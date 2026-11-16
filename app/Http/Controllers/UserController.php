<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
                $q->where('name', 'like', $like)
                    ->orWhere('username', 'like', $like)
                    ->orWhere('email', 'like', $like);
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $u) => $u->only(['id', 'name', 'username', 'email', 'role', 'is_active']));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', ['roles' => User::ROLES]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = new User($request->safe()->only(['name', 'username', 'email', 'password']));
        // Privileged attributes are set explicitly, never mass assigned.
        $user->role = $data['role'];
        $user->is_active = true;
        $user->must_change_password = true;
        $user->created_by = $request->user()->id;
        $user->save();

        return redirect()->route('users.index')->with('success', "User {$user->username} created.");
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'roles' => User::ROLES,
            'target' => $user->only(['id', 'name', 'username', 'email', 'role', 'is_active']),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if ($user->isSuperadmin() && $user->is_active
            && $data['role'] !== User::ROLE_SUPERADMIN
            && User::activeSuperadminCount() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'There must always be at least one active Superadmin.',
            ]);
        }

        $user->fill($request->safe()->only(['name', 'username', 'email']));
        $user->role = $data['role'];
        $user->save();

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    /** Set a new temporary password; ends all of the user's sessions. */
    public function updatePassword(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', Password::min(8)],
        ]);

        $user->password = $data['password'];
        $user->must_change_password = true;
        $user->save();
        $user->endAllSessions();

        return back()->with('success', 'Temporary password set. The user must change it at next login.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['user' => 'You cannot deactivate your own account.']);
        }

        if ($user->is_active) {
            if ($user->isSuperadmin() && User::activeSuperadminCount() <= 1) {
                return back()->withErrors(['user' => 'There must always be at least one active Superadmin.']);
            }
            $user->is_active = false;
            $user->save();
            $user->endAllSessions();

            return back()->with('success', "{$user->username} deactivated.");
        }

        $user->is_active = true;
        $user->save();

        return back()->with('success', "{$user->username} activated.");
    }
}
