---
paths:
  - 'modules/**/resources/js/**'
---

# Js

## Base UI components come from modules/Shared (shadcn) — never hand-roll them
modules/Shared/resources/js/components/ui is the single source of truth for base components (button, input, select, checkbox, dialog, alert-dialog, badge, card, table, tabs, sidebar, field, alert, tooltip, pagination, etc.). They are shadcn/ui (radix, style radix-nova); components.json aliases point to @shared/*. Add more with `npx shadcn@latest add <name>` (it installs into Shared; fix any `from "cn"` import to `@shared/lib/utils`, delete any stray pnpm-lock.yaml / "cn" package). Before writing any UI, check Shared/ui for an existing component and use it, including badges, dialogs and form controls. Never recreate a base component inside a module. Only when no shared component fits, build a custom component inside that module (e.g. modules/Platform/resources/js/Components/ConsoleParts.tsx) composed FROM the shared primitives. Use semantic tokens (bg-primary, text-muted-foreground), the shared Field/FieldGroup for forms, lucide-react for icons. Overlays portal to <body>, so theme tokens live on the root element (the console host adds `console-theme` to <html>).
