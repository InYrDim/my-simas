Halo {{ $recipientName }},

Pengajuan {{ $schoolName }} sudah disetujui. Sekolah Anda siap dipakai.

Kode sekolah: {{ $schoolCode }}
@if ($planName !== null)
Paket: {{ $planName }}
@endif
@if ($trialEndsOn !== null)
Masa trial berakhir: {{ $trialEndsOn }}
@endif

@if ($passwordReady)
Masuk dengan email dan kata sandi yang Anda pakai saat mendaftar di:
{{ $loginUrl }}

Anda akan langsung masuk ke sekolah Anda. Pengguna lain di sekolah masuk
lewat halaman login sekolah dengan kode sekolah di atas.
@else
Anda akan menerima email terpisah berisi tautan untuk membuat kata sandi
akun admin sekolah. Setelah itu, masuk dengan kode sekolah di atas.
@endif

— SIMAS
