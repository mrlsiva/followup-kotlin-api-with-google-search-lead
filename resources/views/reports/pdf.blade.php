<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Team report</title>
    <style>
        @page { margin: 22px 24px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1f2933; }
        h1 { font-size: 15px; margin: 0 0 2px; color: #1b1f2b; }
        .meta { color: #616e7c; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1b1f2b; color: #fff; text-align: right; padding: 5px; }
        th:first-child, td:first-child { text-align: left; }
        td { padding: 4px 5px; border-bottom: 1px solid #e4e7eb; text-align: right; }
        tr:nth-child(even) td { background: #f5f7fa; }
        tfoot td { font-weight: bold; background: #fff1df !important; border-top: 2px solid #f7941d; }
        .accent { color: #f7941d; }
    </style>
</head>
<body>
<h1>Team report <span class="accent">·</span> Sling CRM</h1>
<div class="meta">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }} · Generated {{ now()->format('d M Y, h:i A') }} by {{ $viewer->name }}</div>
<table>
    <thead>
    <tr>
        <th>Salesperson</th><th>Leads added</th><th>Total leads</th><th>Calls done</th><th>Pending</th><th>Overdue</th>
        <th>Interested</th><th>Won</th><th>Lost</th><th>Conversion</th><th>Researched</th><th>Saved to CRM</th>
    </tr>
    </thead>
    <tbody>
    @foreach ($rows as $r)
        <tr>
            <td>{{ $r['user']->name }} <span style="color:#7b8794">({{ $r['user']->role->label() }})</span></td>
            <td>{{ $r['leads_added'] }}</td><td>{{ $r['leads_total'] }}</td><td>{{ $r['calls'] }}</td><td>{{ $r['pending'] }}</td>
            <td>{{ $r['overdue'] }}</td><td>{{ $r['interested'] }}</td><td>{{ $r['won'] }}</td><td>{{ $r['lost'] }}</td>
            <td>{{ $r['conversion'] }}%</td><td>{{ $r['researched'] }}</td><td>{{ $r['saved_to_crm'] }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
    <tr>
        <td>Total</td><td>{{ $totals['leads_added'] }}</td><td>{{ $totals['leads_total'] }}</td><td>{{ $totals['calls'] }}</td>
        <td>{{ $totals['pending'] }}</td><td>{{ $totals['overdue'] }}</td><td>{{ $totals['interested'] }}</td><td>{{ $totals['won'] }}</td>
        <td>{{ $totals['lost'] }}</td><td>{{ $totals['conversion'] }}%</td><td>{{ $totals['researched'] }}</td><td>{{ $totals['saved_to_crm'] }}</td>
    </tr>
    </tfoot>
</table>
</body>
</html>
