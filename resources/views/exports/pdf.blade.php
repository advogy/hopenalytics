<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 40px 40px 70px 40px; }
        body { font-family: Helvetica, Arial, sans-serif; color: #0f172a; font-size: 11px; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        p.subtitle { color: #64748b; margin: 0 0 16px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; table-layout: fixed; }
        th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; word-wrap: break-word; overflow-wrap: anywhere; word-break: break-word; }
        th { background: #f1f5f9; font-weight: bold; }
        tr:nth-child(even) td { background: #f8fafc; }
        tfoot td { background: #f1f5f9; font-weight: bold; border-top: 2px solid #cbd5e1; }
        table.continued { margin-top: 0; border-top: 0; }
        tr.totals-top td { background: #f1f5f9; font-weight: bold; border-bottom: 2px solid #cbd5e1; }
        .summary { margin: 4px 0 0 0; padding: 0; list-style: none; }
        .summary li { display: inline-block; margin: 0 12px 0 0; padding: 4px 10px; border: 1px solid #e2e8f0; border-radius: 6px; }
        .summary .label { color: #64748b; }
        .summary .value { font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ $dataset['title'] }}</h1>
    @if ($dataset['subtitle'])
        <p class="subtitle">{{ $dataset['subtitle'] }}</p>
    @endif

    @if (! empty($dataset['summary']))
        <ul class="summary">
            @foreach ($dataset['summary'] as $item)
                <li><span class="label">{{ $item['label'] }}:</span> <span class="value">{{ $item['value'] }}</span></li>
            @endforeach
        </ul>
    @endif

    @if (empty($dataset['rows']))
        <p>{{ __('export.no_data') }}</p>
    @else
        {{--
            Rendered as consecutive tables of 100 rows rather than one table: DomPDF lays out a
            whole table at once, so its memory grows much faster than the row count (about 1.6 GB
            for 2,000 rows in one table, against about 0.4 GB in chunks), which a long export
            (Data Anggota per Gereja, Hastag) exceeded. The header repeats on each chunk, so it
            still reads as one table; the totals row sits above the first chunk and below the
            last. Very long exports are kept out of PDF altogether, see ExportController::
            PDF_MAX_ROWS.
        --}}
        @foreach (array_chunk($dataset['rows'], 100) as $chunk)
            <table @if (! $loop->first) class="continued" @endif>
                <thead>
                    <tr>
                        @foreach ($dataset['headers'] as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @if ($loop->first && ! empty($dataset['totals']))
                        <tr class="totals-top">
                            @foreach ($dataset['totals'] as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endif
                    @foreach ($chunk as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                @if ($loop->last && ! empty($dataset['totals']))
                    <tfoot>
                        <tr>
                            @foreach ($dataset['totals'] as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        @endforeach
    @endif
</body>
</html>
