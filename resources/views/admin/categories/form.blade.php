@extends('layouts.app')
@php $editing = $category->exists; $system = $category->isSystem(); @endphp
@section('title', $editing ? __('Edit category') : __('Add category'))
@section('content')
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('admin.categories.index') }}" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="page-title">{{ $editing ? __('Edit category') : __('Add category') }}</h1>
    </div>

    <form method="POST" action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}" class="card" style="max-width: 680px" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="card-body">
            @if ($system)
                <div class="alert alert-light border small"><i class="bi bi-lock me-1"></i>{{ __('Built-in categories cannot be renamed or removed.') }}</div>
            @endif
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="name">{{ __('Category name (English)') }} <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="name" value="{{ old('name', $category->name) }}" class="form-control @error('name') is-invalid @enderror" maxlength="40" required @disabled($system) placeholder="{{ __('e.g. Pharmacies') }}">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if ($editing && ! $system)<div class="form-text">{{ __('Renaming also updates businesses saved with this category.') }}</div>@endif
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="name_ta">{{ __('Tamil name') }}</label>
                    <input type="text" name="name_ta" id="name_ta" value="{{ old('name_ta', $category->name_ta) }}" class="form-control @error('name_ta') is-invalid @enderror" maxlength="80" placeholder="{{ __('e.g. மருந்தகங்கள்') }}">
                    @error('name_ta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="search_term">{{ __('Google search words') }}</label>
                    <input type="text" name="search_term" id="search_term" value="{{ old('search_term', $category->search_term) }}" class="form-control @error('search_term') is-invalid @enderror" maxlength="80" placeholder="{{ __('Leave empty to use the category name') }}">
                    <div class="form-text">{{ __('Used to build the links, e.g. ":words in Chennai".', ['words' => $category->search_term ?: ($category->name ?: 'Pharmacies')]) }}</div>
                    @error('search_term')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sort_order">{{ __('Order') }}</label>
                    <input type="number" name="sort_order" id="sort_order" value="{{ old('sort_order', $category->sort_order) }}" class="form-control @error('sort_order') is-invalid @enderror" min="0" max="9999" required>
                    <div class="form-text">{{ __('Lower numbers show first.') }}</div>
                </div>
                @unless ($system)
                    <div class="col-12">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" id="is_active" value="1" @checked(old('is_active', $category->exists ? (int) $category->is_active : 1) == 1)>
                            <label class="form-check-label" for="is_active">{{ __('Active (shown in Business Finder)') }}</label>
                        </div>
                    </div>
                @endunless
            </div>
        </div>
        <div class="card-footer bg-white d-flex justify-content-end gap-2">
            <a href="{{ route('admin.categories.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
            <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>{{ $editing ? __('Save changes') : __('Add category') }}</button>
        </div>
    </form>
@endsection
