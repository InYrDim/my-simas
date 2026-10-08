Halo {{ $recipientName }},

Invoice {{ $invoiceNumber }} untuk {{ $schoolName }} sudah lewat jatuh tempo
({{ $dueOn }}) dan belum kami terima pembayarannya.

Jumlah: {{ $amount }}
@if ($graceEndsOn)

Sekolah masih dapat dipakai sampai {{ $graceEndsOn }}. Setelah tanggal itu
akses sekolah dihentikan sampai pembayaran diterima.
@endif

@include('Platform::billing-payment-how-text')
Abaikan pesan ini bila Anda sudah membayar.
@if ($issuerContact)

Pertanyaan? Hubungi {{ $issuerContact }}.
@endif

— {{ $issuerName }}
