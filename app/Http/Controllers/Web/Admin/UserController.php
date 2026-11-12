<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Web\WebController;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Admin: create users, set roles and teams, enable/disable, reset passwords. */
class UserController extends WebController
{
    public function index(Request $request): View
    {
        $users = User::query()->with('manager:id,name')
            ->withCount(['leads', 'team'])
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', '%'.$request->search.'%')->orWhere('email', 'like', '%'.$request->search.'%')))
            ->when($request->filled('role'), fn ($q) => $q->where('role', $request->role))
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'manager' THEN 2 ELSE 3 END")->orderBy('name')
            ->paginate(25)->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function create(): View
    {
        return view('admin.users.form', ['managed' => new User, 'managers' => $this->managers()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $user = new User;
        $this->fill($user, $data);
        $user->password = $data['password'];
        $user->save();
        AuditLog::record('user.created', $user, ['role' => $user->role->value]);

        return redirect()->route('admin.users.index')->with('success', __('User :name created.', ['name' => $user->name]));
    }

    public function edit(User $managedUser): View
    {
        return view('admin.users.form', ['managed' => $managedUser, 'managers' => $this->managers($managedUser)]);
    }

    public function update(Request $request, User $managedUser): RedirectResponse
    {
        $data = $this->validated($request, $managedUser);
        $self = $managedUser->id === $request->user()->id;
        if ($self && ($data['role'] !== UserRole::Admin->value || ! ($data['is_active'] ?? false))) {
            return back()->withInput()->with('error', __('You cannot remove your own admin access or disable yourself.'));
        }

        $this->fill($managedUser, $data);
        if (! empty($data['password'])) {
            $managedUser->password = $data['password'];
            $managedUser->tokens()->delete();            // sign out of the mobile app
        }
        $managedUser->save();
        if (! $managedUser->is_active) {
            $managedUser->tokens()->delete();
        }
        if ($managedUser->role !== UserRole::Manager) {
            User::where('manager_id', $managedUser->id)->update(['manager_id' => null]);   // a non-manager has no team
        }
        AuditLog::record('user.updated', $managedUser, ['role' => $managedUser->role->value, 'active' => $managedUser->is_active]);

        return redirect()->route('admin.users.index')->with('success', __('User :name updated.', ['name' => $managedUser->name]));
    }

    public function toggle(Request $request, User $managedUser): RedirectResponse
    {
        if ($managedUser->id === $request->user()->id) {
            return back()->with('error', __('You cannot remove your own admin access or disable yourself.'));
        }
        $managedUser->forceFill(['is_active' => ! $managedUser->is_active])->save();
        if (! $managedUser->is_active) {
            $managedUser->tokens()->delete();
        }
        AuditLog::record($managedUser->is_active ? 'user.enabled' : 'user.disabled', $managedUser);

        return back()->with('success', $managedUser->is_active ? __('User enabled.') : __('User disabled.'));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'role' => ['required', Rule::enum(UserRole::class)],
            'manager_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('role', UserRole::Manager->value),
                Rule::notIn(array_filter([$user?->id]))],
            'locale' => ['required', Rule::in(array_keys(config('crm.locales')))],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }

    private function fill(User $user, array $data): void
    {
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            // Only salespeople belong to a manager's team
            'manager_id' => $data['role'] === UserRole::Sales->value ? ($data['manager_id'] ?? null) : null,
            'locale' => $data['locale'],
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);
    }

    private function managers(?User $except = null)
    {
        return User::where('role', UserRole::Manager)->when($except, fn ($q) => $q->whereKeyNot($except->id))->orderBy('name')->get(['id', 'name']);
    }
}
