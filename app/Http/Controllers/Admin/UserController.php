<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        $users = User::withTrashed()->get();

        return view('admin.users.index', compact('users'));
    }

    public function show($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return redirect()->route('admin.users.index')->with('error', 'User does not exist.');
        }

        return view('admin.users.show', compact('user'));
    }

    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return redirect()->route('admin.users.index')->with('error', 'User not found.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }

    public function ban($id)
    {
        $user = User::find($id);

        if (!$user) {
            return redirect()->route('admin.users.index')->with('error', 'User does not exist.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User is locked.');
    }

    public function unlock($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return redirect()->route('admin.users.index')->with('error', 'User does not exist.');
        }

        if ($user->trashed()) {
            $user->restore();
            return redirect()->route('admin.users.index')->with('success', 'Account has been successfully unlocked.');
        }

        return redirect()->route('admin.users.index')->with('error', 'Account is not locked.');
    }

    public function forceDelete($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return redirect()->route('admin.users.index')->with('error', 'User not found.');
        }

        $user->forceDelete();

        return redirect()->route('admin.users.index')->with('success', 'User permanently deleted.');
    }
}
