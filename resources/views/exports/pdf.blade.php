<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 40px 40px 70px 40px; }
        body { font-family: Helvetica, Arial, sans-serif; color: #0f172a; font-size: 11px; }
        h1 { font-size: 18px; margin: 0 0 4px 0; }
        p.subtitle { color: #64748b; margin: 0 0 16px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #e2e8f0; padding: 6px 8px; text-align: left; }
        th { background: #f1f5f9; font-weight: bold; }
        tr:nth-child(even) td { background: #f8fafc; }
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
        <table>
            <thead>
                <tr>
                    @foreach ($dataset['headers'] as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($dataset['rows'] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
