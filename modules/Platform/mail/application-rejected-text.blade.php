Halo {{ $recipientName }},

Pengajuan {{ $schoolName }} belum bisa kami setujui.
@if ($note !== null && $note !== '')

Catatan dari tim kami:
{{ $note }}
@endif

Anda bisa memperbaiki data sekolah lalu mengajukannya lagi di:
{{ $onboardingUrl }}

— SIMAS
