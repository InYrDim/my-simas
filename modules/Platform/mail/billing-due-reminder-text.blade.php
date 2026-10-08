Halo {{ $recipientName }},

Pengingat: invoice {{ $invoiceNumber }} untuk {{ $schoolName }} akan jatuh
tempo pada {{ $dueOn }}.

Jumlah: {{ $amount }}
Periode: {{ $periodLabel }}

@include('Platform::billing-payment-how-text')
Abaikan pesan ini bila Anda sudah membayar.
@if ($issuerContact)

Pertanyaan? Hubungi {{ $issuerContact }}.
@endif

— {{ $issuerName }}
