<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Shared helpers for the web panel. */
abstract class WebController extends Controller
{
    /** Only the owner may change a record (admins/managers can view and reassign). */
    protected function ensureOwner(Model $record): void
    {
        abort_unless((int) $record->user_id === (int) auth()->id(), 403, __('Only the owner can change this record.'));
    }

    /**
     * Owner filter for team viewers: ?owner=me | <user id> | (empty = everyone visible).
     * Sales users always see their own records.
     */
    protected function ownerScope(Request $request, Builder $query): Builder
    {
        $user = $request->user();
        if (! $user->canViewTeam()) {
            return $query->where($query->qualifyColumn('user_id'), $user->id);
        }
        $query->visibleTo($user);
        $owner = $request->input('owner');
        if ($owner === 'me') {
            $query->where($query->qualifyColumn('user_id'), $user->id);
        } elseif (is_numeric($owner) && $user->canSeeUser((int) $owner)) {
            $query->where($query->qualifyColumn('user_id'), (int) $owner);
        }

        return $query;
    }

    /** Users for the owner filter / reassign dropdown. */
    protected function teamUsers(User $user)
    {
        return $user->canViewTeam() ? $user->visibleUsers()->get(['id', 'name']) : collect();
    }
}
