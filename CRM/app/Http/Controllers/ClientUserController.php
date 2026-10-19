<?php

namespace App\Http\Controllers;

use App\Models\ClientUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Modules\Clients\Client;

/**
 * Staff management of client-portal logins, from the client detail page.
 * Credentials never leave this screen; clients change their own password
 * inside the portal afterwards.
 */
class ClientUserController extends Controller
{
    public function __construct()
    {
        $this->middleware('perm:sales');
    }
    public function store(Request $r, Client $client)
    {
        $data = $r->validate([
            'name' => 'required|string|max:255', 'email' => 'required|email|max:255|unique:client_users,email',
            'password' => 'required|string|min:8',
        ]);
        $client->portalUsers()->create([
            'name' => $data['name'], 'email' => $data['email'],
            'password' => Hash::make($data['password']), 'is_active' => true,
        ]);

        return back()->with('ok', 'Portal access created for '.$data['email'].'.');
    }

    public function toggle(Client $client, ClientUser $user)
    {
        abort_unless($user->client_id === $client->id, 404);
        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('ok', $user->email.($user->is_active ? ' activated.' : ' deactivated.'));
    }

    public function destroy(Client $client, ClientUser $user)
    {
        abort_unless($user->client_id === $client->id, 404);
        $email = $user->email;
        $user->delete();

        return back()->with('ok', 'Portal access for '.$email.' removed.');
    }
}
