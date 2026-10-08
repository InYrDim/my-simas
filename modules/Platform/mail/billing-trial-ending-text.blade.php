Halo {{ $recipientName }},

Masa uji coba {{ $schoolName }} di SIMAS berakhir pada {{ $dueOn }}.

Agar sekolah tetap bisa memakai SIMAS tanpa terputus, mohon aktifkan
langganan sebelum tanggal itu. Kami akan menerbitkan invoice setelah
paket dan siklus pembayaran dipilih.
@if ($issuerContact)

Untuk mengaktifkan langganan atau bertanya, hubungi {{ $issuerContact }}.
@endif

— {{ $issuerName }}
