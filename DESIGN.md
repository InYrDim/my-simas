---
name: SIMAS
description: A flat, ruled school ledger — one green stamp for what is confirmed, three type sizes, and words before icons (icons only in the provider console).
colors:
  stamp-ink: "#047857"
  stamp-ink-deep: "#065f46"
  stamp-ink-bright: "#059669"
  stamp-wash: "#ecfdf5"
  stamp-wash-line: "#a7f3d0"
  stamp-mist: "#6ee7b7"
  void-red: "#dc2626"
  void-red-bright: "#ef4444"
  void-red-deep: "#b91c1c"
  void-red-mist: "#f87171"
  void-wash: "#fef2f2"
  void-wash-line: "#fecaca"
  provisional-amber: "#fcd34d"
  ledger-paper: "#ffffff"
  form-grey: "#fafafa"
  rule: "#e4e4e7"
  rule-strong: "#d4d4d8"
  pencil-grey: "#71717a"
  pencil-deep: "#52525b"
  faint-pencil: "#a1a1aa"
  ledger-ink: "#18181b"
  board-ink: "#3f3f46"
  chalk-bright: "#f4f4f5"
  night-board: "#09090b"
  night-rule: "#27272a"
  # Lembar world only (landing + sign-in/account pages, scoped under .lembar). Not app tokens.
  landing-ink: "#09a8ed"
  landing-ink-deep: "#006b9c"
  landing-ink-line: "#acd8f4"
  landing-ink-tint: "#ecf7fe"
  landing-graphite: "#27313c"
  landing-pencil: "#5b6674"
  landing-paper: "#f4f8fb"
  landing-correction: "#c0261b"
typography:
  title:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.25rem"
    fontWeight: 600
    lineHeight: "1.4"
  body:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: "1.43"
  label:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: "1.43"
  meta:
    fontFamily: "Instrument Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: "1.33"
  slug:
    fontFamily: "ui-monospace, SFMono-Regular, Menlo, monospace"
    fontSize: "0.75rem"
    fontWeight: 400
  landing-display:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "2.75rem"
    fontWeight: 800
    lineHeight: "0.95"
    letterSpacing: "-0.025em"
    fontVariation: "\"wdth\" 72"
  landing-band:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "2rem"
    fontWeight: 800
    lineHeight: "1"
    letterSpacing: "-0.02em"
    fontVariation: "\"wdth\" 75"
  landing-title:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 700
    letterSpacing: "-0.01em"
    fontVariation: "\"wdth\" 85"
  landing-wordmark:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.5rem"
    fontWeight: 900
    letterSpacing: "-0.02em"
    fontVariation: "\"wdth\" 118"
  landing-lead:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 400
    lineHeight: "1.625"
  landing-body:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: "1.625"
  landing-action:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 600
  landing-field-label:
    fontFamily: "Archivo, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 700
    letterSpacing: "0.1em"
  landing-code:
    fontFamily: "Azeret Mono, ui-monospace, monospace"
    fontSize: "1rem"
    fontWeight: 500
rounded:
  sm: "4px"
  md: "8px"
  lg: "12px"
  pill: "9999px"
  landing-sheet: "2px"
spacing:
  xs: "2px"
  sm: "4px"
  sm-half: "6px"
  md: "8px"
  md-half: "10px"
  lg: "12px"
  xl: "16px"
  "2xl": "20px"
  "3xl": "24px"
  "4xl": "32px"
  "5xl": "40px"
  bar: "64px"
components:
  button-stamp:
    backgroundColor: "{colors.stamp-ink}"
    textColor: "{colors.ledger-paper}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
  button-stamp-hover:
    backgroundColor: "{colors.stamp-ink-deep}"
  button-ink:
    backgroundColor: "{colors.ledger-ink}"
    textColor: "{colors.ledger-paper}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "10px 16px"
  button-ink-hover:
    backgroundColor: "{colors.board-ink}"
  button-void:
    backgroundColor: "{colors.void-red}"
    textColor: "{colors.ledger-paper}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  button-void-hover:
    backgroundColor: "{colors.void-red-bright}"
  button-outline-stamp:
    backgroundColor: "{colors.ledger-paper}"
    textColor: "{colors.stamp-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  button-ghost-night:
    backgroundColor: "transparent"
    textColor: "{colors.rule}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "8px 16px"
  input-text:
    backgroundColor: "{colors.ledger-paper}"
    textColor: "{colors.ledger-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "8px 12px"
  card:
    backgroundColor: "{colors.ledger-paper}"
    rounded: "{rounded.md}"
    padding: "24px"
  card-raised:
    backgroundColor: "{colors.ledger-paper}"
    rounded: "{rounded.lg}"
    padding: "32px"
  panel-night:
    backgroundColor: "{colors.night-board}"
    rounded: "{rounded.md}"
    padding: "20px"
  chip-role:
    backgroundColor: "{colors.stamp-wash}"
    textColor: "{colors.stamp-ink}"
    typography: "{typography.meta}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  chip-pending:
    backgroundColor: "{colors.night-board}"
    textColor: "{colors.provisional-amber}"
    typography: "{typography.meta}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  chip-muted:
    backgroundColor: "{colors.form-grey}"
    textColor: "{colors.pencil-grey}"
    typography: "{typography.meta}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  banner-success:
    backgroundColor: "{colors.stamp-wash}"
    textColor: "{colors.stamp-ink-deep}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "12px 16px"
  banner-void:
    backgroundColor: "{colors.void-wash}"
    textColor: "{colors.void-red-deep}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "12px 16px"
  list-row:
    backgroundColor: "{colors.ledger-paper}"
    rounded: "{rounded.md}"
    padding: "16px 20px"
  topbar:
    backgroundColor: "{colors.ledger-paper}"
    height: "64px"
  sidebar-night:
    backgroundColor: "{colors.night-board}"
    width: "240px"
  landing-button-primary:
    backgroundColor: "{colors.landing-graphite}"
    textColor: "{colors.landing-paper}"
    typography: "{typography.landing-action}"
    rounded: "{rounded.landing-sheet}"
    padding: "0 24px"
    height: "48px"
  landing-button-primary-hover:
    backgroundColor: "{colors.landing-ink-deep}"
  landing-button-inverted:
    backgroundColor: "{colors.landing-paper}"
    textColor: "{colors.landing-graphite}"
    typography: "{typography.landing-action}"
    rounded: "{rounded.landing-sheet}"
    padding: "0 24px"
    height: "48px"
  landing-button-inverted-hover:
    backgroundColor: "{colors.landing-ink-tint}"
  landing-bubble:
    backgroundColor: "{colors.landing-paper}"
    textColor: "{colors.landing-ink}"
    rounded: "{rounded.pill}"
    size: "20px"
  landing-bubble-filled:
    backgroundColor: "{colors.landing-graphite}"
  landing-sheet-band:
    backgroundColor: "{colors.landing-ink-tint}"
    textColor: "{colors.landing-graphite}"
    typography: "{typography.landing-band}"
    padding: "24px 20px"
  landing-close-band:
    backgroundColor: "{colors.landing-ink}"
    textColor: "{colors.landing-paper}"
    typography: "{typography.landing-display}"
    padding: "80px 20px"
  landing-timing-rail:
    backgroundColor: "{colors.landing-paper}"
    width: "28px"
