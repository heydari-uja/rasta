import { componentNames, semanticNames } from './scripts/color-token-utils.mjs';

const tailwindColorValue = (name) => `rgb(var(--${name}-rgb) / calc(var(--${name}-alpha) * <alpha-value>))`;
const colors = Object.fromEntries([
    ['transparent', 'transparent'],
    ['current', 'currentColor'],
    ['inherit', 'inherit'],
    ...[...semanticNames, ...componentNames].map((name) => [name, tailwindColorValue(name)]),
]);

export default {
    darkMode: 'class',
    theme: {
        colors,
    },
};
