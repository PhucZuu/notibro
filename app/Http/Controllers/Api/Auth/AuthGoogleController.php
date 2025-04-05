<?php

namespace App\Http\Controllers\Api\Auth;

session_start();

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuthGoogleController extends Controller
{
    public function redirect()
    {
        return response()->json([
            'url' => Socialite::driver('google')->stateless()->redirect()->getTargetUrl()
        ]);
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            $handleName = explode(' ',$googleUser->user['name']);
            // dd($handleName);
            DB::beginTransaction();
            
            $user = User::firstOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'first_name' => $handleName[0],
                    'last_name' => $handleName[count($handleName) -1],
                    'avatar' => $googleUser->user['picture'],
                    'password' => bcrypt($googleUser->user['id']),
                    'email_verified_at' => $googleUser->user['email_verified'] ? Carbon::now() : '',
                    'google_id' => $googleUser->getId(),
                ]
            );

            if(!$user->setting){
                Setting::create(['user_id' => $user->id]);
            }

            if(!count($user->roles) > 0) {
                $role = Role::where('name' , 'user')->first();

                if($role) {
                    $user->roles()->attach($role->id);
                }
            }

            if($user->tags->isEmpty()) {
                Tag::create([
                    'user_id' => $user->id,
                    'name'     => "$user->first_name $user->last_name",
                    'color_code'    => "#1890ff",
                    'description' => null,
                    'shared_user' => [],
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }

            DB::commit();

            $token = $user->createToken('access_token')->plainTextToken;
            
            return redirect()->away(env('URL_FRONTEND') . '/auth/google/callback?token=' . $token);

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error($e->getMessage());

            return redirect()->away(env('URL_FRONTEND') . '/auth/google/error');
        }
    }

    public function getGoogleUser()
    {
        try{
            $user = User::with('roles:id,name','setting')
            ->where('id', auth()->id())
            ->first();

            if(!$user) {
                return response()->json([
                    'code'    => 404,
                    'message' => "not found"
                ],404);
            }
            return response()->json([
                'code'         => 200,
                'message'      => 'Login successfully',     
                'user'         => [
                    "id"         => $user->id,
                    'email'      => $user->email,
                    "first_name" => $user->first_name,
                    "last_name"  => $user->last_name,
                    "role"       => $user->roles[0]['name'] ?? 'user',
                    "avatar"     => $user->avatar,
                ],
                'setting'      => $user->setting,
            ],200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            
            return response()->json([
                'code' => 500,
                'message' => 'An error occurred',
            ]);
        }
    }
}