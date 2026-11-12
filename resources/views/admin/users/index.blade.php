@extends('layouts.app')
@section('title', __('Users'))
@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h1 class="page-title">{{ __('Users') }}</h1>
        <form class="d-flex gap-2 ms-auto" method="GET">
            <select name="role" class="form-select form-select-sm" data-autosubmit style="max-width: 10rem">
                <option value="">{{ __('All roles') }}</option>
                @foreach (\App\Enums\UserRole::cases() as $r)<option value="{{ $r->value }}" @selected(request('role') === $r->value)>{{ __($r->label()) }}</option>@endforeach
            </select>
            <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="{{ __('Name or email') }}">
        </form>
        <a href="{{ route('admin.users.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>{{ __('Add user') }}</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr>
                    <th>{{ __('User') }}</th><th>{{ __('Role') }}</th><th class="d-none d-md-table-cell">{{ __('Manager / team') }}</th>
                    <th class="d-none d-md-table-cell text-end">{{ __('Leads') }}</th><th class="d-none d-lg-table-cell">{{ __('Last login') }}</th>
                    <th>{{ __('Status') }}</th><th class="text-end">{{ __('Actions') }}</th>
                </tr></thead>
                <tbody>
                @foreach ($users as $u)
                    <tr class="{{ $u->is_active ? '' : 'opacity-50' }}">
                        <td><div class="fw-semibold">{{ $u->name }} @if ($u->id === auth()->id())<span class="badge bg-light text-muted">{{ __('You') }}</span>@endif</div><div class="small text-muted">{{ $u->email }}</div></td>
                        <td><x-badge :value="$u->role" /></td>
                        <td class="d-none d-md-table-cell small">
                            @if ($u->isManager()){{ trans_choice(':count member|:count members', $u->team_count, ['count' => $u->team_count]) }}
                            @elseif ($u->manager){{ $u->manager->name }}@else<span class="text-muted">—</span>@endif
                        </td>
                        <td class="d-none d-md-table-cell text-end">{{ $u->leads_count }}</td>
                        <td class="d-none d-lg-table-cell small text-muted">{{ $u->last_login_at?->diffForHumans() ?? __('Never') }}</td>
                        <td>@if ($u->is_active)<span class="badge bg-teal-subtle text-teal">{{ __('Active') }}</span>@else<span class="badge text-bg-secondary">{{ __('Disabled') }}</span>@endif</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.users.edit', $u) }}" class="btn btn-sm btn-light" title="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                            @if ($u->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.users.toggle', $u) }}" class="d-inline" data-confirm="{{ $u->is_active ? __('Disable this user? They will be signed out of the app.') : __('Enable this user?') }}">
                                    @csrf
                                    <button class="btn btn-sm btn-light {{ $u->is_active ? 'text-danger' : 'text-success' }}" title="{{ $u->is_active ? __('Disable') : __('Enable') }}"><i class="bi bi-{{ $u->is_active ? 'person-slash' : 'person-check' }}"></i></button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $users->links() }}</div>
    <div class="card card-body mt-3 small text-muted">
        <div><x-badge value="admin" /> {{ __('Sees all users\' leads, team reports and manages users.') }}</div>
        <div class="mt-1"><x-badge value="manager" /> {{ __('Sees the leads and reports of the salespeople assigned to them (read-only, can reassign leads).') }}</div>
        <div class="mt-1"><x-badge value="sales" /> {{ __('Sees only their own leads, follow-ups and businesses.') }}</div>
    </div>
@endsection
