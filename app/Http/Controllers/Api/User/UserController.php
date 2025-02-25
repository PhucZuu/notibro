<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function getAllUser()
    {
        $users = User::with(['roles' => function($query) {  
            $query->select('roles.id', 'roles.name');  
        }])->paginate(10)->map(function ($user) {  
            return [  
                'email' => $user->email,
                'avatar' => $user->avatar,  
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'gender' => $user->gender,
                'address' => $user->address,
                'phone' => $user->phone,
                'email_verified_at' => $user->email_verified_at,
                'deleted_at' => $user->deleted_at,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'google_id' => $user->google_id,
                'roles' => $user->roles->map(function ($role) {  
                    return [  
                        'role_id' => $role->id,  
                        'role_name' => $role->name,  
                    ];  
                })->toArray(),  
            ];  
        });  

        

        if (!$users) {
            return response()->json([
                'code'    => 404,
                "message" =>  'Users not found',
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => "Retrieve user list successfully",
            'data'    => $users,
        ], 200);
    }

    public function show($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'code'    => 404,
                "message" =>  'User not found',
            ], 404);
        }

        return response()->json([
            'code' => 200,
            'message' => "Retrieve user successfully",
            'data'    => $user,
        ]);
    }

    public function ban($id)
    {
        $user =  User::find($id);

        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => "User not found",
            ], 404);
        }

        try {
            $user->delete();

            return response()->json([
                'code'    => 200,
                'message' => 'This user account has been locked',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code' => 500,
                'message' => "An error occurred",
            ]);
        }
    }

    public function unlock($id)
    {
        $user =  User::withTrashed()->find($id);

        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => "User not found",
            ], 404);
        }

        try {
            $user->restore();

            return response()->json([
                'code'    => 200,
                'message' => 'Account unlocked successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => "An error occurred",
            ]);
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
                'code'    => 404,
                'message' => "User not found",
            ]);
        }

        try {
            $user->roles()->sync($data['role_id']);

            return response()->json([
                'code'    => 200,
                'message' => "User permissions changed successfully",
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ], 500);
        }
    }

    public function profile()
    {
        $user = User::find(auth()->id());

        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'code'    => 200,
            'message' => 'Retrieve user successfully',
            'data'    => $user
        ]);
    }

    public function updateProfile(Request $request)
    {
        $info = $request->validate([
            'avatar'     => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'first_name' => ['required', 'max:255'],
            'last_name'  => ['required', 'max:255'],
            'gender'     => ['required', Rule::in(['male', 'female'])],
            'address'    => ['required', 'max:255'],
            'phone'      => ['required', 'regex:/^0[0-9]{9}$/'],
        ]);

        $user = User::find(auth()->id());

        try {
            if ($request->hasFile('avatar')) {
                $info['avatar'] = Storage::put('images', $request->file('avatar'));
            }

            $user->update($info);

            if ($request->hasFile('avatar') && $user->avatar && Storage::exists($user->avatar)) {
                Storage::delete($user->avatar);
            }

            return response()->json([
                'code'    => 200,
                'message' => 'Updated information successfully',
                'data'    => $user,
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code' => 500,
                'message' => 'An error occurred',
            ]);
        }
    }

    public function editAccount(Request $request, $id)
    {
        $info = $request->validate([
            'avatar'     => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'first_name' => ['required', 'max:255'],
            'last_name'  => ['required', 'max:255'],
            'gender'     => ['required', Rule::in(['male', 'female'])],
            'address'    => ['required', 'max:255'],
            'phone'      => ['required', 'regex:/^0[0-9]{9}$/'],
        ]);

        $user = User::find($id);

        try {
            if ($request->hasFile('avatar')) {
                $info['avatar'] = Storage::put('images', $request->file('avatar'));
            }

            $user->update($info);

            if ($request->hasFile('avatar') && $user->avatar && Storage::exists($user->avatar)) {
                Storage::delete($user->avatar);
            }

            return response()->json([
                'code'    => 200,
                'message' => 'Updated information successfully',
                'data'    => $user,
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code' => 500,
                'message' => 'An error occurred',
            ]);
        }
    }
}
