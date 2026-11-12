<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Web\WebController;
use App\Models\AuditLog;
use App\Models\BusinessLead;
use App\Models\BusinessSearch;
use App\Models\FinderCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Admin: Business Finder categories. Businesses store the category name, so a rename is
 * applied to existing businesses and search history too. Categories in use are deactivated
 * rather than deleted; the two built-in ones (All / Other businesses) cannot be renamed or removed.
 */
class CategoryController extends WebController
{
    public function index(): View
    {
        $usage = BusinessLead::withTrashed()->selectRaw('category, COUNT(*) as n')->groupBy('category')->pluck('n', 'category');

        return view('admin.categories.index', [
            'categories' => FinderCategory::query()->ordered()->get(),
            'usage' => $usage,
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', ['category' => new FinderCategory(['sort_order' => (int) FinderCategory::where('system', null)->max('sort_order') + 10])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = FinderCategory::create($this->validated($request));
        AuditLog::record('category.created', $category, ['name' => $category->name]);

        return redirect()->route('admin.categories.index')->with('success', __('Category :name added.', ['name' => $category->name]));
    }

    public function edit(FinderCategory $category): View
    {
        return view('admin.categories.form', ['category' => $category]);
    }

    public function update(Request $request, FinderCategory $category): RedirectResponse
    {
        $data = $this->validated($request, $category);
        if ($category->isSystem()) {
            unset($data['name'], $data['is_active']);     // built-in: only labels, search words and order change
        }
        $old = $category->name;

        DB::transaction(function () use ($category, $data, $old) {
            $category->update($data);
            if ($category->name !== $old) {
                BusinessLead::withTrashed()->where('category', $old)->update(['category' => $category->name]);
                BusinessSearch::where('category', $old)->update(['category' => $category->name]);
            }
        });
        AuditLog::record('category.updated', $category, ['name' => $category->name, 'renamed_from' => $category->name !== $old ? $old : null]);

        return redirect()->route('admin.categories.index')->with('success', __('Category :name updated.', ['name' => $category->name]));
    }

    public function toggle(FinderCategory $category): RedirectResponse
    {
        if ($category->isSystem()) {
            return back()->with('error', __('Built-in categories cannot be turned off.'));
        }
        $category->update(['is_active' => ! $category->is_active]);
        AuditLog::record($category->is_active ? 'category.enabled' : 'category.disabled', $category, ['name' => $category->name]);

        return back()->with('success', $category->is_active ? __('Category turned on.') : __('Category turned off. Businesses already saved with it keep it.'));
    }

    public function destroy(FinderCategory $category): RedirectResponse
    {
        if ($category->isSystem()) {
            return back()->with('error', __('Built-in categories cannot be deleted.'));
        }
        if (BusinessLead::withTrashed()->where('category', $category->name)->exists()) {
            return back()->with('error', __('This category is used by saved businesses. Turn it off instead of deleting it.'));
        }
        $category->delete();
        AuditLog::record('category.deleted', $category, ['name' => $category->name]);

        return back()->with('success', __('Category deleted.'));
    }

    private function validated(Request $request, ?FinderCategory $category = null): array
    {
        $data = $request->validate([
            'name' => [$category?->isSystem() ? 'nullable' : 'required', 'string', 'max:40',
                Rule::unique('business_categories', 'name')->ignore($category?->id),
                Rule::notIn(['All', 'all businesses', 'All Businesses'])],
            'name_ta' => ['nullable', 'string', 'max:80'],
            'search_term' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['required', 'integer', 'between:0,9999'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        if (isset($data['name'])) {
            $data['name'] = trim(preg_replace('/\s+/', ' ', $data['name']));
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
