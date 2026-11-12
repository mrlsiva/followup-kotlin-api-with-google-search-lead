@extends('layouts.app')
@section('title', __('Import / Export'))
@section('content')
    <h1 class="page-title mb-3">{{ __('Import / Export') }}</h1>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-download text-teal me-1"></i>{{ __('Export my leads') }}</div>
                <div class="card-body">
                    <p class="text-muted">{{ __('Download all your leads with their next follow-up. The file can be edited and imported again.') }}</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('leads.export', ['format' => 'xlsx']) }}" class="btn btn-success"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('Excel (.xlsx)') }}</a>
                        <a href="{{ route('leads.export', ['format' => 'csv']) }}" class="btn btn-outline-success"><i class="bi bi-filetype-csv me-1"></i>CSV</a>
                    </div>
                    <hr>
                    <p class="small text-muted mb-1">{{ __('Business Finder exports (CSV, Excel, PDF) are on the Saved businesses page.') }}</p>
                    <a href="{{ route('businesses.index') }}" class="small">{{ __('Saved businesses') }} →</a>
                </div>
            </div>
        </div>
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header"><i class="bi bi-upload text-orange me-1"></i>{{ __('Import leads') }}</div>
                <div class="card-body">
                    <form method="POST" action="{{ route('leads.import') }}" enctype="multipart/form-data" class="mb-3">
                        @csrf
                        <div class="input-group">
                            <input type="file" name="file" accept=".xlsx,.csv" class="form-control @error('file') is-invalid @enderror" required>
                            <button class="btn btn-primary"><i class="bi bi-upload me-1"></i>{{ __('Import') }}</button>
                        </div>
                        @error('file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </form>
                    <ul class="small text-muted mb-0">
                        <li>{{ __('First row must be the header. Required column: Customer Name.') }}</li>
                        <li>{{ __('Rows that match an existing lead by ID or mobile number update it; blank cells never overwrite existing data.') }}</li>
                        <li>{{ __('Tip: export first and use that file as the template.') }}</li>
                    </ul>
                    <div class="mt-2 small"><span class="text-muted">{{ __('Columns') }}:</span> {{ implode(', ', $headers) }}</div>

                    @if ($r = session('import_result'))
                        <div class="row g-2 text-center mt-3">
                            <div class="col-4"><div class="border rounded-3 py-2"><div class="fs-4 fw-bold text-teal">{{ $r['created'] }}</div><div class="small">{{ __('Created') }}</div></div></div>
                            <div class="col-4"><div class="border rounded-3 py-2"><div class="fs-4 fw-bold text-orange">{{ $r['updated'] }}</div><div class="small">{{ __('Updated') }}</div></div></div>
                            <div class="col-4"><div class="border rounded-3 py-2"><div class="fs-4 fw-bold text-danger">{{ $r['skipped'] }}</div><div class="small">{{ __('Skipped') }}</div></div></div>
                        </div>
                        @if ($r['errors'])
                            <div class="alert alert-warning mt-3 mb-0 small"><ul class="mb-0">@foreach (array_slice($r['errors'], 0, 20) as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
