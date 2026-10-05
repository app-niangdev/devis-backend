@php
    $money = fn ($v) => number_format((int) $v, 0, ',', ' ') . ' FCFA';
    $qty = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, ',', ' '), '0'), ',');
    $primary = $tenant->primary_color ?: '#1D4ED8';
    $secondary = $tenant->secondary_color ?: '#0F172A';
    $accent = $tenant->accent_color ?: '#F59E0B';
    $accepted = $quote->status === \App\Models\Quote::ACCEPTED;
    $supplies = $quote->items->where('kind', \App\Models\QuoteItem::SUPPLY);
    $labor = $quote->items->where('kind', \App\Models\QuoteItem::LABOR);
    $sections = array_filter([
        'Fournitures' => $supplies,
        "Main-d'œuvre" => $labor,
    ], fn ($items) => $items->isNotEmpty());
    $showSections = count($sections) > 1;
    $customerPhone = \App\Support\SenegalPhone::format($quote->customer?->phone);
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>Devis {{ $quote->quote_number }}</title>
    <style>
        @page { margin: 14mm 14mm 16mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #1a1f35; margin: 0; }
        table { border-collapse: collapse; }
        .head { width: 100%; border-bottom: 2.5px solid {{ $primary }}; padding-bottom: 4mm; }
        .head td { vertical-align: top; padding: 0; }
        .logo { width: 20mm; height: 20mm; }
        .company { font-size: 13pt; font-weight: bold; color: {{ $secondary }}; }
        .issuer span, .issuer em { display: block; font-size: 8pt; color: #5b6075; margin-top: .6mm; }
        .title { text-align: right; }
        .title h1 { margin: 0 0 2mm; font-size: 18pt; color: {{ $primary }}; text-transform: uppercase; letter-spacing: 1px; }
        .title table { margin-left: auto; font-size: 8.5pt; }
        .title td { padding: .4mm 0 .4mm 3mm; }
        .title td.l { color: #5b6075; text-align: right; }
        .customer { margin: 6mm 0 5mm; padding: 3mm 4mm; background: #f5f6fa; border-left: 3px solid {{ $primary }}; }
        .label { display: block; font-size: 7pt; color: #5b6075; text-transform: uppercase; letter-spacing: .4px; margin-bottom: 1mm; }
        .customer strong { font-size: 10.5pt; }
        .object { margin: 0 0 4mm; font-size: 10pt; }
        .accepted { margin: 0 0 4mm; padding: 2mm; text-align: center; font-weight: bold; color: #15803d; border: 1.5px solid #15803d; letter-spacing: 1px; }
        table.lines { width: 100%; }
        table.lines th { background: {{ $secondary }}; color: #fff; font-size: 7.5pt; text-transform: uppercase; letter-spacing: .3px; text-align: left; padding: 2mm; }
        table.lines th.num { text-align: right; }
        table.lines td { padding: 2mm; border-bottom: 1px solid #e8e9f0; vertical-align: top; }
        table.lines tr.section td { background: #f5f6fa; font-weight: bold; color: {{ $secondary }}; font-size: 8pt; text-transform: uppercase; letter-spacing: .3px; }
        table.lines tr.subtotal td { font-weight: bold; border-bottom: 1.5px solid #d6d8e2; }
        .num { text-align: right; white-space: nowrap; }
        table.totals { width: 60%; margin: 4mm 0 0 auto; }
        table.totals td { padding: 1.6mm 2mm; }
        table.totals td.num { font-weight: bold; }
        table.totals tr.total td { border-top: 2px solid {{ $secondary }}; font-size: 11.5pt; font-weight: bold; color: {{ $secondary }}; }
        table.totals tr.deposit td { background: {{ $accent }}; color: #111; font-weight: bold; }
        table.totals tr.balance td { color: #5b6075; }
        .received { margin-top: 2mm; text-align: right; font-size: 8pt; color: #15803d; }
        .notes { margin-top: 6mm; padding: 3mm 4mm; border: 1px solid #e8e9f0; }
        .notes p { margin: 0; white-space: pre-line; }
        .disclaimer { margin-top: 6mm; font-size: 8pt; color: #5b6075; font-style: italic; }
        .stamp { width: 100%; margin-top: 4mm; page-break-inside: avoid; }
        .stamp td { padding: 0; }
        .stamp .cell { width: 46mm; text-align: center; }
        .stamp img { width: 40mm; height: 40mm; margin-top: 1mm; }
        .foot { margin-top: 10mm; padding-top: 3mm; border-top: 1px solid #e8e9f0; text-align: center; font-size: 8pt; color: #5b6075; }
        .legal { margin-top: 1mm; font-size: 7pt; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            @if ($logo)
                <td style="width: 24mm"><img class="logo" src="{{ $logo }}" alt=""></td>
            @endif
            <td class="issuer">
                <div class="company">{{ $tenant->name }}</div>
                @if ($tenant->trade)<span>{{ $tenant->trade }}</span>@endif
                @if ($tenant->slogan)<em>{{ $tenant->slogan }}</em>@endif
                @if ($tenant->address)<span>{{ $tenant->address }}</span>@endif
                @if ($phones)<span>Tél. {{ implode(' / ', $phones) }}</span>@endif
                @if ($tenant->email)<span>{{ $tenant->email }}</span>@endif
            </td>
            <td class="title">
                <h1>Devis</h1>
                <table>
                    <tr><td class="l">N°</td><td>{{ $quote->quote_number }}</td></tr>
                    <tr><td class="l">Date</td><td>{{ $quote->created_at?->format('d/m/Y') }}</td></tr>
                    @if ($quote->valid_until)<tr><td class="l">Valable jusqu'au</td><td>{{ $quote->valid_until->format('d/m/Y') }}</td></tr>@endif
                </table>
            </td>
        </tr>
    </table>

    <div class="customer">
        <span class="label">Devis établi pour</span>
        <strong>{{ $quote->customer?->name ?? 'Client' }}</strong>
        @if ($customerPhone)<br><span>Tél. {{ $customerPhone }}</span>@endif
        @if ($quote->customer?->address)<br><span>{{ $quote->customer->address }}</span>@endif
    </div>

    @if ($quote->title)
        <div class="object"><span class="label">Objet</span>{{ $quote->title }}</div>
    @endif

    @if ($accepted && $quote->decided_at)
        <div class="accepted">DEVIS ACCEPTÉ LE {{ $quote->decided_at->format('d/m/Y') }}</div>
    @endif

    <table class="lines">
        <thead>
            <tr><th>Désignation</th><th class="num">Qté</th><th class="num">Prix unitaire</th><th class="num">Montant</th></tr>
        </thead>
        <tbody>
            @foreach ($sections as $sectionLabel => $items)
                @if ($showSections)
                    <tr class="section"><td colspan="4">{{ $sectionLabel }}</td></tr>
                @endif
                @foreach ($items as $item)
                    <tr>
                        <td>{{ $item->designation }}</td>
                        <td class="num">{{ $qty($item->quantity) }} {{ $item->unit_name }}</td>
                        <td class="num">{{ $money($item->unit_price) }}</td>
                        <td class="num">{{ $money($item->subtotal) }}</td>
                    </tr>
                @endforeach
                @if ($showSections)
                    <tr class="subtotal"><td colspan="3">Sous-total {{ mb_strtolower($sectionLabel) }}</td><td class="num">{{ $money($items->sum('subtotal')) }}</td></tr>
                @endif
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        @if ($quote->discount > 0)
            <tr><td>Sous-total</td><td class="num">{{ $money($quote->gross_amount) }}</td></tr>
            <tr><td>Remise</td><td class="num">− {{ $money($quote->discount) }}</td></tr>
        @endif
        <tr class="total"><td>Total</td><td class="num">{{ $money($quote->total_amount) }}</td></tr>
        @if ($quote->deposit_amount > 0)
            <tr class="deposit">
                <td>Acompte à la commande{{ $quote->deposit_type === \App\Models\Quote::DEPOSIT_PERCENT ? ' (' . $quote->deposit_value . ' %)' : '' }}</td>
                <td class="num">{{ $money($quote->deposit_amount) }}</td>
            </tr>
            <tr class="balance"><td>Solde à la fin des travaux</td><td class="num">{{ $money($quote->total_amount - $quote->deposit_amount) }}</td></tr>
        @endif
    </table>

    @if ($quote->deposit_received_amount)
        <div class="received">Acompte de {{ $money($quote->deposit_received_amount) }} reçu le {{ $quote->deposit_received_at?->format('d/m/Y') }}.</div>
    @endif

    @if ($quote->notes)
        <div class="notes">
            <span class="label">Conditions et remarques</span>
            <p>{{ $quote->notes }}</p>
        </div>
    @endif

    <p class="disclaimer">Ce document est un devis : il ne constitue pas une facture.</p>

    @if ($stamp)
        <table class="stamp">
            <tr>
                <td></td>
                <td class="cell"><span class="label">Cachet de l'entreprise</span><img src="{{ $stamp }}" alt=""></td>
            </tr>
        </table>
    @endif

    <div class="foot">
        @if ($tenant->quote_footer){{ $tenant->quote_footer }}<br>@endif
        @if ($author)Établi par : {{ $author }} · @endif Merci de votre confiance.
        @if ($tenant->ninea || $tenant->rccm)
            <div class="legal">
                @if ($tenant->ninea)NINEA : {{ $tenant->ninea }}@endif
                @if ($tenant->ninea && $tenant->rccm) · @endif
                @if ($tenant->rccm)RCCM : {{ $tenant->rccm }}@endif
            </div>
        @endif
    </div>
</body>
</html>
