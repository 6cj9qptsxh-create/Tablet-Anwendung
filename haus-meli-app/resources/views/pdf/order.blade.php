<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Bestellung {{ $delivery_date_short }} — {{ $customer_name }}</title>
    <style>
        @page { margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #1a1a1a;
            margin: 0;
        }
        .header {
            border-bottom: 2px solid #660000;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .header-title {
            font-size: 16pt;
            font-weight: bold;
            margin: 0 0 6px 0;
            color: #660000;
        }
        .header-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .header-grid td {
            vertical-align: top;
            padding: 2px 0;
        }
        .label {
            color: #555;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .value {
            font-weight: bold;
            font-size: 10.5pt;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .cat-head td {
            background: #f5e6a8;
            font-weight: bold;
            font-size: 9.5pt;
            padding: 5px 6px;
            border: 1px solid #c9b45a;
        }
        .item td {
            padding: 4px 6px;
            border: 1px solid #ccc;
            vertical-align: top;
        }
        .item:nth-child(even) td {
            background: #fafafa;
        }
        .qty {
            width: 48px;
            text-align: center;
            font-weight: bold;
        }
        .price, .line {
            width: 72px;
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .subtotal td {
            padding: 4px 6px;
            border: 1px solid #ccc;
            background: #f3eef8;
            font-weight: bold;
            text-align: right;
        }
        .totals {
            width: 100%;
            margin-top: 12px;
            border-collapse: collapse;
        }
        .totals td {
            padding: 8px 10px;
            border: 1px solid #660000;
            background: #f3eef8;
        }
        .totals .sum-label {
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9pt;
            color: #555;
        }
        .totals .sum-value {
            text-align: right;
            font-size: 14pt;
            font-weight: bold;
            color: #660000;
        }
        .footer {
            margin-top: 16px;
            font-size: 7.5pt;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 6px;
        }
        .empty {
            padding: 24px;
            text-align: center;
            color: #777;
            border: 1px dashed #ccc;
        }
        .warn {
            margin-top: 8px;
            font-size: 8pt;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-title">Bestellung</div>
        <table class="header-grid">
            <tr>
                <td style="width: 55%;">
                    <div class="label">Kunde</div>
                    <div class="value">{{ $customer_name }}</div>
                    <div style="margin-top: 4px;">{{ $address }}</div>
                </td>
                <td style="width: 45%;">
                    <div class="label">Liefertag</div>
                    <div class="value">{{ $delivery_date }}</div>
                </td>
            </tr>
        </table>
    </div>

    @if(count($groups) === 0)
        <div class="empty">Keine Positionen in dieser Bestellung.</div>
    @else
        @foreach($groups as $group)
            <table class="items">
                <tr class="cat-head">
                    <td colspan="4">{{ $group['category'] }}</td>
                </tr>
                @foreach($group['items'] as $row)
                    <tr class="item">
                        <td class="qty">{{ $row['qty'] }}</td>
                        <td>{{ $row['label'] }}</td>
                        <td class="price">{{ number_format($row['unit_price'], 2, ',', '.') }} €</td>
                        <td class="line">{{ number_format($row['line_total'], 2, ',', '.') }} €</td>
                    </tr>
                @endforeach
                <tr class="subtotal">
                    <td colspan="3">Zwischensumme {{ $group['category'] }}</td>
                    <td>{{ number_format($group['subtotal'], 2, ',', '.') }} €</td>
                </tr>
            </table>
        @endforeach
    @endif

    <table class="totals">
        <tr>
            <td class="sum-label">Gesamtbetrag</td>
            <td class="sum-value">{{ number_format($total, 2, ',', '.') }} €</td>
        </tr>
    </table>

    @if(abs($total - $calc_total) > 0.009)
        <div class="warn">
            Hinweis: Gespeicherter Total ({{ number_format($total, 2, ',', '.') }} €)
            weicht von der Positionssumme ({{ number_format($calc_total, 2, ',', '.') }} €) ab.
        </div>
    @endif

    <div class="footer">
        Erstellt am {{ $generated_at }}
        · Bestellung #{{ $order_id }}
        · Kanal: {{ $channel }}
        · Nur bestellte Artikel · Einzelpreise zur Kontrolle
    </div>
</body>
</html>
