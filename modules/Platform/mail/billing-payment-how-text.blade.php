Cara membayar: transfer sesuai nominal {{ $amount }} dan cantumkan nomor
invoice {{ $invoiceNumber }} pada berita transfer.
@if (! empty($bank['name']) || ! empty($bank['account']))
{{ $bank['name'] ?? '' }} {{ $bank['account'] ?? '' }}@if (! empty($bank['holder'])) a.n. {{ $bank['holder'] }}@endif

@endif
