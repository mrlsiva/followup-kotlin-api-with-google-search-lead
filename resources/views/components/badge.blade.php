@props(['value' => null])
@php
    $v = $value instanceof \BackedEnum ? $value->value : $value;
    $cls = match ($v) {
        // Lead status
        'New' => 'text-bg-info', 'Interested' => 'text-bg-success', 'Follow-up' => 'text-bg-warning',
        'Won' => 'bg-success', 'Lost' => 'text-bg-danger',
        // Priority
        'High' => 'bg-danger-subtle text-danger-emphasis', 'Medium' => 'bg-warning-subtle text-warning-emphasis', 'Low' => 'bg-secondary-subtle text-secondary-emphasis',
        // Website
        'No website found' => 'bg-orange-subtle text-orange', 'Website available' => 'bg-teal-subtle text-teal',
        'Website outdated' => 'bg-warning-subtle text-warning-emphasis', 'Website inactive' => 'bg-danger-subtle text-danger-emphasis',
        'Pending verification' => 'bg-secondary-subtle text-secondary-emphasis',
        // Contact
        'Pending' => 'bg-secondary-subtle text-secondary-emphasis', 'Verified' => 'bg-teal-subtle text-teal',
        'Incorrect number', 'Disconnected' => 'bg-danger-subtle text-danger-emphasis', 'Do not contact' => 'text-bg-dark',
        // Call status
        'Not Answered' => 'bg-secondary-subtle text-secondary-emphasis', 'Callback' => 'bg-warning-subtle text-warning-emphasis', 'Closed' => 'text-bg-dark',
        // Follow-up / role
        'pending' => 'bg-orange-subtle text-orange', 'completed' => 'bg-teal-subtle text-teal',
        'admin' => 'text-bg-dark', 'manager' => 'bg-teal-subtle text-teal', 'sales' => 'bg-secondary-subtle text-secondary-emphasis',
        default => 'bg-secondary-subtle text-secondary-emphasis',
    };
    $label = match ($v) { 'admin' => 'Admin', 'manager' => 'Manager', 'sales' => 'Sales', 'pending' => 'Pending', 'completed' => 'Completed', default => $v };
@endphp
@if ($v !== null && $v !== '')
    <span {{ $attributes->merge(['class' => "badge rounded-pill fw-medium $cls"]) }}>{{ __($label) }}</span>
@endif
