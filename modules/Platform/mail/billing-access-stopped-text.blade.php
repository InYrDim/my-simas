Halo {{ $recipientName }},

Akses {{ $schoolName }} ke SIMAS dihentikan karena tagihan belum dibayar
sampai masa tenggang berakhir.
@if ($invoiceNumber)

Invoice: {{ $invoiceNumber }}
Jumlah: {{ $amount }}

@include('Platform::billing-payment-how-text')
@endif
Data sekolah tidak dihapus. Akses dibuka kembali otomatis setelah
pembayaran kami konfirmasi.
@if ($issuerContact)

Pertanyaan? Hubungi {{ $issuerContact }}.
@endif

— {{ $issuerName }}
