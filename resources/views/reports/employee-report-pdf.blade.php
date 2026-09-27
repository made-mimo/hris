<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 11px; color: #14151A; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { color: #6B7280; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #E7E8EC; padding: 6px 8px; text-align: left; }
        th { background: #F5F6F8; text-transform: uppercase; font-size: 9px; letter-spacing: 0.05em; color: #6B7280; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">Generated {{ now()->format('j M Y, H:i') }} &middot; {{ $rows->count() }} employee{{ $rows->count() === 1 ? '' : 's' }}</div>

    <table>
        <thead>
            <tr>
                @foreach($fields as $field)
                    <th>{{ $labels[$field] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($rows as $employee)
                <tr>
                    @foreach($fields as $field)
                        <td>{{ $service->rowValue($employee, $field) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
