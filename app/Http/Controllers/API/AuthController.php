<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\UserRefreshToken;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->only('username', 'password');
        
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Username atau password salah',
            ], 401);
        }
        
        $user = Auth::user();
        
        // Generate access token
        $accessToken = $user->createToken('auth_token')->plainTextToken;
        
        // Generate refresh token
        $refreshToken = Str::random(60);
        
        // Save refresh token
        UserRefreshToken::create([
            'user_id' => $user->id,
            'refresh_token' => $refreshToken,
            'expires_at' => Carbon::now()->addDays(30),
        ]);
        
        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
        ]);
    }
    
    public function logout(Request $request)
    {
        // Revoke all tokens
        $request->user()->tokens()->delete();
        
        // Delete refresh tokens
        UserRefreshToken::where('user_id', $request->user()->id)->delete();
        
        return response()->json([
            'message' => 'Berhasil logout',
        ]);
    }
    
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);
        
        $refreshToken = UserRefreshToken::where('refresh_token', $request->refresh_token)
            ->where('expires_at', '>', Carbon::now())
            ->first();
        
        if (!$refreshToken) {
            return response()->json([
                'message' => 'Refresh token tidak valid atau sudah kadaluarsa',
            ], 401);
        }
        
        $user = $refreshToken->user;
        
        // Revoke all tokens
        $user->tokens()->delete();
        
        // Generate new access token
        $accessToken = $user->createToken('auth_token')->plainTextToken;
        
        // Generate new refresh token
        $newRefreshToken = Str::random(60);
        
        // Update refresh token
        $refreshToken->update([
            'refresh_token' => $newRefreshToken,
            'expires_at' => Carbon::now()->addDays(30),
        ]);
        
        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
        ]);
    }
    
    public function user(Request $request)
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }
    
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        
        $user->update([
            'password' => Hash::make($request->password),
        ]);
        
        // Revoke all tokens except current
        $user->tokens()->where('id', '<>', $request->user()->currentAccessToken()->id)->delete();
        
        return response()->json([
            'message' => 'Password berhasil diubah',
        ]);
    }
}