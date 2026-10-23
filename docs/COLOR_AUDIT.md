# Color system audit

## Scope and counts

The baseline is the committed `HEAD` state before this migration. Count comparisons cover 82 tracked source files across the repository; they exclude dependencies, caches, minified files, and `public/build/`. `npm run check:colors` scans source files across the repository, excluding the checker implementation itself as well. Literal-color counts include hex and literal `rgb()` / `hsl()` values, but not generated `rgb(var(...))` expressions.

| Measure | Before | After |
| --- | ---: | ---: |
| Distinct literal colors in source | 103 | 0 |
| Literal color occurrences in source | 113 | 0 |
| `--color-sys-*` references in source | 1,368 | 0 |
| Legacy primitive palette leaves / distinct values | 99 / 99 | replaced by 57 / 52 |
| Semantic color leaves per theme | 135 legacy `sys` leaves | 29 exact semantic tokens |

The final system also has 25 component tokens. Its semantic layer has exactly three surfaces and two borders. Project and label color choices resolve to the eight `project-marker-*` tokens. The legacy marker migration maps existing values across those eight tokens.

The source scan found 113 hardcoded color occurrences (103 distinct values); all are now represented by tokens or generated variable expressions. The old 99-value reference palette was reduced to 52 distinct primitive values. No unresolved color references or logo exceptions were found.

## Contrast results

`npm run check:colors` computes WCAG contrast from resolved primitive values and checks 74 pairs in both themes. All pairs meet the enforced thresholds. The reference comparison values are:

| Pair | Theme | Computed | Reference | Difference |
| --- | --- | ---: | ---: | ---: |
| text-primary / surface-page | Dark | 16.36:1 | 16.4:1 | -0.04 |
| text-primary / surface-base | Dark | 14.65:1 | 14.7:1 | -0.05 |
| text-primary / surface-muted | Dark | 12.19:1 | 12.2:1 | -0.01 |
| text-muted / surface-page | Dark | 7.35:1 | 7.4:1 | -0.05 |
| text-muted / surface-base | Dark | 6.58:1 | 6.6:1 | -0.02 |
| text-muted / surface-muted | Dark | 5.47:1 | 5.5:1 | -0.03 |
| accent / surface-page | Dark | 7.56:1 | 7.6:1 | -0.04 |
| accent / surface-base | Dark | 6.77:1 | 6.8:1 | -0.03 |
| accent / surface-muted | Dark | 5.63:1 | 5.6:1 | +0.03 |
| accent / accent-surface | Dark | 5.62:1 | 5.6:1 | +0.02 |
| on-accent / accent | Dark | 7.56:1 | 7.6:1 | -0.04 |
| border-strong / surface-base | Dark | 3.17:1 | 3.2:1 | -0.03 |
| border-strong / surface-page | Dark | 3.54:1 | 3.5:1 | +0.04 |
| text-primary / surface-page | Light | 15.45:1 | 15.5:1 | -0.05 |
| text-primary / surface-base | Light | 16.81:1 | 16.8:1 | +0.01 |
| text-primary / surface-muted | Light | 14.27:1 | 14.3:1 | -0.03 |
| text-muted / surface-page | Light | 5.31:1 | 5.3:1 | +0.01 |
| text-muted / surface-base | Light | 5.78:1 | 5.8:1 | -0.02 |
| text-muted / surface-muted | Light | 4.91:1 | 4.9:1 | +0.01 |
| accent / surface-page | Light | 5.17:1 | 5.1:1 | +0.07 |
| accent / surface-base | Light | 5.63:1 | 5.1:1 | +0.53 |
| accent / surface-muted | Light | 4.78:1 | 5.1:1 | -0.32 |
| accent / accent-surface | Light | 5.09:1 | 5.1:1 | -0.01 |
| on-accent / accent | Light | 5.63:1 | 5.6:1 | +0.03 |
| border-strong / surface-base | Light | 3.42:1 | 3.4:1 | +0.02 |
| border-strong / surface-page | Light | 3.15:1 | 3.1:1 | +0.05 |

The largest reference differences are light `accent` on `surface-base` (+0.53) and on `surface-muted` (-0.32). The latter computes to 4.78:1, above the enforced 4.5:1 threshold and consistent with the specified `brand-600` value. Project marker contrast on `surface-base` spans 6.55–10.40:1 in dark and 3.26–4.61:1 in light; rounded to one decimal these match the specified ranges, and every marker exceeds 3:1.

## Visual review

The browser suite captured 44 screenshots (22 dark/light pairs) at 1440 × 1000. Each stem has a dark and light PNG in `docs/color-audit/screenshots/`:

- Login (member and admin), profile selection, and the empty My tasks view.
- New project wizard (details, color picker, and goal), and empty and populated project boards.
- Empty and populated My tasks views, new-task modal, and task details modal with the comment composer expanded.
- Expanded and collapsed sidebar, and the profile menu.
- Settings account, appearance, security, security dialog, and selected light/dark appearance states.

For quick review: [populated tasks, dark](color-audit/screenshots/my-tasks-filled-dark.png), [populated tasks, light](color-audit/screenshots/my-tasks-filled-light.png), [task details, dark](color-audit/screenshots/task-details-modal-dark.png), and [settings security, dark](color-audit/screenshots/settings-security-dark.png).

Before-migration screenshots were not captured before the styles changed, so the browser artifacts are after screenshots only.

## Verification

- `npm run build` — passed.
- `npm run check:colors` — passed; all 74 contrast pairs and source restrictions pass.
- `php vendor/bin/pint --test` — passed.
- `php artisan test` — passed, 60 tests and 681 assertions.
- `npm run test:browser` — passed, 3 tests; generated dark and light screenshots for the states listed above.
