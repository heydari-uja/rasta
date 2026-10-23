import fs from 'node:fs';
import path from 'node:path';

export const semanticNames = [
    'surface-page', 'surface-base', 'surface-muted',
    'border-subtle', 'border-strong',
    'text-primary', 'text-muted',
    'accent', 'accent-hover', 'accent-surface', 'on-accent',
    'status-success', 'status-warning', 'status-danger', 'status-info',
    'status-success-surface', 'status-warning-surface', 'status-danger-surface', 'status-info-surface',
    'status-danger-hover', 'scrim',
    ...Array.from({ length: 8 }, (_, index) => `project-marker-${index + 1}`),
];

export const componentNames = [
    'button-brand-solid-bg-default', 'button-brand-solid-bg-hover', 'button-brand-solid-bg-pressed', 'button-brand-solid-fg',
    'button-brand-quiet-bg-default', 'button-brand-quiet-bg-hover', 'button-brand-quiet-bg-pressed', 'button-brand-quiet-fg',
    'button-neutral-solid-bg', 'button-neutral-solid-border', 'button-neutral-solid-border-hover', 'button-neutral-solid-fg',
    'button-neutral-quiet-bg-default', 'button-neutral-quiet-bg-hover', 'button-neutral-quiet-bg-pressed', 'button-neutral-quiet-fg', 'button-neutral-quiet-fg-hover',
    'button-danger-solid-bg-default', 'button-danger-solid-bg-hover', 'button-danger-solid-bg-pressed', 'button-danger-solid-fg',
    'field-bg', 'field-border', 'field-fg', 'field-placeholder',
];

const primitiveNames = [
    'neutral-0', 'neutral-25', 'neutral-50', 'neutral-100', 'neutral-200', 'neutral-400', 'neutral-500', 'neutral-550',
    'neutral-600', 'neutral-750', 'neutral-800', 'neutral-875', 'neutral-925', 'neutral-950',
    'brand-50', 'brand-300', 'brand-400', 'brand-500', 'brand-600', 'brand-700', 'brand-900',
    'success-50', 'success-500', 'success-700', 'success-900',
    'warning-50', 'warning-500', 'warning-700', 'warning-900',
    'danger-50', 'danger-300', 'danger-500', 'danger-700', 'danger-800', 'danger-900',
    'info-50', 'info-500', 'info-700', 'info-900',
    ...Array.from({ length: 8 }, (_, index) => [`project-${index + 1}-400`, `project-${index + 1}-600`]).flat(),
    'scrim-dark', 'scrim-light',
];

function flatten(node, prefix = '', result = {}) {
    for (const [key, value] of Object.entries(node ?? {})) {
        if (key.startsWith('$')) continue;
        const name = prefix ? `${prefix}.${key}` : key;
        if (value && typeof value === 'object' && '$value' in value) result[name] = value.$value;
        else if (value && typeof value === 'object') flatten(value, name, result);
        else throw new Error(`Invalid token: ${name}`);
    }
    return result;
}

const assertNames = (actual, expected, layer) => {
    const unexpected = actual.filter((name) => !expected.includes(name));
    const missing = expected.filter((name) => !actual.includes(name));
    if (unexpected.length || missing.length) {
        throw new Error(`${layer} token names differ. Missing: ${missing.join(', ') || 'none'}. Unexpected: ${unexpected.join(', ') || 'none'}.`);
    }
};

export function colorParts(value, tokenName = 'color') {
    let channels;
    let alpha = 1;
    const hex = /^#([0-9a-f]{6})$/i.exec(value);
    const rgb = /^rgb\(\s*(\d{1,3})\s+(\d{1,3})\s+(\d{1,3})\s*\/\s*(0(?:\.\d+)?|1(?:\.0+)?)\s*\)$/i.exec(value);

    if (hex) {
        channels = hex[1].match(/../g).map((channel) => Number.parseInt(channel, 16));
    } else if (rgb) {
        channels = rgb.slice(1, 4).map(Number);
        alpha = Number(rgb[4]);
    } else {
        throw new Error(`${tokenName} must be an opaque six-digit hex or an alpha color primitive: ${value}`);
    }

    if (channels.some((channel) => channel < 0 || channel > 255)) throw new Error(`${tokenName} has an RGB channel outside 0..255.`);
    return { channels, alpha };
}

