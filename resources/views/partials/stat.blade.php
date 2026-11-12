{{-- @include('partials.stat', ['label' => ..., 'value' => ..., 'icon' => 'people', 'tone' => 'orange|teal|red|navy', 'href' => null]) --}}
@php $tag = ! empty($href) ? 'a' : 'div'; @endphp
<{{ $tag }} @if (! empty($href)) href="{{ $href }}" @endif class="stat-card stat-{{ $tone ?? 'navy' }}">
    <div class="d-flex align-items-start justify-content-between gap-2">
        <div>
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value">{{ number_format($value) }}</div>
        </div>
        <span class="stat-icon"><i class="bi bi-{{ $icon }}"></i></span>
    </div>
    @if (! empty($hint))<div class="small text-muted mt-1">{{ $hint }}</div>@endif
</{{ $tag }}>
