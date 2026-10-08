Halo {{ $recipientName }},

Terima kasih. Pembayaran invoice {{ $invoiceNumber }} untuk {{ $schoolName }}
sudah kami terima.

Jumlah: {{ $amount }}
Periode langganan: {{ $periodLabel }}

Kuitansi terlampir dalam bentuk PDF.
@if ($issuerContact)

Pertanyaan? Hubungi {{ $issuerContact }}.
@endif

— {{ $issuerName }}
