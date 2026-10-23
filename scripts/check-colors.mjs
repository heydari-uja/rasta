import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { contrastChecks, contrastReferenceDifferences, loadColorTokens } from './color-token-utils.mjs';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const tokens = loadColorTokens(root);
const failures = [];

function sourceFiles(directory) {
    if (!fs.existsSync(directory)) return [];
    const result = [];
    for (const entry of fs.readdirSync(directory, { withFileTypes: true })) {
        const fullPath = path.join(directory, entry.name);
        const generatedBuild = path.join(root, 'public', 'build');
        if (entry.isDirectory() && ['.git', 'node_modules', 'vendor', 'storage'].includes(entry.name)) continue;
        if (fullPath === path.join(root, 'scripts', 'check-colors.mjs')) continue;
        if (fullPath === generatedBuild || fullPath.startsWith(`${generatedBuild}${path.sep}`)) continue;
        if (fullPath === path.join(root, 'bootstrap', 'cache')) continue;
        if (entry.isDirectory()) result.push(...sourceFiles(fullPath));
        else if (!/\.min\./i.test(entry.name) && /\.(?:blade\.php|php|css|js|mjs|ts|tsx|jsx|html|svg|xml)$/i.test(entry.name)) result.push(fullPath);
    }
    return result;
}

const literalColorPatterns = [
    ['hex color', /#[0-9a-f]{3,8}\b/gi],
    ['literal rgb/hsl color', /\b(?:rgba?|hsla?)\(\s*(?!var\s*\(|calc\s*\()/gi],
    ['Tailwind palette class', /\b(?:bg|text|border|ring|fill|stroke|outline|accent|caret|placeholder|divide|decoration|from|via|to)-(?:orange|gray|red|blue|green|yellow|purple|pink|indigo|violet|amber|emerald|teal|cyan|slate|stone|zinc|neutral|rose|lime|sky|fuchsia|black|white)(?:-\d{2,3})?(?:\/\d{1,3})?\b/gi],
    ['Tailwind arbitrary color', /\b(?:bg|text|border|ring|fill|stroke|outline|accent|caret|placeholder|divide|decoration)-\[(?:[^\]]*(?:#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|color-mix)\s*\(|\b(?:transparent|currentcolor|black|white|red|green|blue|orange|yellow|purple|pink|gray|grey|navy|teal|olive|maroon|silver|lime|aqua|fuchsia|rebeccapurple)\b|var\(\s*--(?:surface|text|accent|status|border|button|field|project-marker)-)[^\]]*|[a-z]+)\]/gi],
    ['legacy color token', /--color-sys-[\w-]+|--color-ref-[\w-]+|\b(?:surface-raised|surface-hover|surface-sidebar|surface-clear|focus-ring)\b/gi],
];

for (const file of sourceFiles(root)) {
    const content = fs.readFileSync(file, 'utf8');
    for (const [label, pattern] of literalColorPatterns) {
        for (const match of content.matchAll(pattern)) failures.push(`${path.relative(root, file)}: ${label} ${match[0]}`);
    }
}

const tailwindUrl = pathToFileURL(path.join(root, 'tailwind.config.js')).href;
const tailwind = (await import(tailwindUrl)).default;
const allowedColors = ['transparent', 'current', 'inherit', ...tokens.semanticNames, ...tokens.componentNames].sort();
const actualColors = Object.keys(tailwind.theme?.colors ?? {}).sort();
if (actualColors.join('\n') !== allowedColors.join('\n')) {
    failures.push(`tailwind.config.js color keys differ. Expected: ${allowedColors.join(', ')}. Found: ${actualColors.join(', ') || 'none'}.`);
}

for (const { mode, label, ratio, minimum } of contrastChecks(tokens)) {
    if (ratio < minimum) failures.push(`${mode} contrast ${label}: ${ratio.toFixed(2)} < ${minimum.toFixed(2)}.`);
}

if (failures.length) {
    console.error(`Color checks failed (${failures.length}):\n${failures.map((failure) => `- ${failure}`).join('\n')}`);
    process.exitCode = 1;
} else {
    const checks = contrastChecks(tokens);
    console.log(`Color checks passed: ${tokens.semanticNames.length} semantic tokens per theme, ${checks.length} WCAG contrast pairs, and source color restrictions.`);
    for (const { mode, foreground, background, reference, ratio, difference } of contrastReferenceDifferences(tokens)) {
        console.log(`Reference ${mode} ${foreground} on ${background}: ${ratio.toFixed(2)}:1 (reference ${reference.toFixed(1)}:1, difference ${difference.toFixed(2)}).`);
    }
}
