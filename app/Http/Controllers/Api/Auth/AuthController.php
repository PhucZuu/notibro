<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\OtpMail;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only('email','password');

        $user = User::with('roles:id,name','setting')
        ->where('email',$credentials['email'])
        ->first();

        if(!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'code'    => 401,
                'message' => 'Invalid credentials',
            ],401);
        }

        if($user && $user->roles->contains('name', 'admin')){
            return response()->json([
                'code'    => 401,
                'message' => 'Invalid credentials',
            ], 401);
        }

        if($user->email_verified_at === null) {
            // Tạo mã OTP mới
            $otp = random_int(100000, 999999);

            // Lưu mã OTP vào cache
            Cache::put('otp_' . $user->email, $otp, Carbon::now()->addMinutes(5));

            // Gửi email chứa mã OTP
            Mail::to($user->email)->queue(new OtpMail($otp));

            return response()->json([
                'code'    => 400,
                'message' => 'Email is not verified',
            ]);
        }

        // Tạo token
        $accessToken = $user->createToken('access_token')->plainTextToken;

        return response()->json([
            'code'    => 200,
            'message' => 'Login successfully',
            'data'    => [
                'access_token' => $accessToken,
                'token_type'   => 'Bearer',
                'user'         => [
                    "id"         => $user->id,
                    'email'      => $user->email,
                    "first_name" => $user->first_name,
                    "last_name"  => $user->last_name,
                    "role"       => $user->roles[0]['name'] ?? 'user',
                    "avatar"     => $user->avatar,
                ],
                'setting'      => $user->setting,
            ],
        ],200);
    }

    public function loginWithAdmin(Request $request)
    {
        $credentials = $request->only('email','password');

        $user = User::with('roles:id,name','setting')
        ->where('email',$credentials['email'])
        ->first();

        if(!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'code'    => 401,
                'message' => 'Invalid credentials',
            ]);
        }

        if($user && !$user->roles->contains('name', 'admin')){
            return response()->json([
                'code'    => 403,
                'message' => 'You do not have permission to access this resource',
            ]);
        }

        // Tạo token
        $accessToken = $user->createToken('access_token')->plainTextToken;

        return response()->json([
            'code'    => 200,
            'message' => 'Login successfully',
            'data'    => [
                'access_token' => $accessToken,
                'token_type'   => 'Bearer',
                'user'         => [
                    "id"         => $user->id,
                    'email'      => $user->email,
                    "first_name" => $user->first_name,
                    "last_name"  => $user->last_name,
                    "role"       => $user->roles[0]['name'] ?? 'user',
                    "avatar"     => $user->avatar,
                ],
                'setting'      => $user->setting,
            ],
        ],200);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'email'      => ['required','max:255','email',Rule::unique('users')],
            'password'   => ['required','min:8','regex:/[a-z]/','regex:/[A-Z]/','regex:/[0-9]/','regex:/[!@#$%^&*]/','confirmed'],
            'avatar'     => ['nullable','image','mimes:jpg,jpeg,png','max:2048'],
            'first_name' => ['required','max:255'],
            'last_name'  => ['required','max:255'],
            'gender'     => ['required',Rule::in(['male','female'])],
            'address'    => ['nullable','max:255'],
            'phone'      => ['nullable','regex:/^0[0-9]{9}$/',Rule::unique('users')],
        ]);

        DB::beginTransaction();
        
        $role = Role::where('name', 'user')->first();

        try {
            $data['password'] = Hash::make($request->password);

            $user = User::create($data);

            Tag::create([
                'user_id' => $user->id,
                'name'     => "$user->first_name $user->last_name",
                'color_code'    => "#1890ff",
                'description' => null,
                'shared_user' => [],
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            Setting::create([
                'user_id' => $user->id
            ]);

            if(!$role){
                return response()->json([
                    'code'    => 404,
                    'message' => 'Can not find a suitable role',
                ],404);
            }

            $user->roles()->attach($role->id);

            // Tạo mã OTP
            $otp = random_int(100000, 999999);

            // Lưu mã OTP vào cache
            Cache::put('otp_' . $user->email, $otp, now()->addMinutes(5));

            Mail::to($user->email)->send(new OtpMail($otp));

            DB::commit();

            return response()->json([
                'code'    => 201,
                'message' => 'Registration successful',
                'data'    => $user,
            ],200);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ],500);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'code'    => 200,
            'message' => 'Logout successfully',
        ],200);
    }

    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email' => ['required','email','max:255'],
            'otp'   => ['required'],
        ]);

        $user = User::where('email', $request->email)->first();

        if(!$user) {
            return response()->json([
                'code'   => 404,
                'message' => 'Email does not exists',
            ],404);
        }
    
        // Lấy mã OTP từ cache
        $cachedOtp = Cache::get('otp_' . $request->email);
        
        if (!$cachedOtp) {
            return response()->json([
                'code'    => 400,
                'message' => 'OTP has expired or does not exist'
            ], 400);
        }

        if($cachedOtp != $request->otp) {
            return response()->json([
                'code'    => 400,
                'message' => 'OTP code is incorrect',
            ],400);
        }

        try {
            $user->update([
                'email_verified_at' => Carbon::now(),
            ]);
        
            Cache::forget('otp_' . $request->email);
    
            return response()->json([
                'code'    => 200,
                'message' => 'Email verified successfully',
            ],200);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred',
            ],500);
        }
    }

    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', Rule::exists('users')],
        ]);

        $user = User::where('email', $validated['email'])->first();

        // Tạo mã OTP mới
        $otp = random_int(100000, 999999);

        // Lưu mã OTP vào cache
        Cache::put('otp_' . $user->email, $otp, Carbon::now()->addMinutes(5));

        // Gửi email chứa mã OTP
        Mail::to($user->email)->send(new OtpMail($otp));

        return response()->json([
            'code'    => 200,
            'message' => 'OTP has been resent to your email'
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'old_password' => ['required'],
            'new_password' => ['required','min:8','regex:/[a-z]/','regex:/[A-Z]/','regex:/[0-9]/','regex:/[!@#$%^&*]/'],
            'password_confirmation' => ['required','same:new_password'],
        ]);
        
        $user = User::find(auth()->id());

        if(!$user) {
            return response()->json([
                'code'    => 404,
                'message' => 'User not found',
            ],404);
        } 

        if(!Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'code'    => 400,
                'message' => 'Password is incorrect',
            ],400);
        }

        try {
            $data['new_password'] = Hash::make($data['new_password']);

            $user->update(['password' => $data['new_password']]);

            return response()->json([
                'code'    => 200,
                'message' => 'Password changed successfully',
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage());

            return response()->json([
                'code'    => 500,
                'message' => 'An error occurred'
            ],500);
        }
    }

    public function sendOtpResetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required','email',Rule::exists('users')],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if($user->email_verified_at === null) {
            return response()->json([
                'code'    => 400,
                'message' => 'Email is not verified'
            ], 400);
        }
    
        $otp = random_int(100000, 999999);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $validated['email']],
            ['token' => $otp, 'created_at' => Carbon::now()]
        );
    
        Mail::to($validated['email'])->send(new OtpMail($otp));
    
        return response()->json([
            'code'    => 200,
            'message' => 'OTP sent to your email'
        ], 200);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'reset_token' => ['required','string'],
            'password'    => ['required','min:8','regex:/[a-z]/','regex:/[A-Z]/','regex:/[0-9]/','regex:/[!@#$%^&*]/','confirmed'],
        ]);

        $reset = DB::table('password_reset_tokens')
            ->where('token', $validated['reset_token'])
            ->first();

        if (!$reset) {
            return response()->json([
                'code'    => 400,
                'message' => 'Invalid reset token'
            ], 400);
        }

        if (Carbon::parse($reset->created_at)->addMinutes(5)->isPast()) {
            return response()->json([
                'code'    => 400,
                'message' => 'Reset token has expired'
            ], 400);
        }

        // Tìm người dùng theo email và cập nhật mật khẩu
        $user = User::where('email', $reset->email)->first();
        if (!$user) {
            return response()->json([
                'code'    => 404,
                'message' => 'User not found',
            ], 404);
        }

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Xóa mã khôi phục sau khi sử dụng
        DB::table('password_reset_tokens')->where('email', $reset->email)->delete();

        return response()->json([
            'code'    => 200,
            'message' => 'Password reset successfully'
        ], 200);
    }
}