---

# Design System: SIMAS — Buku Besar Sekolah

## Overview

**Creative North Star: "Buku Besar Sekolah" (The School Ledger)**

SIMAS is a ledger, not a dashboard. The metaphor is the bound register a school keeps
in its headmaster's office: ruled lines, one ink, a stamp that means *this is
confirmed*, and nothing on the page that isn't an entry, a rule, or the name of a
thing. The interface never performs. It records, marks, and gets out of the way of
the person who has the job to do — the teacher on a phone at 7am, the school admin
at a desk, the provider operator clearing an approval queue at night.

The personality is calm, exact, and unhurried, in plain Bahasa Indonesia. The copy
register is institutional and serviceable: *Daftarkan sekolah Anda*, *Tidak ada
pengajuan pending. Semua beres.* No marketing enthusiasm, no cheerfulness, no
playfulness. The single green in the system is not decorative — it is a stamp, and
stamps are rare by definition. Everything else is paper, rule, and grey pencil.

Density is deliberate. The system runs on three type sizes (20px title, 14px body,
12px meta) at a 14px base, with 8px corners, 1px rules, and generous page padding
(24px sides, 40px top) so that a dense list of records still breathes. The product's
primary user is on a low-end Android phone on a slow connection, so the system spends
its weight on legibility and does almost nothing with motion: color transitions, a 1px
press on primary buttons, and — once per surface at most — a single authored entrance.
A surface that earns one writes it in page-scoped CSS under a class it owns, keeps it
under a second, and gates it behind `prefers-reduced-motion`. The system has no global
motion tokens to reach for.

Two grounds, one language. The school portal is paper (`form-grey` page, white
records, `rule` hairlines). The provider console is the same ledger at night
(`night-board` page, translucent night panels, `night-rule` hairlines) — the same
voice and the same component shapes, lit differently, because that operator is
working through a queue alone. Unauthenticated public surfaces are the third case:
paper and ink only.

Confirmed rejections: no purple or indigo SaaS gradient, no glassmorphism or blur,
no heavy or decorative drop shadows, no pill-shaped everything, no icons on the school portal, no
emoji, no illustration, no display or hero type, no gradients, no rounded
everything.

**Key Characteristics:**

- One green (Stamp Ink) and one meaning: confirmed, permitted, actionable.
- Three type sizes, one family, two weights of emphasis (500, 600).
- 8px corners for everything; pills reserved for status chips; 12px only on the raised auth card.
- Flat surfaces separated by 1px rules and a change of paper tone; one shadow in the entire system.
- Words first — every control is labeled in Bahasa Indonesia; icons exist only in the provider console, beside a label.
- One centered column, fluid. One named breakpoint exists — `sm` (640px) — and a
  surface may reach for it only deliberately.
- Two grounds (paper / night) sharing one component language and one voice.

## Colors

The palette is stock neutral greys with a single green accent, plus red for voided
and blocked states and amber for entries still awaiting a human. Nothing is
saturated except the stamp.

