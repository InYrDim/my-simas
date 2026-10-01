Halo {{ $recipientName }},

Pengajuan {{ $schoolName }} sudah disetujui. Sekolah Anda siap dipakai.

Kode sekolah: {{ $schoolCode }}
@if ($planName !== null)
Paket: {{ $planName }}
@endif
@if ($trialEndsOn !== null)
Masa trial berakhir: {{ $trialEndsOn }}
@endif

Anda akan menerima email terpisah berisi tautan untuk membuat kata sandi
akun admin sekolah. Setelah itu, masuk dengan kode sekolah di atas.

Status pengajuan tetap bisa dilihat di:
{{ $loginUrl }}

— SIMAS
