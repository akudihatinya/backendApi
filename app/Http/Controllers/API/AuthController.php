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
use Illuminate\Support\Facades\Cookie;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // For API testing, allow simple validation
        $credentials = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($credentials)) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Login gagal',
                    'errors' => 'Username atau password salah'
                ],
                401
            );
        }

        $user = Auth::user();

        // Generate access token (1 hour)
        $tokenResult = $user->createToken('acces_token', ['*']);
        $accessToken = $tokenResult->plainTextToken;

        // Generate refresh token (30 days)
        $refreshToken = Str::random(60);

        // Save refresh token
        UserRefreshToken::create([
            'user_id' => $user->id,
            'refresh_token' => $refreshToken,
            'expires_at' => Carbon::now()->addDays(30),
        ]);
        $refreshTokenCookie = cookie(
            'refresh_token',
            $refreshToken,
            60 * 24 * 30, // 30 hari dalam menit
            null,
            null,
            true,  // Secure (hanya HTTPS)
            true,  // HttpOnly (tidak bisa diakses JS)
            false,
            'lax'  // SameSite policy
        );

        $minutes = 60; // Expire dalam 60 menit
        return response()->json([
            'user' => new UserResource($user),
            'message' => 'Login berhasil',
        ], status: 200)->withCookie(cookie('access_token', $accessToken, $minutes, null, null, true, true, false, 'lax'))
            ->withCookie($refreshTokenCookie);
    }

    public function logout(Request $request)
    {
        $user = $request->user();

        // Revoke all tokens
        $user->tokens()->delete();

        // Delete refresh tokens using relationship
        $user->refreshTokens()->delete();

        // Hapus cookie
        return response()->json([
            'message' => 'Berhasil logout',
        ])->withCookie(cookie('access_token', '', 0))
            ->withCookie(cookie('refresh_token', '', 0));
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->cookie('refresh_token');

        if (!$refreshToken) {
            return response()->json([
                'message' => 'Refresh token tidak ditemukan',
            ], 401);
        }

        $refreshTokenRecord = UserRefreshToken::where('refresh_token', $refreshToken)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        if (!$refreshTokenRecord) {
            return response()->json([
                'message' => 'Refresh token tidak valid atau sudah kadaluarsa',
            ], 401);
        }

        $user = $refreshTokenRecord->user;

        if (!$user) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        // Revoke all tokens
        $user->tokens()->delete();

        // Generate new access token
        $tokenResult = $user->createToken('acces_token', ['*']);
        $accessToken = $tokenResult->plainTextToken;

        // Generate new refresh token
        $newRefreshToken = Str::random(60);

        // Update refresh token di database
        $refreshTokenRecord->update([
            'refresh_token' => $newRefreshToken,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        // Buat cookie refresh token baru
        $refreshTokenCookie = cookie(
            'refresh_token',
            $newRefreshToken,
            60 * 24 * 30, // 30 hari
            null,
            null,
            true,
            true,
            false,
            'lax'
        );

        $minutes = 60; // Expire access token 60 menit

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'Token berhasil diperbarui',
        ])->withCookie(cookie('access_token', $accessToken, $minutes, null, null, true, true, false, 'lax'))
            ->withCookie($refreshTokenCookie);
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
        $user = Auth::user();

        if (!Hash::check($request->get('old_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak cocok'
            ], 400);
        }

        $user->password = Hash::make($request->get('new_password'));
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah'
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