### Primary
- **Stamp Ink** (#047857): the system's only accent. Primary buttons, links, the
  focus border, checkboxes, role chips, and success surfaces. It means *go, done,
  confirmed, or permitted* — nothing else.
- **Stamp Ink Deep** (#065f46): the pressed/hover state of Stamp Ink, and the text
  color on a `stamp-wash` success surface.
- **Stamp Ink Bright** (#059669): focus borders and the primary button on the night
  ground, where a deeper green would sink. The only place the accent is lightened
  for contrast against `night-board`.

### Secondary
- **Provisional Amber** (#fcd34d): the `pending` chip and nothing else. An entry
  that has been received but not yet stamped by a human.

### Tertiary
- **Void Red family** — **Void Red** (#dc2626), **Void Red Bright** (#ef4444, hover),
  **Void Red Deep** (#b91c1c, text on wash), **Void Red Mist** (#f87171, text on
  night): deactivating a user, rejecting an application, a destructive confirm, an
  invalid field, an error message. Red never means "cancel" in this system.

### Neutral
- **Ledger Paper** (#ffffff): the record surface on the school portal, and all text
  on a filled button.
- **Form Grey** (#fafafa): the portal's page ground behind the records.
- **Rule** (#e4e4e7): the hairline that separates records from ground, and body text
  on the night ground.
- **Rule Strong** (#d4d4d8): the input stroke on paper, and field labels on the night ground.
- **Pencil Grey** (#71717a): secondary text, hints, empty-state copy, label text
  inside the console's definition lists.
- **Pencil Deep** (#52525b): the one mid-weight label that needs more presence than
  Pencil Grey.
- **Faint Pencil** (#a1a1aa): meta text, placeholders, slugs, timestamps, the
  console's navigation links.
- **Ledger Ink** (#18181b): headings and strong text on paper; the fill of the
  public form's submit; also the console's panel ground at half opacity.
- **Board Ink** (#3f3f46): the hover state of Ledger Ink, input strokes on night.
- **Chalk Bright** (#f4f4f5): headings and strong text on the night ground.
- **Night Board** (#09090b): the console's page ground.
- **Night Rule** (#27272a): the console's hairline.

Translucent steps are a night-ground-only device: console panels are `ledger-ink`
at 50% over `night-board`, success is `stamp-ink` at 40% over `night-board` with a
50% `stamp-ink-deep` border, and void is the same at 20%/30% with a 50%-60%
`void-red-deep` border. Paper surfaces are never translucent.

The one value in the app that sits outside the palette is the Inertia navigation
progress bar, `#4B5563` — a plain neutral grey rather than a zinc, and deliberately
so: a navigation in progress is neither confirmed nor voided.

### Console theme (supersedes the night ground and the green stamp for the provider console)
The provider console is **light** and uses the shadcn token set from the design
reference **verbatim** (`.console-theme` in `resources/css/app.css`; the school
portal keeps paper + Stamp Ink). Tokens: `background`/`foreground`, `card`,
`popover`, `muted`, `secondary`, `border`, `input`, `ring`, `chart-1..5`,
`sidebar*`, **primary** (sky, `oklch(0.693 0.150 237)`, white
`primary-foreground`), **accent** (amber `oklch(0.730 0.156 70)`), **destructive**
(`oklch(0.637 0.208 25)`). Primary is the filled button, focus ring, current-page
fill (`sidebar-accent` + `sidebar-accent-foreground`), icon accent on the active
row, and chart bars (`chart-1`); accent tints the pending state; destructive is
voided/blocked. Status chips and notices are a tinted fill with a tinted border and
**neutral foreground text**, so the word stays legible. Shape and type follow the
reference: **square corners** (radius 0), **Geist** (body) and **JetBrains Mono**
(slugs, identifiers), letter-spacing -0.01em, and the reference's soft offset
shadows (`console-card` = `--shadow-sm`, `console-pop` = `--shadow-md`) on panels,
tooltips and the login card. This replaces "no shadow on the console". The One Green
Rule becomes **one primary filled button per screen**. Known trade-off: white text on
the reference primary and primary-coloured small text are about 3:1, below 4.5:1.

### Named Rules
**The One Green Rule.** Stamp Ink appears only where something is confirmed,
permitted, or actionable. One filled green button per screen, plus links, focus, and
success. If a second green region appears, one of them is wrong.

**The Public Form Rule.** On an unauthenticated public surface, the submit is
**Ledger Ink**, not green (the school application form's *Daftarkan sekolah* button
is ink). Green is a credential of a verified session; the public form has not earned
one.

**The Void Rule.** Red means voided or blocked — a user is deactivated, an
application is rejected, a field is invalid. Nothing in this system hard-deletes a
record, so red never appears on a "remove permanently" control.

**The Provisional Rule.** Amber is only ever `pending`. Never a warning about the
user's own work, never a caution before an action.

## Typography

**Display Font:** n/a — the system has no display size.
**Body Font:** Instrument Sans (with `ui-sans-serif, system-ui, sans-serif` fallback), self-hosted at weights 400, 500, and 600 only.
**Label/Mono Font:** the platform monospace stack, used in exactly one role.

**Character:** One family, three sizes, two weights of emphasis. Instrument Sans is
a plain grotesque with slightly narrow proportions, which is what lets 14px text
carry dense record lists without shouting. There is no second family and no serif:
a ledger is set in one hand.

### Hierarchy
- **Title** (600, 1.25rem/20px, 1.4): the page's one heading — *Daftar Pengguna*,
  *Pengajuan Sekolah*, the school name on a review page. Never a hero, never
  repeated; a screen has one title.
- **Body** (400, 0.875rem/14px, 1.43): every label, paragraph, button, field value,
  and list row. The system's default and its floor — no text below 14px.
- **Label** (500, 0.875rem/14px, 1.43): form labels and emphasis inside body copy
  (a person's name in a list row). Weight is the only emphasis tool.
- **Meta** (400, 0.75rem/12px, 1.33): the only smaller size. Field errors, hints,
  timestamps, chips, the "aktif/nonaktif" status word, empty-state copy.
- **Slug** (400, 0.75rem/12px, monospace): the only monospace in the product — a
  slug, a host, or an identifier the user is expected to read character by character
  (`/sekolah-a`). Three uses in the codebase; that is the budget.

### Named Rules
**The Three-Size Rule.** Twenty, fourteen, twelve. If a screen seems to need a
fourth size, it needs less text or a clearer title instead. A ledger has no cover.

**The Weight Is the Emphasis Rule.** Hierarchy comes from 400 → 500 → 600 and from
grey level, never from a new size, a color shift, or an italic. Ledger Ink for
headings, Pencil Grey for secondary, Faint Pencil for meta.

**The Slug Rule.** Monospace is reserved for identifiers a human must verify or
type. Never for prose, labels, or numbers that merely happen to be numeric.

## Layout

One centered column per page, capped and guttered. Every page shell is a minimum
full-viewport height (`100dvh`, not `100vh`, so mobile browser chrome cannot clip
content). Content is horizontally centered with 24px side gutters (spacing `3xl`, the framework's step 6) and
40px of top padding (spacing `5xl`, step 10).

Container widths are chosen by density, and the system uses three: `max-w-sm`/
`max-w-md` (384/448px) for the centered auth and public-form cards, `max-w-3xl`
(768px) for a single record being edited, `max-w-4xl` (896px) for the console's
review pages, and `max-w-5xl` (1024px) for the widest record list. On the portal a 64px top bar
(spacing `bar`, step 16) spans the full width; on the console a 240px left sidebar
replaces it and the column is centered in the space to its right.

Vertical rhythm is a single ladder: 8px inside controls, 12px between closely
related items, 16px between a control and its label, 20–24px inside panels, 32–40px
between blocks. Lists are stacks of record cards 12px apart (spacing `lg`, step 3), each card
padded 16px vertically by 20px horizontally.

**The system defines exactly one breakpoint: `sm`, Tailwind's 640px.** It was
introduced by the school landing (`Core/Beranda`), the first surface written
phone-first, where it promotes a stacked masthead to a two-column one. Everything else
is fluid; the only other adaptive qualifier in the codebase is a dormant dark-mode
variant. This is still mostly a gap, not a style: the primary user is on a phone and
most surfaces remain desktop-shaped. Any new surface that adapts must say which
breakpoint it reaches for and why, and must start from the phone width — never assume
a scale exists beyond `sm`.

## Elevation & Depth

This system is flat. Depth is expressed almost entirely with a 1px hairline and a
change of paper tone — a white record on a `form-grey` ground, a translucent night
panel on a `night-board` ground. There is exactly one shadow in the whole codebase,
on the raised auth card, and it exists because that card is genuinely detached from
its ground: it is the one element a user meets before they have any context.

### Shadow Vocabulary
- **Card Lift** (`box-shadow: 0 1px 2px 0 rgb(0 0 0 / 0.05)`): the centered auth
  and public-form card, and nothing else. `shadow-sm`.

### Named Rules
**The Ruled Surface Rule.** Separate surfaces with a 1px rule before you consider a
shadow. If a component needs elevation to be legible, it needs a rule instead.

**The One Shadow Rule.** `shadow-sm` on a centered card is the entire shadow budget.
No lifted hover states, no elevated modals, no shadow on the console (a shadow on
`night-board` is invisible anyway — the night ground separates with tone and rule).

## Shapes

8px corners are the system's default and cover every button, input, panel, banner,
and record row. 4px is reserved for the small square edges of a checkbox. 12px
appears on exactly one element — the raised auth card — which is what makes it read
as the detached, pre-context card it is. Fully rounded shapes are not a style
choice but a *category* marker: pills mean status (role, pending, no role) and
nothing else. The four rounding names map onto the framework's steps — `sm` is step
0.25, `md` is step 0.5 (the default, used by nearly everything), `lg` is step 0.75,
and `pill` is the fully-rounded step. Dividers are 1px hairlines; there are no 2px
borders and no dashed
rules. Nothing is clipped at an angle; the ledger has square corners and round
stamps, in that order.

## Components

Only two primitives are shared in code — the auth shell and the auth input.
Everything else is composed per page from these tokens, which is why the values
below are invariants rather than a component library.

### Buttons
- **Shape:** gently rounded (8px radius), weight 600 at 14px, padding 10px by 16px
  (8px by 16px for the smaller danger and outline variants).
- **Stamp (primary, school portal):** Stamp Ink fill, Ledger Paper text, 10px × 16px.
  Hover to Stamp Ink Deep; press 1px down (`translate-y-px`); disabled at 60%
  opacity with a not-allowed cursor.
- **Ink (public surface):** Ledger Ink fill, Ledger Paper text, same geometry. Hover
  to Board Ink. Reserved for unauthenticated pages.
- **Void (destructive):** Void Red fill, Ledger Paper text, 8px × 16px, no press
  offset. Hover to Void Red Bright. Only for deactivation and rejection.
- **Outline Stamp (secondary, paper):** 1px Stamp Ink border, Stamp Ink text, paper
  background, 8px × 16px. Hover fills with Stamp Wash. The paired secondary next to
  a Stamp button (*Undang* beside *Tambah Pengguna*).
- **Ghost (console):** transparent with a 1px `board-ink` border and `rule` text on
  night; hover fills with the panel tone. The console's cancel control.
- **Text buttons and links:** 14px. On paper, Stamp Ink deepening to Stamp Ink Deep
  with an underline on hover. On night, Faint Pencil to Rule. A red text button is
  the console's inline destructive ("Tolak pengajuan ini?"), and it darkens rather
  than filling.
- **No icon appears inside a portal button.** The label is the whole control (console icon buttons carry an aria-label).

### Chips
- **Style:** pill (`9999px`), 2px × 10px padding, 12px weight 500, 1px border in a
  tint of the text color.
- **Role chip:** Stamp Wash ground, Stamp Wash Line border, Stamp Ink text.
- **Muted chip (no role):** Form Grey ground, Rule border, Pencil Grey text.
- **Pending chip:** Night Board ground, a translucent Stamp/Amber family border,
  Provisional Amber text. On paper a pending state has no chip yet — the word alone.

### Cards / Containers
- **Corner style:** 8px; 12px on the raised auth card.
- **Background:** Ledger Paper on the portal; translucent Ledger Ink over Night Board
  on the console (`zinc-900/50`), with `night-rule` borders.
- **Shadow strategy:** rules and tone only; `shadow-sm` on the raised auth card
  (see Elevation).
- **Border:** 1px — Rule on paper, Night Rule on night. Red-tinted border only inside
  a void panel (`void-red-deep` at 50%).
- **Internal padding:** 24px (20px for the tighter console panel), 32px on the raised
  card. Record rows use 16px × 20px.
- **Definition lists** (the console's record summary) are a 1px-bordered night panel
  with a two-column grid: label in Faint Pencil, value in Rule, 8px row gap.

### Inputs / Fields
- **Style:** 8px corners, 8px × 12px padding, 14px Ledger Ink text on Ledger Paper;
  Rule Strong stroke. On the console: Night Board ground, `board-ink` stroke,
  Chalk Bright text. The select and the textarea are styled identically to the
  input — there is no separate "form widget" look.
- **Focus:** the stroke shifts to Stamp Ink Bright and a 2px ring at 20% opacity
  surrounds it. On the console, focus is the same green border with no ring.
  Placeholder is Faint Pencil; **placeholder is never the label**.
- **Error / Disabled:** invalid fields take a Void Red stroke, with the message below
  in 12px Void Red (Void Red Mist on night) and `aria-invalid` plus
  `aria-describedby` wired to that message. Labels sit above the field at weight 500
  in Board Ink; a hint and an error share the same slot.
- **Checkbox:** 16px square with 4px corners, checked in Stamp Ink.

### Navigation
- **Top bar (portal):** 64px, Ledger Paper, 1px Rule underneath, 24px gutters. The
  section name ("Pengguna") in 14px weight 600 Ledger Ink on the left; on the right,
  either the primary action or a pair of actions, in the order secondary-then-primary.
  A single text link ("Kembali") in Pencil Grey is the only "back" affordance.
- **Sidebar (console):** left column on Night Board with a 1px Night Rule on its right
  edge, full viewport height and sticky. It has two widths the operator toggles with a
  panel button at the top (remembered per browser): **expanded** (256px, icon + label)
  and a **collapsed icon rail** (72px, icon only; the label moves to a tooltip on hover
  and keyboard focus and stays in the accessibility name). Rows are 44px tall with 8px
  corners; idle rows are Faint Pencil, hover fills with `zinc-900` and brightens the
  text; the current page is a `zinc-800` fill with Chalk Bright weight 600 and the icon
  in Stamp Ink Bright. *Langganan* is an expandable group (chevron) whose sub-pages
  are text-only rows under a 1px Night Rule guide; in the rail it is one icon that opens
  its first page. The foot holds the operator's initials avatar, name and email, and an
  icon "Keluar" button.
- **Console icons:** the console (not the school portal) uses a small authored SVG set
  (`Components/icons.tsx`): 24px grid, one 1.75 stroke, round caps, `currentColor`,
  decorative (`aria-hidden`). Icons accompany labels and never replace them except in
  the collapsed rail, where the text remains available as tooltip and accessible name.
- **No breadcrumbs exist in the system, and tabs appear only inside a single record
  (tenant detail).** The portal keeps its top bar and "Kembali" link.

### Beranda blocks (deviation, decided with the dashboard)
The school Beranda is the one portal surface that shows live figures per role, and it
deliberately steps outside the ledger rules above: **figures are cards** (shared `Card`
with its `shadow-sm`), **panels are cards** holding ruled lists, and there is **one bar
list** (shared `BarList`, `chart-1`). Everything else holds: no icons, one primary
filled button per screen, words beside colour, 14px body and 12px meta, 8px corners, no
gradients. A count is plain text, never a badge (a badge is a state word; the amber
outline means pending). Order of a page: the day, the one action, setup, figures, what
needs attention, the rest, then the quiet record. Rows and the action are at least 44px
tall; the figures grid is two columns on a phone and four only on the wide admin
column (`sm`). Blocks load deferred behind a skeleton of the same bands.

### Flash banners
- A 1px-bordered, 8px-cornered strip at 12px × 16px padding, 24px below the page
  title. Success: Stamp Wash ground, Stamp Wash Line border, Stamp Ink Deep text.
  Void: Void Wash ground, Void Wash Line border, Void Red Deep text. Console
  equivalents sit on the night ground at 40% and 20–30% fills with brighter text.
  A banner states a fact that just happened and is not dismissible.

### Record rows (the signature pattern)
- A 12px stack of 8px-cornered cards, each 16px × 20px padded, hairline-bordered,
  whole card clickable. Identity on the left (name in 14px weight 500, then email in
  12px Pencil Grey); status on the right, right-aligned (role chips, then the
  `aktif`/`nonaktif` word, then the slug in monospace on the console). Hover raises
  the border one step (Rule → Rule Strong) and tints the ground one step toward
  `form-grey` — never a shadow.

## Do's and Don'ts

### Do:
- **Do** use the palette's own semantics and always pair color with a word: green is
  confirmed, red is voided, amber is pending — and every chip, banner, and status
  line in the system says the word too (*aktif*, *nonaktif*, *pending*). Never let
  color be the only carrier.
- **Do** keep body text at 14px and page titles at 20px, reaching for 12px meta
  rather than inventing a size.
- **Do** use 8px corners for anything interactive or surface-level, pills only for
  status chips, and 12px only on the raised auth card.
- **Do** separate surfaces with a 1px Rule (paper) or Night Rule (night) before
  considering any shadow.
- **Do** mirror the ground: paper surfaces take Ledger Paper / Form Grey / Rule, the
  console takes Night Board / translucent night panels / Night Rule. Translucency is
  a night-only device.
- **Do** put the label above the field with the error below it, and keep
  `aria-invalid` and `aria-describedby` wired to that error — the existing auth input
  sets this precedent and every new field must match it.
- **Do** write every user-facing string in plain, serviceable Bahasa Indonesia: one
  statement, no marketing register, provider referred to as "tim kami".
- **Do** theme the browser surfaces you own. The system ships no global `::selection`
  or `:focus-visible` treatment, so a new surface sets its own — a selection in
  `stamp-wash` on `ledger-ink`, a 2px `stamp-ink` focus ring at 2px offset — scoped
  under a class the page owns. Making that treatment system-wide is a design-system
  decision and needs sign-off, not a side effect of one page.

### Don't:
- **Don't add a second accent.** There is one green and it means one thing; a new hue
  for a new feature breaks the stamp.
- **Don't use green for a submit on an unauthenticated public surface** — the public
  application form's button is ink.
- **Don't use red to mean "cancel,"** and don't build a hard-delete control: this
  system voids and marks records, it never removes them.
- **Don't introduce gradients, glass, blur, or shadows** into surfaces that are
  currently ruled and flat, and don't lift record rows on hover.
- **Don't ship interactive targets at 16px.** The current checkbox is 16px; that is
  a gap, not a precedent. The primary user is on a low-end phone — touch targets on
  new surfaces must be at least 44px even though the recorded type scale stays at 14px.
- **Don't add a display size, hero type, or marketing headline** anywhere in the
  product, and don't reach for a fourth type size to create hierarchy.
- **Don't replace a word with an icon.** The school portal has no icon vocabulary and
  the console's small set always sits beside a label (the collapsed rail keeps the
  label as tooltip and accessible name).
- **Don't put text in monospace** unless it is a slug, a host, or an identifier a
  human must verify character by character.
- **Don't assume a breakpoint exists.** One is named — `sm` (640px) — and it is the
  only one; new surfaces must introduce responsive behavior deliberately,
  mobile-first, and must not invent a second name.

## Public world — Lembar (landing and sign-in pages)

**Scope.** This section governs **only** these unauthenticated surfaces:

- the public landing page at `/`
  (`modules/Platform/resources/js/Pages/Platform/Landing.tsx`, copy in
  `Components/Landing/content.ts`);
- the school's sign-in pages (`modules/Identity/resources/js/Pages/Identity/Auth/`:
  Login, ForgotPassword, ResetPassword, SetPassword, and ChangePassword, which a
  signed-in account is forced through);
- the PPDB applicant's account pages (`modules/Ppdb/resources/js/Pages/Ppdb/Account/`:
  Login, Register, ForgotPassword, SetPassword, VerifyNotice).

The world lives in Shared: `modules/Shared/resources/js/components/lembar/`
(`lembar.css` with the faces and the scoped tokens, `parts.tsx` with Bubble,
CornerMarks, TimingRail and Wordmark, `fields.tsx` with the form controls, and
`LembarShell.tsx`, the frame of a sign-in page). Everything is scoped under the
`.lembar` class the page root carries. The rules in every section above (the One
Green Rule, three type sizes, no display or hero type, 8px corners, words before
icons, one named breakpoint, the console theme) **do not govern these pages**, and
nothing in this section governs the school portal, the console, the PPDB applicant's
signed-in pages (Join, Form, Home, which keep `PortalPage`) or the school applicant
(pemohon) pages. Lembar tokens carry a `landing-` prefix in the frontmatter so they
can never be mistaken for app tokens.

### Overview

**Creative North Star: "Lembar" (the LJK answer sheet)**

The page is a *Lembar Jawaban Komputer*: the computer-read answer sheet every
Indonesian school has handled. White paper, every rule, label and bubble ring printed
in cobalt drop-out ink, black timing marks down the left edge, and marks filled in 2B
graphite. The sheet is filled once, correctly, which is the job of the teacher this
product serves. Instead of a SaaS hero with a screenshot, the hero is a working
application sheet that fills itself in and stops mid-review, because a person on
"tim kami" approves every school.

**Key Characteristics:**

- Two inks, one paper: cobalt ink prints the form, graphite fills it. No third hue.
- One variable face (Archivo) set condensed for heads and expanded for the wordmark; one mono (Azeret Mono 500) for written digits only.
- 2px corners, 1px ink frames, square corner marks, round bubbles.
- One authored moment: the pencil fill. The timing rail tracks reading progress.
- Flat. No shadow, gradient or blur; the one illustration is the isometric school
  building in the hero.
- Calm, not hard: graphite is a cool slate, heads stop at 700, corner marks and
  filled timing marks are softened, and the closing band is Ink Tint with Deep
  Cobalt type, not a full ink block.
- **No border lines** (decided after review: the lines read too hard). Surfaces
  separate by tone alone: the page is a faint sky-grey paper (`#f4f8fb`), sheets,
  the masthead and the rail are white, header strips, bands, code cells and inputs
  are Ink Tint fills, and table rows alternate white. Wherever the sections below
  still say "frame", "rule" or "hairline", read a change of surface instead. The
  only rings left are the bubbles (they are marks, not borders), the comb's light
  cell dividers, an input's 2px inner graphite stroke on focus, and a 1px red ring
  on a field to correct.

### Colors

Printed in drop-out ink, filled in graphite.

- **Drop-out Cobalt** (`landing-ink`): the app's sky primary (`oklch(0.693 0.150 237)`,
  the blue of `/daftar-sekolah`); the other ink shades share its hue. Only ~2.7:1 on
  paper, so small text never sits in it: numerals, labels and selection use Deep
  Cobalt (5.9:1). The printed form. 1px frames on the masthead
  rule, the application sheet, sheet bands and the ketentuan box; bubble rings; field
  borders; step and principle numerals; the link underline; the full-bleed closing
  band; the scrollbar thumb; the selection fill.
- **Deep Cobalt** (`landing-ink-deep`): ink as *text*, for field labels, table heads,
  band asides, the masthead tagline, and the hover of the graphite button and links.
- **Ink Hairline** (`landing-ink-line`): 1px rules between rows and list items, the
  timing rail's border and its unfilled marks.
- **Ink Tint** (`landing-ink-tint`): the printed panel ground of sheet bands, the
  sheet header strip, the hover of the inverted button; at 60% it marks the one
  human review step in the flow.
- **Graphite** (`landing-graphite`): body text, bubble fills, filled timing marks,
  corner marks, the primary button, and the focus ring.
- **Pencil** (`landing-pencil`): secondary text, leads, nav links at rest.
- **Paper** (`landing-paper`): the page and every framed surface. On the cobalt band,
  text is paper at 90%; underlines are paper at 50%, full on hover.

- **Correction Red** (`landing-correction`): the teacher's red pen. Only on a field
  that needs correcting: its border, and the message under it (with a small alert
  icon and the words). Never a button, never a banner, never on the landing.

**The Two Inks Rule.** Cobalt prints, graphite marks. Anything the form *is* (rules,
labels, rings, frames) is cobalt; anything a person *wrote* (fills, answers, the
action they take) is graphite. A third hue, green included, does not appear; the
red pen marking a field to correct is the one exception.

### Typography

**Display / body:** Archivo variable (400–900, width 62–125%), self-hosted latin
subset `modules/Shared/resources/fonts/archivo-latin-variable.woff2`.
**Code:** Azeret Mono 500, self-hosted `azeret-mono-latin-500.woff2`. Both
`font-display: swap`, SIL OFL. Tabular numerals on the whole page; letter-spacing 0
at the root.

- **Display** (`landing-display`): the hero headline, 2.75rem, 4.25rem at `sm`,
  5.25rem at `xl`; width 72%, `text-balance`. The closing headline uses the same
  voice at 2.5rem / 4rem.
- **Band head** (`landing-band`): section heads inside sheet bands and the ketentuan
  head, 2rem / 2.75rem at `sm`, width 75%.
- **Title** (`landing-title`): module names at 1.5rem, width 85%; flow step titles
  1.25rem at 700, width 85%; principle titles 1.125rem 700.
- **Wordmark** (`landing-wordmark`): "SIMAS" at 800, width 118%, the only expanded
  setting.
- **Lead / body** (`landing-lead`, `landing-body`): leads 1.125rem relaxed, max
  ~34rem; module summaries 1rem; detail lines and notes 0.875rem in Pencil.
- **Action** (`landing-action`): buttons and nav at 15px, 600 (nav 500).
- **Field label** (`landing-field-label`): uppercase, 0.1em tracking, Deep Cobalt.
  Used only where a printed form has a field label: the sheet's legends (Kode
  sekolah, Jenjang, Modul), the table's first column head, and the sheet header strip
  (0.875rem, 0.08em, width 85%).
- **Code** (`landing-code`): written digits in the kode sekolah boxes, flow step
  numbers (1.25rem, cobalt) and principle numbers (1.125rem, cobalt), the numerals of
  "Petunjuk pengisian", and the school code typed into the comb (1.25rem). Never
  prose.
- **Sign-in title:** the one heading of a sign-in page, the band-head voice at
  2.25rem, 3rem at `sm`, 3.5rem at `lg`, width 75%.
- **Field text:** 16px Archivo in every input (16px also keeps phone browsers from
  zooming on focus).

**The Width Axis Rule.** Hierarchy comes from Archivo's width axis as much as size:
condensed (72–85%) for heads, normal for reading, expanded (118%) only for the
wordmark.

**The Written Digit Rule.** Mono means a digit or code a hand writes on the sheet,
one character per box. It is not a code font for labels or decoration.

### Layout

- **Frame:** a fixed timing rail on the left (28px; 48px at `sm`), the page offset by
  the same amount. Content in a `max-w-7xl` (80rem) column with 20px sides (32px at
  `sm`).
- **Hero:** single column on mobile; at `lg` a 12-column grid, headline 6/12,
  application sheet 6/12. Top padding 48px, 80px at `sm`.
- **Sections:** each opens with a full-width sheet band, then its content; 96px
  between sections (128px at `sm`). The modules list is one column, two at `lg` with
  a hairline between; the flow is stacked rows, five columns at `lg`; ketentuan is a
  4/8 split at `lg`.
- **Breakpoints used:** `sm` (640px) for rail width, side padding, type steps and
  row/column flips; `md` (768px) only to reveal the masthead tagline; `lg` (1024px)
  for the 12-column and multi-column grids; `xl` (1280px) only for the largest hero
  headline step.
- **Sign-in pages:** the same rail and a 64px masthead; content in a `max-w-6xl`
  column. At `lg` a 12-column grid: title, lead and "Petunjuk pengisian" in 5/12,
  the sheet in 6/12 from column 7. On a phone: title, sheet, then the petunjuk, so
  the form comes first.
- **Targets:** every link and toggle is at least 44px tall; buttons and inputs are
  48px.

### Elevation & Depth

Flat. Depth is printed: 1px cobalt frames, tinted band grounds, and the tinted
closing band. There is no `box-shadow` on the page; the only "lift" is the 1px press
of a button on `:active`.

### Shapes

- **Corners:** 2px (`landing-sheet`) on buttons; frames are square-cornered.
- **Frames:** 1px cobalt for the things that are a sheet (masthead underline,
  application sheet, sheet bands top and bottom, roles table head, ketentuan box);
  1px cobalt for fields inside the sheet; 1px Ink Hairline between rows.
- **Corner marks:** four 10px graphite squares inset 8px in the corners of the
  application sheet and the ketentuan box, and in paper on the closing band. They
  are the scanner's registration marks; they belong only on a framed sheet or band.
- **Bubbles:** circles with a 1px cobalt ring, 20px (16px in the code columns), with
  a printed numeral at 8–9px inside the code columns. Filled = a graphite disc
  covering the ring. Unfilled 8px rings are the bullets of module detail lists.

### Components

- **Masthead:** 64px (80px at `sm`), 1px cobalt underline. Wordmark, tagline in
  field-label caps from `md`, nav (Calon siswa, Masuk) in Pencil, and the graphite
  "Daftarkan sekolah" from `sm`.
- **Timing rail:** 28 marks, 6px tall, spaced down the full height. Unfilled marks
  are short Ink Hairline bars; marks up to the reading position are longer graphite
  bars (200ms width/colour transition). `aria-hidden`.
- **School building (hero, signature; replaced the application sheet on request,
  styled after the isometric line drawings of the Laravel homepage):**
  `Components/Landing/SchoolBuilding.tsx`, an isometric two-storey school drawn as
  SVG polygons with a soft slate outline (`#27313c` at 50%, 1px non-scaling; the one
  place on Lembar pages where edges are drawn): a slate plinth ("Ruang kerja per
  sekolah"), white walls with windows, columns, an upper corridor rail, a sky fascia
  carrying "SIMAS", a pitched slate-blue roof, the merah-putih flag, a faint
  isometric grid fading out (a mask, not a visible gradient), three lines carrying
  the example school codes (`123456`, `sekolah-a`, `sekolah-b`) to the building, and
  the four default roles as floor tiles. Each room is a module (ground floor
  Absensi, PPDB, Data induk; upper floor Akademik, Statistik & laporan, WhatsApp
  sekolah): switching it on lights its windows and fills its bubble, which sits in
  the middle of the room as an HTML overlay (names live in the toggle list under the
  drawing). The hero grid is 5/7 at `lg` to give the drawing room. The SVG is
  `aria-hidden`; an `sr-only` line names the modules that are on.
- **Bubble:** the one mark. Ring = an answer that exists; graphite fill = chosen.
  Used for answers, toggles, status and role grants. Never decorative.
- **Primary button:** graphite fill, paper text, 48px, 2px corners; hover Deep
  Cobalt; 1px press. There is no inverted variant any more.
- **Quiet link:** graphite text with a 2px cobalt underline at 6px offset and a
  trailing arrow beside the label (the only icon on the page); hover Deep Cobalt.
- **Sheet band:** full-bleed Ink Tint with 1px cobalt rules top and bottom; condensed
  band head left, Deep Cobalt aside right from `sm`.
- **Roles bubble table:** a real `table`; a 1px cobalt rule under the head; one row
  per task, one column per default role, a filled or empty bubble per cell with
  `sr-only` "Ya"/"Tidak".
- **Ketentuan box:** a 2px-framed, corner-marked ordered list of standing rules,
  cobalt mono numerals, hairlines between items.
- **Close band:** full-bleed Ink Tint between 1px cobalt rules, softened corner
  marks, the condensed headline in Deep Cobalt at 700, the graphite button and
  quiet underlined tertiary links. (It was a full cobalt block; softened on request
  because the page read too hard.)
- **Sign-in sheet (`LembarShell`):** masthead with the wordmark (link to `/`) and the
  door in caps ("Portal sekolah", "PPDB · Calon siswa"); one 2px-framed,
  corner-marked sheet with an Ink Tint header strip naming the sheet ("Lembar masuk
  sekolah") and, beside it, the school's name when one is known; the flash `status`
  as a notice inside the sheet; links to other pages under the sheet; the numbered
  "Petunjuk pengisian" (short, true lines only).
- **Field (`LembarInput`):** caps field label in Deep Cobalt above; a 48px box, 1px
  cobalt, 2px corners; hint in Pencil below, or the correction in red. Focus is a 2px
  graphite stroke inside the box (no detached ring). A password box carries a text
  switch "Lihat"/"Tutup" (`aria-pressed`) inside its right edge.
- **Comb (`comb`):** the school code box: 1px Ink Hairline dividers make one cell per
  character (cell = 1ch + 0.85ch tracking of Azeret Mono), so a code is written one
  character per box, digits or slug alike.
- **Check (`LembarCheck`):** a real checkbox drawn as a 20px bubble; checked fills in
  pencil. 44px row.
- **Notice:** 1px cobalt frame on Ink Tint, graphite text, `role="status"`.

### Motion

- **The pencil fill** is the page's one authored moment: each filled bubble scales
  from 0.15 to 1 and fades in over 280ms `cubic-bezier(0.16, 1, 0.3, 1)`, staggered
  by a per-mark `--delay` so the sheet fills digit by digit, then jenjang, modules,
  status. It runs on load (and when a module bubble is toggled on).
- **Timing rail:** 200ms colour and width transitions as reading progresses. On a
  sign-in page the rail is the form's progress instead: marks fill as the required
  boxes are filled in.
- **Everything else:** colour transitions on hover, a 2px nudge of the link arrow,
  a 1px button press.
- **Reduced motion:** `prefers-reduced-motion: reduce` removes the pencil fill;
  bubbles appear already filled.

**The One Pencil Rule.** One authored motion per page, and it is the act of filling
the sheet. No scroll reveals, parallax or looping animation.

### Browser surfaces

Scoped to `.lembar`: selection is cobalt with paper text; `:focus-visible` is a 2px
graphite outline at 3px offset (inputs take the inner graphite stroke instead);
`scrollbar-color` is cobalt on paper; the caret is graphite.

### Do's and Don'ts

#### Do:
- **Do** keep Lembar scoped under `.lembar` and its tokens prefixed `landing-`;
  build new sign-in pages from `LembarShell` and the shared fields, never by hand.
- **Do** make every new block a printed part of the sheet: a cobalt frame or band, a
  field label, bubbles for choices, graphite for what is filled.
- **Do** keep proof real: the modules that exist, the actual onboarding steps, the
  default roles from Identity's `roles.php`, trial days from config. Example data is
  labelled "Contoh pengisian".
- **Do** give every `aria-hidden` bubble grid an `sr-only` sentence with its value.

#### Don't:
- **Don't** bring Lembar into the app (no cobalt, Archivo or 2px frames in the
  portal, the console or signed-in forms), and don't bring app tokens (Stamp Ink
  green, Instrument Sans, Geist, shadows, shadcn components) onto Lembar pages.
- **Don't** add a third hue, a gradient, a shadow, a screenshot or a feature-card grid.
- **Don't** use a full-size bubble as decoration (the 8px ring bullets of detail
  lists are the one ornamental use), or corner marks on anything that is not a
  framed sheet or band.
- **Don't** set mono for anything but a written digit or a numeral.
- **Don't** set readable text below 12px; the 8–9px numerals exist only inside
  `aria-hidden` bubbles as print texture.
- **Don't** add customers, figures, testimonials or prices; there are none.
