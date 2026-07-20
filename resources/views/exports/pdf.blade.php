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
    </style>
</head>
<body>
    <h1>{{ $dataset['title'] }}</h1>
    @if ($dataset['subtitle'])
        <p class="subtitle">{{ $dataset['subtitle'] }}</p>
    @endif

    @if (empty($dataset['rows']))
        <p>Tidak ada data untuk diekspor.</p>
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
