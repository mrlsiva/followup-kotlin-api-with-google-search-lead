<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Business Leads</title>
    <style>
        @page { margin: 18px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1f2933; }
        h1 { font-size: 14px; margin: 0 0 2px; color: #1a5fb4; }
        .meta { color: #616e7c; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #1a5fb4; color: #fff; text-align: left; padding: 4px; font-weight: bold; }
        td { padding: 3px 4px; border-bottom: 1px solid #e4e7eb; vertical-align: top; }
        tr:nth-child(even) td { background: #f5f7fa; }
        .muted { color: #7b8794; }
        .ok { color: #1b7f3b; }
        .warn { color: #b25e09; }
    </style>
</head>
<body>
<h1>Business Leads</h1>
<div class="meta">
    {{ $leads->count() }} record(s) · Generated {{ $generatedAt->format('d M Y, h:i A') }} by {{ $user->name }}
    @if ($filters)
        · Filters:
        @foreach ($filters as $k => $v)
            {{ str_replace('_', ' ', $k) }} = {{ is_array($v) ? count($v).' selected' : (is_bool($v) ? ($v ? 'yes' : 'no') : $v) }}@if (! $loop->last), @endif
        @endforeach
    @endif
</div>
<table>
    <thead>
    <tr>
        <th>#</th>
        <th>Business</th>
        <th>Category</th>
        <th>Contact</th>
        <th>Phone</th>
        <th>Location</th>
        <th>Website</th>
        <th>Contact status</th>
        <th>CRM</th>
        <th>Next follow-up</th>
    </tr>
    </thead>
    <tbody>
    @forelse ($leads as $b)
        <tr>
            <td>{{ $b->id }}</td>
            <td><strong>{{ $b->business_name }}</strong>@if ($b->email)<br><span class="muted">{{ $b->email }}</span>@endif</td>
            <td>{{ $b->category->value }}</td>
            <td>{{ $b->contact_person ?: '—' }}</td>
            <td>{{ $b->phone ?: '—' }}</td>
            <td>{{ collect([$b->area, $b->city, $b->district])->filter()->implode(', ') ?: '—' }}@if ($b->pincode) {{ $b->pincode }}@endif</td>
            <td>
                {{ $b->website_status->value }}
                @if ($b->website_url)<br><span class="muted">{{ \Illuminate\Support\Str::limit($b->website_url, 40) }}</span>@endif
            </td>
            <td class="{{ $b->contact_status->value === 'Verified' ? 'ok' : 'warn' }}">
                {{ $b->contact_status->value }}@if ($b->contact_verified_at)<br><span class="muted">{{ $b->contact_verified_at->format('d M Y') }}</span>@endif
            </td>
            <td>{{ $b->lead ? $b->lead->status->value.' · '.$b->lead->priority->value : 'Not saved' }}</td>
            <td>{{ $b->lead?->next_followup_at?->format('d M Y, h:i A') ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="10" class="muted">No records match the filters.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>
