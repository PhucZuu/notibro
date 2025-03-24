<?php

namespace App\Http\Controllers\Api\Role;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::query()->get(); // hoặc Role::all();
    
        if ($roles->isEmpty()) {
            return response()->json([
                'code'    => 404,
                'message' => "No roles found"
            ], 404);
        }
    
        return response()->json([
            'code'    => 200,
            'message' => "Retrieve roles successfully",
            'data'    => $roles,
        ], 200);
    }

    public function trashed()
    {
        $roles = Role::onlyTrashed()->get();

        if ($roles->isEmpty()) {
            return response()->json([
                'code'    => 404,
                'message' => "No trashed roles found"
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => "Retrieve trashed roles successfully",
            'data'    => $roles,
        ], 200);
    }

    public function show($id)
    {
        $role = Role::withTrashed()->find($id);
        
        if (!$role) {
            return response()->json([
                'code'    => 404,
                'message' => 'Role not found',
            ]);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Retrieve role successfully',
            'data'    => $role,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => "required|max:255|unique:roles",
        ]);

        try {
            $role = Role::create($data);

            return response()->json([
                'code'    => 200,
                'message' => "Created successfully",
                'data'    => $role,
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => "required|max:255|unique:roles",
        ]);

        $role = Role::find($id);

        if(!$role) {
            return response()->json([
                'code'    => 404,
                'message' => 'Role not found',
            ]);
        }

        try {
            $role->update($data);

            return response()->json([
                'code'    => 200,
                'message' => "Created successfully",
                'data'    => $role,
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }

    public function delete($id)
    {
        $role = Role::find($id);

        if(!$role) {
            return response()->json([
                'code'    => 404,
                'message' => "Role not found",
            ],404);
        }

        try {
            $role->delete();

            return response()->json([
                'code' => 200,
                'message' => 'Delete role successfully',
            ],200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }

    public function forceDelete($id)
    {
        $role = Role::withTrashed()->find($id);

        if(!$role) {
            return response()->json([
                'code'    => 404,
                'message' => "Role not found",
            ],404);
        }

        $users = User::whereHas('roles', function ($q) use ($role) {
            $q->where('role_id', $role->id);
        })->get();

        try {
            if($users) {
                foreach ($users as $user) {
                    $user->roles()->detach($role->id);
                }
            }

            $role->forceDelete();

            return response()->json([
                'code' => 200,
                'message' => 'Delete role successfully',
            ],200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }

    public function restore($id)
    {
        $role = Role::withTrashed()->find($id);

        if(!$role) {
            return response()->json([
                'code'    => 404,
                'message' => "Role not found",
            ],404);
        }

        try {
            $role->restore();

            return response()->json([
                'code' => 200,
                'message' => 'Restore role successfully',
            ],200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ]);
        }
    }
}