@php
    $rupiah = fn (int $amount): string => 'Rp '.number_format($amount, 0, ',', '.');
    $date = fn ($value): string => $value->format('d/m/Y');
    $cycle = $invoice->billing_cycle->value === 'yearly' ? 'Tahunan' : 'Bulanan';
    $stampColor = match ($invoice->status->value) {
        'paid' => '#15803d',
        'void' => '#6b7280',
        default => '#b45309',
    };
    $bank = (array) ($issuer['bank'] ?? []);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111827; }
        h1 { font-size: 20px; margin: 0 0 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 6px 8px; text-align: left; vertical-align: top; }
        .muted { color: #6b7280; }
        .right { text-align: right; }
        .items th { border-bottom: 1px solid #111827; font-size: 10px; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #e5e7eb; }
        .total td { border-top: 2px solid #111827; font-weight: bold; font-size: 13px; }
        .stamp { display: inline-block; border: 2px solid {{ $stampColor }}; color: {{ $stampColor }}; padding: 4px 12px; font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .box { border: 1px solid #d1d5db; padding: 10px 12px; margin-top: 20px; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td>
                <h1>{{ $invoice->status->value === 'paid' ? 'Kuitansi' : 'Invoice' }}</h1>
                <div class="muted">{{ $invoice->number }}</div>
            </td>
            <td class="right"><span class="stamp">{{ $stamp }}</span></td>
        </tr>
    </table>

    <table style="margin-top: 16px;">
        <tr>
            <td style="width: 50%;">
                <strong>{{ $issuer['name'] ?? 'SIMAS' }}</strong><br>
                @if (! empty($issuer['address'])){{ $issuer['address'] }}<br>@endif
                @if (! empty($issuer['email'])){{ $issuer['email'] }}<br>@endif
                @if (! empty($issuer['whatsapp']))WhatsApp {{ $issuer['whatsapp'] }}<br>@endif
                @if (! empty($issuer['phone'])){{ $issuer['phone'] }}@endif
            </td>
            <td>
                <span class="muted">Ditagihkan kepada</span><br>
                <strong>{{ $schoolName }}</strong><br>
                @if ($contactName){{ $contactName }}<br>@endif
                @if ($contactEmail){{ $contactEmail }}@endif
            </td>
        </tr>
    </table>

    <table style="margin-top: 16px;">
        <tr>
            <td><span class="muted">Tanggal terbit</span><br>{{ $date($invoice->issued_at) }}</td>
            <td><span class="muted">Jatuh tempo</span><br>{{ $date($invoice->due_at) }}</td>
            <td><span class="muted">Periode</span><br>{{ $date($invoice->period_start) }} - {{ $date($invoice->period_end) }}</td>
            @if ($invoice->paid_at)
                <td><span class="muted">Dibayar</span><br>{{ $date($invoice->paid_at) }}</td>
            @endif
        </tr>
    </table>

    <table class="items" style="margin-top: 20px;">
        <thead>
            <tr>
                <th>Item</th>
                <th>Siklus</th>
                <th class="right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Langganan SIMAS - paket {{ $invoice->plan_name }}</td>
                <td>{{ $cycle }}</td>
                <td class="right">{{ $rupiah($invoice->amount) }}</td>
            </tr>
        </tbody>
        <tfoot>
            <tr class="total">
                <td colspan="2">Total (tanpa pajak)</td>
                <td class="right">{{ $rupiah($invoice->amount) }}</td>
            </tr>
        </tfoot>
    </table>

    @if ($invoice->status->value === 'unpaid')
        <div class="box">
            <strong>Cara pembayaran</strong><br>
            Transfer sesuai nominal {{ $rupiah($invoice->amount) }} dan cantumkan nomor invoice
            <strong>{{ $invoice->number }}</strong> pada berita transfer.<br>
            @if (! empty($bank['name']) || ! empty($bank['account']))
                <br>
                {{ $bank['name'] ?? '' }} {{ $bank['account'] ?? '' }}<br>
                @if (! empty($bank['holder']))a.n. {{ $bank['holder'] }}@endif
            @endif
        </div>
    @endif
</body>
</html>
