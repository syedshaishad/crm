<?php

namespace Modules\Settings\Http;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Licensing\LicenseManager;

class UsersController extends Controller
{
    public function index(Request $r)
    {
        $q = User::orderBy('name');
        if ($r->filled('q')) {
            $s = '%'.$r->q.'%';
            $q->where(fn ($w) => $w->where('name', 'like', $s)->orWhere('email', 'like', $s));
        }
        if ($r->filled('role')) {
            $q->where('role', $r->role);
        }
        if ($r->filled('status')) {
            $q->where('is_active', $r->status === 'active');
        }

        $stats = [
            'total' => User::count(),
            'active' => User::where('is_active', true)->count(),
            'admins' => User::where('role', 'admin')->count(),
            'disabled' => User::where('is_active', false)->count(),
        ];

        return view('settings.users', ['users' => $q->paginate(perPage())->withQueryString(), 'q' => $r->q, 'role' => $r->role, 'status' => $r->status, 'stats' => $stats]);
    }

    public function create()
    {
        return view('settings.user-form', ['user' => new User(['role' => 'staff', 'is_active' => true])]);
    }

    public function store(Request $r)
    {
        $limit = (int) (app(LicenseManager::class)->status()['user_limit'] ?? 0);
        if ($limit > 0 && User::count() >= $limit) {
            return back()->withErrors(['user' => 'User limit reached ('.$limit.'). Upgrade the license to add more users.']);
        }
        $data = $r->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8|confirmed',
            'role' => 'required|in:admin,manager,staff',
            'is_active' => 'nullable|boolean',
            'monthly_target' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'designation' => 'nullable|string|max:120', 'department' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:20', 'address' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
            'capacity_hours' => 'nullable|integer|min:1|max:168',
            'permissions' => 'nullable|array',
            'permissions.*' => 'nullable|in:inherit,full,view,none',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:4096',
            'remove_avatar' => 'nullable|boolean',
        ]);
        $perms = collect($data['permissions'] ?? [])->filter(fn ($v) => in_array($v, ['full', 'view', 'none'], true))->all();
        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'role' => $data['role'], 'is_active' => $r->boolean('is_active', true), 'monthly_target' => $data['monthly_target'] ?? 0, 'commission_rate' => $data['commission_rate'] ?? 0, 'designation' => $data['designation'] ?? null, 'department' => $data['department'] ?? null, 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null, 'joining_date' => $data['joining_date'] ?? null, 'capacity_hours' => $data['capacity_hours'] ?? null, 'permissions' => $data['role'] === 'admin' ? null : ($perms ?: null)]);
        $this->applyAvatar($r, $user);
        $user->save();

        return redirect()->route('settings.users.index')->with('ok', 'User created.');
    }

    public function edit(User $user)
    {
        return view('settings.user-form', ['user' => $user]);
    }

    public function update(Request $r, User $user)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|min:8|confirmed',
            'role' => 'required|in:admin,manager,staff',
            'is_active' => 'nullable|boolean',
            'monthly_target' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'designation' => 'nullable|string|max:120', 'department' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:20', 'address' => 'nullable|string|max:255',
            'joining_date' => 'nullable|date',
            'capacity_hours' => 'nullable|integer|min:1|max:168',
            'permissions' => 'nullable|array',
            'permissions.*' => 'nullable|in:inherit,full,view,none',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:4096',
            'remove_avatar' => 'nullable|boolean',
        ]);
        if ($user->id === auth()->id() && ($data['role'] !== 'admin' || ! $r->boolean('is_active', true))) {
            return back()->withErrors(['role' => 'You cannot demote or deactivate your own admin account.']);
        }
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];
        $user->is_active = $r->boolean('is_active');
        $user->monthly_target = $data['monthly_target'] ?? 0;
        $user->commission_rate = $data['commission_rate'] ?? 0;
        $user->designation = $data['designation'] ?? null;
        $user->department = $data['department'] ?? null;
        $user->phone = $data['phone'] ?? null;
        $user->address = $data['address'] ?? null;
        $user->joining_date = $data['joining_date'] ?? null;
        $user->capacity_hours = $data['capacity_hours'] ?? null;
        // Never let an admin lock themselves out of sections.
        if ($user->id !== auth()->id()) {
            $perms = collect($data['permissions'] ?? [])->filter(fn ($v) => in_array($v, ['full', 'view', 'none'], true))->all();
            $user->permissions = $data['role'] === 'admin' ? null : ($perms ?: null);
        }
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $this->applyAvatar($r, $user);
        $user->save();

        return redirect()->route('settings.users.index')->with('ok', 'User updated.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }
        $user->delete();

        return back()->with('ok', 'User deleted.');
    }

    public function account()
    {
        return view('settings.account', ['user' => auth()->user()]);
    }

    public function setup2fa(Request $r)
    {
        $r->session()->put('twofa:setup_secret', totp_secret());

        return back()->with('ok', 'Secret generated — enter a code from your app to finish.');
    }

    public function confirm2fa(Request $r)
    {
        $secret = $r->session()->get('twofa:setup_secret');
        if (! $secret) {
            return back()->withErrors(['code' => 'Start setup again — the secret expired.']);
        }
        $data = $r->validate(['code' => 'required|string|max:32']);
        if (! totp_verify($secret, $data['code'])) {
            return back()->withErrors(['code' => 'Wrong code. Check the app clock and try again.']);
        }
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }
        auth()->user()->update([
            'twofa_secret' => $secret, 'twofa_enabled' => true,
            'twofa_recovery_codes' => json_encode(array_map(fn ($c) => Hash::make($c), $codes)),
        ]);
        $r->session()->forget('twofa:setup_secret');

        return back()->with('twofa:codes', $codes)->with('ok', 'Two-factor switched on.');
    }

    public function disable2fa(Request $r)
    {
        $user = auth()->user();
        $data = $r->validate(['password' => 'required|string']);
        if (! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => 'Wrong password.']);
        }
        $user->update(['twofa_enabled' => false, 'twofa_secret' => null, 'twofa_recovery_codes' => null]);

        return back()->with('ok', 'Two-factor switched off.');
    }

    public function accountUpdate(Request $r)
    {
        $user = auth()->user();
        $data = $r->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|min:8|confirmed',
            'current_password' => 'required_with:password|string',
            'avatar' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:4096',
            'remove_avatar' => 'nullable|boolean',
        ]);
        if (! empty($data['password']) && ! Hash::check($data['current_password'] ?? '', $user->password)) {
            return back()->withErrors(['current_password' => 'Current password is incorrect.']);
        }
        $user->name = $data['name'];
        $user->email = $data['email'];
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $this->applyAvatar($r, $user);
        $user->save();

        return back()->with('ok', 'Profile updated.');
    }

    private function applyAvatar(Request $r, User $user): void
    {
        if ($r->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($r->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk('public')->delete($user->avatar_path);
            }
            $user->avatar_path = $r->file('avatar')->store('avatars', 'public');
        }
    }
}
