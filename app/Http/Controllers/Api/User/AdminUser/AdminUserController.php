<?php

namespace App\Http\Controllers\Api\User\AdminUser;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AdminUserController extends Controller
{
    
    public function index()
    {
        $users = User::withTrashed()->get();

        if ($users->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'There are no users.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Get list of users successfully.',
            'data' => $users,
        ], 200);
    }

    
    public function show($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Get user information successfully.',
            'data' => $user,
        ], 200);
    }



    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|min:8',
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'gender' => 'required|in:male,female',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'avatar' => 'nullable|image|max:2048',
        ]);

        $avatarPath = null;
        if ($request->hasFile('avatar')) {
            $avatarPath = $request->file('avatar')->store('avatars', 'public');
        }

        $user = User::create([
            'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'first_name' => $validatedData['first_name'],
            'last_name' => $validatedData['last_name'],
            'gender' => $validatedData['gender'],
            'address' => $validatedData['address'],
            'phone' => $validatedData['phone'],
            'avatar' => $avatarPath,
        ]);

        return response()->json(['code' => 201, 'message' => 'User created successfully', 'data' => $user], 201);
    }



    public function update(Request $request, $id)
    {
        $user = User::find($id);
    
        if (!$user) {
            return response()->json(['code' => 404, 'message' => 'User not found'], 404);
        }
    
        $validatedData = $request->validate([
            'email' => 'nullable|email|unique:users,email,' . $user->id,
            'password' => 'nullable|min:8',
            'first_name' => 'nullable|string',
            'last_name' => 'nullable|string',
            'gender' => 'nullable|in:male,female',
            'address' => 'nullable|string',
            'phone' => 'nullable|string',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Kiểm tra file avatar
        ]);
    
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filePath = $file->store('avatars', 'public');
            $validatedData['avatar'] = $filePath;
        }
    
        $user->update(array_filter([
            'email' => $validatedData['email'] ?? $user->email,
            'password' => isset($validatedData['password']) ? Hash::make($validatedData['password']) : $user->password,
            'first_name' => $validatedData['first_name'] ?? $user->first_name,
            'last_name' => $validatedData['last_name'] ?? $user->last_name,
            'gender' => $validatedData['gender'] ?? $user->gender,
            'address' => $validatedData['address'] ?? $user->address,
            'phone' => $validatedData['phone'] ?? $user->phone,
            'avatar' => $validatedData['avatar'] ?? $user->avatar,
        ]));
    
        return response()->json(['code' => 200, 'message' => 'User updated successfully', 'data' => $user]);
    }
    


    public function destroy($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json(['code' => 404, 'message' => 'User not found'], 404);
        }

        $user->delete();

        return response()->json(['code' => 200, 'message' => 'User deleted successfully']);
    }
    
    public function ban($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist.',
            ], 404);
        }

        try {
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'User is locked.',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while locking the user.',
            ], 500);
        }
    }

    
    public function unlock($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist.',
            ], 404);
        }

        if ($user->trashed()) {
            $user->restore();

            return response()->json([
                'success' => true,
                'message' => 'Account has been successfully unlocked.',
            ], 200);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Account is not locked.',
            ], 400);
        }
    }

    
    public function changePermission(Request $request, $id)
    {
        $data = $request->validate([
            'role_id' => ['required', Rule::exists('roles', 'id')],
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User does not exist.',
            ], 404);
        }

        try {
            $user->roles()->sync($data['role_id']);

            return response()->json([
                'success' => true,
                'message' => 'User rights have been changed successfully.',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while changing permissions.',
            ], 500);
        }
    }



    public function forceDelete($id)
    {
        $user = User::withTrashed()->find($id);

        if (!$user) {
            return response()->json(['code' => 404, 'message' => 'User not found'], 404);
        }

        $user->forceDelete();

        return response()->json(['code' => 200, 'message' => 'User permanently deleted']);
    }
}

