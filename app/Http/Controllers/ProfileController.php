<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $currentId = $request->session()->getId();

        $sessions = DB::table('sessions')
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id === $currentId ? 'current' : substr(sha1($s->id), 0, 12),
                'device' => $this->describeAgent((string) $s->user_agent),
                'ip' => $s->ip_address,
                'last_active' => Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                'is_current' => $s->id === $currentId,
            ])
            ->values();

        return Inertia::render('Profile/Show', [
            'profile' => $user->only(['name', 'username', 'email', 'job_title', 'whatsapp', 'role']) + [
                'avatar_url' => $user->avatarUrl(),
            ],
            'sessions' => $sessions,
        ]);
    }

    /** Users may change their own details. Username is Superadmin-only. */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->merge(['whatsapp' => User::normaliseWhatsapp($request->input('whatsapp'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'job_title' => ['nullable', 'string', 'max:100'],
            'whatsapp' => ['nullable', 'regex:/^\+[1-9][0-9]{7,14}$/'],
        ], [
            'whatsapp.regex' => 'Enter a valid WhatsApp number, e.g. 0812 3456 7890 or +62 812 3456 7890.',
        ]);

        $user->update($data);

        return back()->with('success', 'Profile updated.');
    }

    public function storeAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max(2048)->dimensions(
                Rule::dimensions()->maxWidth(4000)->maxHeight(4000)
            )],
        ], [
            'avatar.max' => 'The photo may not be larger than 2 MB.',
        ]);

        $user = $request->user();
        $old = $user->avatar_path;

        $user->avatar_path = $request->file('avatar')->store('avatars', 'local');
        $user->save();

        if ($old) {
            Storage::disk('local')->delete($old);
        }

        return back()->with('success', 'Photo updated.');
    }

    public function destroyAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('local')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        return back()->with('success', 'Photo removed.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Password::min(8), 'different:current_password'],
        ]);

        $user = $request->user();
        $user->password = $data['password'];
        $user->must_change_password = false;
        $user->save();

        $this->deleteOtherSessions($request);

        return redirect()->route('home')->with('success', 'Password changed.');
    }

    public function logoutOtherSessions(Request $request): RedirectResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']]);

        $this->deleteOtherSessions($request);

        return back()->with('success', 'Logged out from all other devices.');
    }

    /** End every other session of this account, keep the current one. */
    private function deleteOtherSessions(Request $request): void
    {
        DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();
    }

    private function describeAgent(string $ua): string
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'unknown device',
        };

        return "$browser on $os";
    }
}
