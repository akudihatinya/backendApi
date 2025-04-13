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
use Illuminate\Support\Facades\Log;
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
        
        // Log aktivitas login
        Log::info("User {$user->name} berhasil login");
        
        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'token_type' => 'Bearer',
        ]);
    }
    
    public function logout(Request $request)
    {
        $user = $request->user();
        
        // Revoke all tokens
        $user->tokens()->delete();
        
        // Delete refresh tokens using relationship
        $user->refreshTokens()->delete();
        
        // Log aktivitas logout
        Log::info("User {$user->name} berhasil logout");
        
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
        
        // Ambil user melalui relasi yang sudah didefinisikan
        $user = $refreshToken->user;
        
        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }
        
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
        
        // Log aktivitas refresh token
        Log::info("User {$user->name} melakukan refresh token");
        
        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'token_type' => 'Bearer',
        ]);
    }
    
    public function user(Request $request)
    {
        $user = $request->user();
        
        // Load puskesmas relationship if user is a puskesmas
        if ($user->isPuskesmas()) {
            $user->load('puskesmas');
        }
        
        return response()->json([
            'user' => new UserResource($user),
            'role' => $user->role,
            'is_admin' => $user->isAdmin(),
            'is_puskesmas' => $user->isPuskesmas(),
        ]);
    }
    
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = $request->user();
        
        // Verifikasi password lama (jika ada dalam request)
        if ($request->has('old_password') && !Hash::check($request->old_password, $user->password)) {
            return response()->json([
                'message' => 'Password lama tidak sesuai',
                'errors' => [
                    'old_password' => ['Password lama yang Anda masukkan salah']
                ]
            ], 422);
        }
        
        $user->update([
            'password' => Hash::make($request->password),
        ]);
        
        // Revoke all tokens except current
        $user->tokens()->where('id', '<>', $request->user()->currentAccessToken()->id)->delete();
        
        // Log aktivitas perubahan password
        Log::info("User {$user->name} berhasil mengubah password");
        
        return response()->json([
            'message' => 'Password berhasil diubah',
        ]);
    }
    
    /**
     * Mendapatkan informasi pengguna dengan role puskesmas beserta data puskesmasnya
     */
    public function puskesmasProfile(Request $request)
    {
        $user = $request->user();
        
        if (!$user->isPuskesmas() || !$user->puskesmas) {
            return response()->json([
                'message' => 'Profil puskesmas tidak ditemukan',
            ], 404);
        }
        
        $user->load('puskesmas');
        
        return response()->json([
            'user' => new UserResource($user),
            'puskesmas' => $user->puskesmas,
        ]);
    }
}