export function loadColorTokens(root) {
    const tokenDir = path.join(root, 'tokens/color');
    const read = (name) => JSON.parse(fs.readFileSync(path.join(tokenDir, name), 'utf8'));
    const primitiveTree = read('primitive.json').color?.primitive;
    const darkTree = read('semantic.dark.json').color?.semantic;
    const lightTree = read('semantic.light.json').color?.semantic;
    const componentTree = read('component.json').color?.component;
    if (!primitiveTree || !darkTree || !lightTree || !componentTree) {
        throw new Error('Color tokens must define primitive, semantic, and component layers in the existing JSON schema.');
    }

    const primitive = flatten(primitiveTree);
    const dark = flatten(darkTree);
    const light = flatten(lightTree);
    const components = flatten(componentTree);
    assertNames(Object.keys(primitive), primitiveNames, 'Primitive');
    assertNames(Object.keys(dark), semanticNames, 'Dark semantic');
    assertNames(Object.keys(light), semanticNames, 'Light semantic');
    assertNames(Object.keys(components), componentNames, 'Component');

    const parsedPrimitives = Object.fromEntries(Object.entries(primitive).map(([name, value]) => [name, colorParts(value, `primitive.${name}`)]));
    const semanticPrimitive = { dark: {}, light: {} };
    for (const [mode, values] of Object.entries({ dark, light })) {
        for (const [name, value] of Object.entries(values)) {
            const ref = /^\{primitive\.([a-z0-9-]+)\}$/.exec(value);
            if (!ref || !(ref[1] in parsedPrimitives)) throw new Error(`Invalid ${mode} semantic reference ${name}: ${value}`);
            semanticPrimitive[mode][name] = ref[1];
        }
    }

    for (const [name, value] of Object.entries(components)) {
        if (value === 'transparent') continue;
        const ref = /^\{semantic\.([a-z0-9-]+)\}$/.exec(value);
        if (!ref || !(ref[1] in dark)) throw new Error(`Invalid component reference ${name}: ${value}`);
    }

    const resolved = { dark: {}, light: {} };
    for (const mode of ['dark', 'light']) {
        for (const name of semanticNames) resolved[mode][name] = parsedPrimitives[semanticPrimitive[mode][name]];
    }

    return { primitive, parsedPrimitives, semanticPrimitive, semanticNames, components, componentNames, resolved };
}

export function relativeLuminance({ channels }) {
    const linear = channels.map((value) => {
        const channel = value / 255;
        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });
    return linear[0] * 0.2126 + linear[1] * 0.7152 + linear[2] * 0.0722;
}

export function contrastRatio(first, second) {
    const a = relativeLuminance(first);
    const b = relativeLuminance(second);
    return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
}

export function contrastChecks(tokens) {
    const checks = [];
    const add = (mode, foreground, background, minimum, label = `${foreground} on ${background}`) => {
        checks.push({ mode, foreground, background, label, minimum, ratio: contrastRatio(tokens.resolved[mode][foreground], tokens.resolved[mode][background]) });
    };

    for (const mode of ['dark', 'light']) {
        for (const foreground of ['text-primary', 'text-muted']) {
            for (const background of ['surface-page', 'surface-base', 'surface-muted']) add(mode, foreground, background, 4.5);
        }
        for (const background of ['surface-page', 'surface-base', 'surface-muted', 'accent-surface']) add(mode, 'accent', background, 4.5);
        add(mode, 'on-accent', 'accent', 4.5);
        add(mode, 'on-accent', 'status-danger', 4.5);
        add(mode, 'on-accent', 'status-danger-hover', 4.5);
        for (const status of ['success', 'warning', 'danger', 'info']) {
            const statusMinimum = mode === 'dark' ? 5.4 : 4.5;
            for (const background of ['surface-base', 'surface-muted', `status-${status}-surface`]) add(mode, `status-${status}`, background, statusMinimum);
        }
        for (const background of ['surface-base', 'surface-page']) {
            add(mode, 'border-strong', background, 3);
            add(mode, 'accent', background, 3, `accent focus outline on ${background}`);
        }
        for (let index = 1; index <= 8; index++) add(mode, `project-marker-${index}`, 'surface-base', 3);
    }
    return checks;
}

export const referenceContrasts = {
    dark: {
        'text-primary': { 'surface-page': 16.4, 'surface-base': 14.7, 'surface-muted': 12.2 },
        'text-muted': { 'surface-page': 7.4, 'surface-base': 6.6, 'surface-muted': 5.5 },
        accent: { 'surface-page': 7.6, 'surface-base': 6.8, 'surface-muted': 5.6, 'accent-surface': 5.6 },
        'on-accent': { accent: 7.6 },
        'border-strong': { 'surface-base': 3.2, 'surface-page': 3.5 },
    },
    light: {
        'text-primary': { 'surface-page': 15.5, 'surface-base': 16.8, 'surface-muted': 14.3 },
        'text-muted': { 'surface-page': 5.3, 'surface-base': 5.8, 'surface-muted': 4.9 },
        accent: { 'surface-page': 5.1, 'surface-base': 5.1, 'surface-muted': 5.1, 'accent-surface': 5.1 },
        'on-accent': { accent: 5.6 },
        'border-strong': { 'surface-base': 3.4, 'surface-page': 3.1 },
    },
};

export function contrastReferenceDifferences(tokens) {
    const result = [];
    for (const [mode, foregrounds] of Object.entries(referenceContrasts)) {
        for (const [foreground, backgrounds] of Object.entries(foregrounds)) {
            for (const [background, reference] of Object.entries(backgrounds)) {
                const ratio = contrastRatio(tokens.resolved[mode][foreground], tokens.resolved[mode][background]);
                result.push({ mode, foreground, background, reference, ratio, difference: ratio - reference });
            }
        }
    }
    return result;
}
