Halo {{ $recipientName }},

Invoice langganan SIMAS untuk {{ $schoolName }} sudah terbit.

Nomor invoice: {{ $invoiceNumber }}
Periode: {{ $periodLabel }}
Jumlah: {{ $amount }}
Jatuh tempo: {{ $dueOn }}

@include('Platform::billing-payment-how-text')
Invoice terlampir dalam bentuk PDF. Setelah pembayaran kami konfirmasi,
Anda akan menerima kuitansi.
@if ($issuerContact)

Pertanyaan? Hubungi {{ $issuerContact }}.
@endif

— {{ $issuerName }}
