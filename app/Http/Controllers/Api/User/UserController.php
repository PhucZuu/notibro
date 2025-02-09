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
    public function profile()
    {
        $user = User::find(auth()->id());

        if(!$user) {
            return response()->json([
                'code'    => 404,
                'message' => 'User not found',
            ],404);
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
            'avatar'     => ['nullable','image','mimes:jpg,jpeg,png','max:2048'],
            'first_name' => ['required','max:255'],
            'last_name'  => ['required','max:255'],
            'gender'     => ['required',Rule::in(['male','female'])],
            'address'    => ['required','max:255'],
            'phone'      => ['required','regex:/^0[0-9]{9}$/'],
        ]);

        $user = User::find(auth()->id());

        try {
            if($request->hasFile('avatar')) {
                $info['avatar'] = Storage::put('images',$request->file('avatar'));
            }

            $user->update($info);

            if($request->hasFile('avatar') && $user->avatar && Storage::exists($user->avatar)) {
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
