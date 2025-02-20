<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    public function index()
    {
        try {
            $roles = Role::withTrashed()->get();
            return view('admin.roles.index', compact('roles'));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while retrieving roles');
        }
    }

    public function create()
    {
        return view('admin.roles.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name',
        ]);

        try {
            Role::create($validated);
            session()->flash('success', 'Role added successfully!');
            return redirect()->route('roles.index');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while creating role');
        }
    }

    public function show($id)
    {
        $role = Role::findOrFail($id);
        return view('admin.roles.show', compact('role'));
    }

    public function edit($id)
    {
        $role = Role::findOrFail($id);
        return view('admin.roles.edit', compact('role'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:roles,name,' . $id,
        ]);

        try {
            $role = Role::withTrashed()->findOrFail($id);
            $role->update($validated);
            session()->flash('success', 'Role updated successfully!');
            return redirect()->route('roles.index');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while updating role');
        }
    }

    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);
            $role->delete(); 
            return redirect()->route('roles.index')->with('success', 'Role deleted successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while deleting role');
        }
    }

    public function restore($id)
    {
        try {
            $role = Role::onlyTrashed()->findOrFail($id);
            $role->restore(); 
            return redirect()->route('roles.index')->with('success', 'Role restored successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while restoring role');
        }
    }

    public function forceDelete($id)
    {
        try {
            $role = Role::withTrashed()->findOrFail($id);
            $role->forceDelete(); 
            return redirect()->route('roles.index')->with('success', 'Role permanently deleted');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while permanently deleting role');
        }
    }
}
