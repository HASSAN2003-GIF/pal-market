@php
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->translatedFormat('j F Y') : '—';
    $money = fn ($value) => number_format((float) $value, 2);
    $order = $doc['order'];
    $currency = $order['currency'] ?? 'TZS';
    $sign = $doc['signatories'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
<meta charset="utf-8">
<title>{{ __('documents.title') }} {{ $order['number'] }}</title>
<style>
    @page { margin: 22mm 16mm 22mm 16mm; }
    * { box-sizing: border-box; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1C2228; line-height: 1.35; }
    h1 { font-size: 18pt; margin: 0; letter-spacing: 0.5px; }
    h2 { font-size: 10pt; text-transform: uppercase; letter-spacing: 0.8px; color: #47515C; margin: 16px 0 6px; border-bottom: 1px solid #D5DAE0; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; }
    .muted { color: #5B6570; }
    .small { font-size: 8pt; }
    .right { text-align: right; }
    .header td { vertical-align: top; }
    .logo { height: 34px; }
    .doc-no { font-size: 11pt; font-weight: bold; }
    .meta td { padding: 2px 0; vertical-align: top; }
    .meta td.label { width: 44%; color: #5B6570; }
    .parties td.party { width: 50%; vertical-align: top; padding: 8px 10px; border: 1px solid #D5DAE0; }
    .parties .name { font-weight: bold; font-size: 10.5pt; margin-bottom: 3px; }
    .items thead { display: table-header-group; }
    .items th { background: #1C2228; color: #FFFFFF; font-size: 8.5pt; text-align: left; padding: 5px 6px; }
    .items td { padding: 5px 6px; border-bottom: 1px solid #E3E7EB; vertical-align: top; }
    .items tr { page-break-inside: avoid; }
    .items .num { text-align: right; white-space: nowrap; }
    .totals { width: 46%; margin-left: 54%; margin-top: 8px; }
    .totals td { padding: 3px 6px; }
    .totals .grand td { font-weight: bold; font-size: 11pt; border-top: 2px solid #1C2228; padding-top: 6px; }
    .box { border: 1px solid #D5DAE0; padding: 8px 10px; }
    .terms li { margin-bottom: 3px; }
    .approvals, .keep { page-break-inside: avoid; }
    .approvals td.cell { width: 36%; vertical-align: top; border: 1px solid #9AA4AE; padding: 8px 10px; }
    .approvals td.stamp { width: 28%; vertical-align: top; border: 1px dashed #9AA4AE; padding: 8px 10px; height: 150px; }
    .line { border-bottom: 1px solid #47515C; height: 22px; margin-bottom: 2px; }
    .filled { height: auto; padding-top: 6px; font-size: 9.5pt; }
    .cap { font-size: 7.5pt; color: #5B6570; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
    .cancelled { border: 2px solid #B42318; color: #B42318; padding: 8px 10px; font-weight: bold; margin: 10px 0; }
    .footer { position: fixed; bottom: -14mm; left: 0; right: 38mm; font-size: 7.5pt; color: #5B6570; }
    .dot { color: #1C73C1; }
</style>
</head>
<body>

<div class="footer">{{ __('documents.generated', ['date' => $date($doc['issued_at'] ?? null), 'reference' => $reference, 'fingerprint' => $fingerprint]) }}</div>

<table class="header">
    <tr>
        <td>
            @if ($logo)
                <img class="logo" src="{{ $logo }}" alt="PAL Market">
            @else
                <h1>PAL<span class="dot">.</span></h1>
            @endif
            <div class="muted small" style="margin-top:4px">PAL Market</div>
        </td>
        <td class="right">
            <h1>{{ __('documents.title') }}</h1>
            <div class="doc-no">{{ $order['number'] }}</div>
            <div class="muted small">{{ __('documents.version', ['version' => $version]) }}</div>
        </td>
    </tr>
</table>

@if ($cancelled)
    <div class="cancelled">{{ mb_strtoupper(__('documents.cancelled')) }} — {{ __('documents.cancelled_note') }}</div>
@endif

<table style="margin-top:14px">
    <tr>
        <td style="width:50%; vertical-align:top; padding-right:12px">
            <table class="meta">
                <tr><td class="label">{{ __('documents.order_number') }}</td><td>{{ $order['number'] }}</td></tr>
                <tr><td class="label">{{ __('documents.document_reference') }}</td><td>{{ $reference }}</td></tr>
                <tr><td class="label">{{ __('documents.status') }}</td><td>{{ __('documents.statuses.'.$order['status']) }}</td></tr>
                <tr><td class="label">{{ __('documents.quotation') }}</td><td>{{ $doc['quotation']['number'] ?? '—' }}</td></tr>
                <tr><td class="label">{{ __('documents.request') }}</td><td>{{ $doc['request']['number'] ?? '—' }}</td></tr>
            </table>
        </td>
        <td style="width:50%; vertical-align:top">
            <table class="meta">
                <tr><td class="label">{{ __('documents.order_date') }}</td><td>{{ $date($order['created_at']) }}</td></tr>
                <tr><td class="label">{{ __('documents.confirmed_date') }}</td><td>{{ $date($order['confirmed_at']) }}</td></tr>
                <tr><td class="label">{{ __('documents.issued') }}</td><td>{{ $date($doc['issued_at'] ?? null) }}</td></tr>
                <tr><td class="label">{{ __('documents.needed_by') }}</td><td>{{ $date($doc['request']['needed_by'] ?? null) }}</td></tr>
            </table>
        </td>
    </tr>
</table>

<table class="parties" style="margin-top:12px">
    <tr>
        @foreach (['buyer' => $doc['buyer'], 'supplier' => $doc['supplier']] as $role => $party)
            <td class="party">
                <div class="cap">{{ __('documents.'.$role) }}</div>
                <div class="name">{{ $party['business_name'] ?? '—' }}</div>
                <div>{{ __('documents.tin') }}: {{ $party['tin'] ?: __('documents.not_given') }}</div>
                <div>{{ __('documents.contact') }}: {{ $party['contact_name'] ?? '—' }}</div>
                @if (! empty($party['phone']))<div>{{ $party['phone'] }}</div>@endif
                @if (! empty($party['email']))<div>{{ $party['email'] }}</div>@endif
                @if ($role === 'supplier' && ! empty($party['address']))<div>{{ __('documents.address') }}: {{ $party['address'] }}</div>@endif
                @if ($role === 'buyer')
                    <div style="margin-top:4px">{{ __('documents.deliver_to') }}:
                        {{ collect([$doc['request']['delivery_address'] ?? null, $doc['request']['delivery_region'] ?? null])->filter()->implode(', ') ?: __('documents.not_given') }}
                    </div>
                @endif
            </td>
        @endforeach
    </tr>
</table>

<h2>{{ __('documents.items') }}</h2>
<table class="items">
    <thead>
        <tr>
            <th style="width:6%">{{ __('documents.col_no') }}</th>
            <th>{{ __('documents.col_item') }}</th>
            <th class="num" style="width:9%">{{ __('documents.col_qty') }}</th>
            <th style="width:15%">{{ __('documents.col_unit') }}</th>
            <th class="num" style="width:15%">{{ __('documents.col_unit_price') }}</th>
            <th class="num" style="width:16%">{{ __('documents.col_amount') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($doc['items'] as $item)
            <tr>
                <td>{{ $item['no'] }}</td>
                <td>
                    <strong>{{ $item['name'] }}</strong>
                    @if (! empty($item['description']))<div class="muted small">{{ $item['description'] }}</div>@endif
                </td>
                <td class="num">{{ number_format($item['quantity']) }}</td>
                <td>{{ $item['unit'] }}</td>
                <td class="num">{{ $money($item['unit_price']) }}</td>
                <td class="num">{{ $money($item['total']) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="totals">
    <tr><td>{{ __('documents.subtotal') }}</td><td class="right">{{ $currency }} {{ $money($order['subtotal']) }}</td></tr>
    <tr><td>{{ __('documents.delivery') }}</td><td class="right">{{ $currency }} {{ $money($order['delivery_fee']) }}</td></tr>
    <tr class="grand"><td>{{ __('documents.total') }}</td><td class="right">{{ $currency }} {{ $money($order['total']) }}</td></tr>
</table>
<p class="muted small right">{{ __('documents.amounts_in', ['currency' => $currency]) }}</p>

@if (! empty($order['notes']))
    <h2>{{ __('documents.notes') }}</h2>
    <div class="box">{!! nl2br(e($order['notes'])) !!}</div>
@endif

<div class="keep">
    <h2>{{ __('documents.terms_title') }}</h2>
    <ul class="terms small">
        @foreach (__('documents.terms') as $term)
            <li>{{ $term }}</li>
        @endforeach
    </ul>
</div>

<div class="approvals">
    <h2>{{ __('documents.approvals') }}</h2>
    <table>
        <tr>
            @foreach (['engineer', 'supervisor'] as $who)
                <td class="cell">
                    <div class="cap">{{ __('documents.'.$who) }}</div>
                    <div class="small muted">{{ __('documents.name') }}</div>
                    <div class="line {{ ! empty($sign[$who.'_name']) ? 'filled' : '' }}">{{ $sign[$who.'_name'] ?? '' }}</div>
                    <div class="small muted">{{ __('documents.designation') }}</div>
                    <div class="line {{ ! empty($sign[$who.'_designation']) ? 'filled' : '' }}">{{ $sign[$who.'_designation'] ?? '' }}</div>
                    <div class="small muted">{{ __('documents.signature') }}</div>
                    <div class="line" style="height:30px"></div>
                    <div class="small muted">{{ __('documents.date') }}</div>
                    <div class="line"></div>
                </td>
            @endforeach
            <td class="stamp">
                <div class="cap">{{ __('documents.stamp') }}</div>
            </td>
        </tr>
    </table>
</div>

</body>
</html>
