---
paths:
  - 'modules/**/resources/js/**'
---

# Js

## Base UI components come from modules/Shared (shadcn) — never hand-roll them
modules/Shared/resources/js/components/ui is the single source of truth for base components (button, input, select, checkbox, dialog, alert-dialog, badge, card, table, tabs, sidebar, field, alert, tooltip, pagination, etc.). They are shadcn/ui (radix, style radix-nova); components.json aliases point to @shared/*. Add more with `npx shadcn@latest add <name>` (it installs into Shared; fix any `from "cn"` import to `@shared/lib/utils`, delete any stray pnpm-lock.yaml / "cn" package). Before writing any UI, check Shared/ui for an existing component and use it, including badges, dialogs and form controls. Never recreate a base component inside a module. Only when no shared component fits, build a custom component inside that module (e.g. modules/Platform/resources/js/Components/ConsoleParts.tsx) composed FROM the shared primitives. Use semantic tokens (bg-primary, text-muted-foreground), the shared Field/FieldGroup for forms, lucide-react for icons. Overlays portal to <body>, so theme tokens live on the root element (the console host adds `console-theme` to <html>).

## UX: modal untuk perpanjangan halaman, halaman detail hanya bila punya URL sendiri
Tambah, ubah, atau aksi yang hanya memperluas halaman yang sudah ada = modal (FormDialog di Core, FormModal di Ppdb); pengguna tetap di halaman itu setelah simpan.
Halaman detail (Show.tsx + route show) hanya bila entitas punya data turunan atau beberapa bagian, perlu URL yang ditautkan, atau form panjang (produk dipakai di HP). Entitas biasanya keduanya: daftar -> detail, dan edit tetap modal (Students/Show.tsx).
Hindari: modal di dalam modal, modal bertab/berlangkah atau lebih dari sekitar 10 field, halaman penuh untuk edit 2-4 field.
Deteksi: Dialog/FormDialog/FormModal/AlertDialog bersarang dalam satu berkas; hitung field di FormRequest atau komponen form; Show.tsx tanpa data turunan.

## UX: jangan banyak input terlihat sekaligus di satu halaman
Halaman dibaca dulu sebagai ringkasan; form dibuka saat diminta (modal), atau dipecah per langkah bila alurnya berurutan. Patokan awal: sekitar 6 input terlihat tanpa interaksi (di luar dialog); sesuaikan bila terasa keliru.
Editor daftar baris (mis. jalur dan kuota) pindah ke modal; di halaman tampil sebagai tabel baca-saja. Keadaan kosong menawarkan satu langkah pertama saja.
Contoh: Ppdb/Settings.tsx (ringkasan + FormModal).
Deteksi: hitung Input/OptionSelect/Textarea yang selalu tampil dalam satu halaman, di luar Dialog/Sheet; form inline permanen dengan lebih dari patokan.
