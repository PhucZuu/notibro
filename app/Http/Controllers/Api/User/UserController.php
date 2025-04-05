<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function getAllUser(Request $request)
    {
        $searchEmail = $request->query('email');
        $perPage = $request->query('per_page', 10);
    
        $users = $this->fetchUsersQuery(false, $searchEmail)
            ->paginate($perPage);
    
        $users->setCollection(
            $users->getCollection()->map([$this, 'mapUserData'])
        );
    
        return response()->json([
            'code'    => 200,
            'message' => "Retrieve user list successfully",
            'data'    => $users,
        ], 200);
    }
    
    
    public function getBanUsers(Request $request)
    {
        $searchEmail = $request->query('email');
        $perPage = $request->query('per_page', 10);
    
        $users = $this->fetchUsersQuery(true, $searchEmail)
            ->paginate($perPage);
    
        $users->setCollection(
            $users->getCollection()->map([$this, 'mapUserData'])
        );
    
        return response()->json([
            'code'    => 200,
            'message' => "Retrieve deleted user list successfully",
            'data'    => $users,
        ], 200);
    }
    

    public function fetchUsersQuery($onlySoftDeleted = false, $searchEmail = null)
    {
        $currentUser = auth()->user()->load('roles');
        $currentUserRoles = $currentUser->roles->pluck('name')->toArray();
    
        $query = User::with(['roles' => function ($query) {
            $query->select('roles.id', 'roles.name');
        }]);
    
        if ($onlySoftDeleted) {
            $query->onlyTrashed();
        } else {
            $query->whereNull('deleted_at');
        }
    
        if (!in_array('super admin', $currentUserRoles)) {
            $query->whereDoesntHave('roles', function ($query) {
                $query->whereIn('name', ['admin', 'super admin']);
            });
        }
    
        if ($searchEmail) {
            $query->where('email', 'like', '%' . $searchEmail . '%');
        }
    
        return $query;
    }

    public function mapUserData($user)
    {
        return [
            'id' => $user->id,
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

        if ($user->avatar && !Str::startsWith($user->avatar, ['http://', 'https://'])) {
            $user->avatar = Storage::url($user->avatar);
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
            'address'    => ['nullable', 'max:255'],
            'phone'      => ['nullable', 'sometimes', 'regex:/^0[0-9]{9}$/'],
        ]);

        $user = User::find(auth()->id());

        $flag = false;
        $oldAvatar = $user->avatar;

        try {
            if ($request->hasFile('avatar')) {
                $flag = true;
                $info['avatar'] = Storage::put('images', $request->file('avatar'));
            }

            $user->update($info);

            if ($flag && Storage::exists($oldAvatar)) {
                Storage::delete($oldAvatar);
            }

            if ($user->avatar && !Str::startsWith($user->avatar, ['http://', 'https://'])) {
                $user->avatar = Storage::url($user->avatar);
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

    public function infoAccount($id)
    {
        $user = User::with(['roles' => function ($query) {
            $query->select('roles.id', 'roles.name');
        }])
            ->where('id', $id) 
            ->first();

        if ($user) {
            $userData = [
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

            return response()->json([
                'code'    => 200,
                'message' => 'Retrieve user successfully',
                'data'    => $userData
            ]); // Trả về dữ liệu người dùng  
        } else {
            return response()->json([
                'code'    => 404,
                'message' => 'User not found',
            ], 404);  // Nếu không tìm thấy người dùng  
        }
    }

    public function guest(Request $request)
    {
        $search = $request->query('search');

        $query = User::select('id','email')->where('id', '!=', auth()->id());

        if ($search) {
            $query->where('email','like','%'. $search .'%');
        }

        $users = $query->get();

        return response()->json([
            'code'    => 200,
            'message' => 'Retrieve user list successfully',
            'data'    => $users,
        ], 200);
    }
}