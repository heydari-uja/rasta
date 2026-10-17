# Rasta color tokens

## Source and hierarchy

`tokens/color/reference.json` owns literal palette values. Both `semantic.light.json` and `semantic.dark.json` have the same `color.sys` keys and alias `color.ref` entries. `scripts/build-color-tokens.mjs` validates those aliases, schema parity, CSS uses, and 56 important foreground/background contrast pairs, then writes `resources/css/color-tokens.generated.css`. Components use `--color-sys-*` properties only. A `components.json` layer is deliberately absent because the current UI needs no component-specific color roles.

The JSON follows the Design Tokens Community Group shape: `$type: "color"` on the tree and `$value` on each leaf. Dots become hyphens in generated CSS names. For example, `color.sys.action.brand.solid.bg.hover` becomes `--color-sys-action-brand-solid-bg-hover`. Semantic aliases resolve directly to references; values are not duplicated in component stylesheets.

## Roles

| Family | Use |
| --- | --- |
| `bg` | Canvas, surfaces, fields, hover/pressed/selected fills, subtle intent fills, scrim, skeleton |
| `fg` | Default, muted, inverse, disabled, brand, success, warning, error, info text and icons |
| `border` | Subtle, default, control, hover, selected, brand, and intent boundaries |
| `action` | Brand, neutral, and danger solid/quiet actions with explicit default/hover/pressed fills and paired foregrounds |
| `selection` | Persistent selected fill, hover, pressed, foreground, border |
| `focus`, `disabled` | Visible keyboard ring and disabled foreground/fill/border |
| `status` | Success, warning, error, info surface/foreground/border and solid/on-solid pair |
| `data` | Decorative category colors for avatars/stages and a mixing base for user-chosen project/label colors |

Use `fg` for monochrome icons (`currentColor`). Do not use a status or brand fill token as text. User-chosen project and label hex values are persisted data, so their decorative swatches remain dynamic; their names and required state text use semantic foreground colors.

## State and theme rules

Interactive order is disabled, pressed, hover, default. Selection is persistent and has its own colors; focus is independent and uses `:focus-visible`. Disabled controls use all three `disabled` roles rather than whole-element opacity. The same semantic key exists in both themes, so components do not branch on theme for their palette. The existing `rasta-theme` preference (`light`, `dark`, `system`) is stored unchanged. The head scripts resolve it before styles paint, and `app.js` updates `<html data-theme="light|dark" data-theme-preference="light|dark|system">` when the user or OS choice changes. The `.dark` class remains synchronized for existing layout/Tailwind selectors.

The generator checks ordinary text at 4.5:1 and focus indicators at 3:1 for its declared pair matrix. Foregrounds for solid actions are tested on default, hover, and pressed fills. When a new semantic role is added, add its relevant contrast pairs to the generator. Verify actual rendered component pairs as well, especially any translucent or dynamic colors. Dynamic hues should not carry essential meaning by themselves.

## Commands

```sh
npm run tokens:build  # validate and regenerate CSS
npm run tokens:check  # fail if generated CSS is stale
npm run build         # regenerate tokens, then build Vite assets
```

Add a new reference swatch only when a semantic role requires it. Add the role in both theme JSON files, map it to references, add contrast assertions for text or essential boundaries, regenerate CSS, and consume only the `color.sys` property in components.
