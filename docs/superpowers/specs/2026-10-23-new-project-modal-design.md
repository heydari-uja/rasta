# New Project Modal Design

Implemented on 2026-10-23. Production build and all three browser tests pass. Browser coverage includes selecting both goal dates at 390 × 680, checking calendar and modal bounds, and saving the project. Dark, light, and mobile screenshots are available in `docs/color-audit/screenshots/project-wizard-*`.

## Intent

Improve the visual clarity and color hierarchy of the new project modal while preserving its existing two-step project creation flow.

## Goals

- Give the title, step navigation, form fields, and footer clear visual hierarchy.
- Use the existing warm dark palette and orange brand accent with stronger, more intentional contrast.
- Reduce crowding in the goal step while keeping all fields readable in RTL and on narrow screens.
- Make the goal start and target dates reliably selectable, including when the calendar is near a viewport edge.

## Non-goals and constraints

- Keep the current fields, labels, step order, validation, and save behavior.
- Limit styling and markup changes to the new project modal; do not change shared color tokens or other modals.
- The date picker is a shared component; correct its popover positioning wherever it is used.
- Preserve the current light theme and keyboard focus behavior.

## Approaches

- **Selected: scoped visual redesign.** Refine the modal's spacing, step indicator, field surfaces, and action hierarchy using the current design tokens. This addresses the visual issue without changing the familiar workflow.
- **Alternative: rework the creation flow.** Reorganize the steps or introduce a different onboarding layout. Not selected because the current two-step flow and fields are to remain.
- **Alternative: change shared theme tokens.** This would affect other surfaces throughout the app, so the redesign will stay scoped to this modal.

## Design direction

Keep the warm charcoal foundation, use distinct dark surfaces for the step bar and inputs, and reserve orange for the active step and primary action. Reduce the goal step's vertical stack by placing its description and success criterion in a balanced two-column group on wider screens, with a clear two-column date group below; stack these groups on narrow screens. Increase separation between sections with spacing, borders, and typography rather than adding decorative elements.

The start and target date fields already exist in step two. Their calendar is currently positioned below the trigger, while `.calendar-fixed` applies `inset: auto !important`, which overrides the inline coordinates produced by the date-picker component. Remove that conflict and place the calendar above the trigger when there is insufficient room below, clamping it within the viewport.

## Builder decisions

The builder may choose the exact spacing, border treatment, and token combinations needed to achieve the hierarchy above. New modal styles should be scoped under `.project-wizard-form`. The calendar positioning fix belongs in the shared date-picker component because the same conflict affects every use of that component.
