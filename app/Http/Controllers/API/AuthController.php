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
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Login user dan memberikan access token dan refresh token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validasi sederhana untuk API testing
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
        Log::info('User berhasil login: ' . $user->username . ' (ID: ' . $user->id . ')');

        // Generate access token (berlaku 1 jam)
        $tokenResult = $user->createToken('access_token', ['*'], now()->addHour());
        $accessToken = $tokenResult->plainTextToken;
        Log::debug('Access token dibuat: ' . substr($accessToken, 0, 10) . '... (berlaku hingga: ' . now()->addHour()->format('Y-m-d H:i:s') . ')');

        // Generate refresh token (berlaku 30 hari)
        $refreshToken = Str::random(60);
        Log::debug('Refresh token dibuat: ' . substr($refreshToken, 0, 10) . '...');

        // Hapus refresh token lama jika ada
        UserRefreshToken::where('user_id', $user->id)->delete();

        // Simpan refresh token baru
        UserRefreshToken::create([
            'user_id' => $user->id,
            'refresh_token' => $refreshToken,
            'expires_at' => Carbon::now()->addDays(30),
        ]);

        // Buat cookie untuk refresh token
        $refreshTokenCookie = cookie(
            'refresh_token',
            $refreshToken,
            60 * 24 * 30, // 30 hari dalam menit
            null,
            null,
            env('APP_ENV') === 'production', // Secure hanya di production
            true,  // HttpOnly (tidak bisa diakses JS)
            false,
            'lax'  // SameSite policy
        );

        // Buat cookie untuk access token
        $minutes = 60; // Expire dalam 60 menit
        $accessTokenCookie = cookie(
            'access_token',
            $accessToken,
            $minutes,
            null,
            null,
            env('APP_ENV') === 'production', // Secure hanya di production
            true,
            false,
            'lax'
        );

        Log::info('Cookie dibuat untuk user: ' . $user->username);

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'Login berhasil',
        ], 200)
            ->withCookie($accessTokenCookie)
            ->withCookie($refreshTokenCookie);
    }

    /**
     * Logout user dan mencabut semua token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            Log::info('User logout: ' . $user->username . ' (ID: ' . $user->id . ')');

            // Cabut semua token
            $user->tokens()->delete();

            // Hapus refresh token dari database
            $user->refreshTokens()->delete();
        }

        // Hapus cookie
        return response()->json([
            'message' => 'Berhasil logout',
        ])
            ->withCookie(cookie('access_token', '', 0))
            ->withCookie(cookie('refresh_token', '', 0));
    }

    /**
     * Memperbarui access token dengan menggunakan refresh token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        // Ambil refresh token dari cookie
        $refreshToken = $request->cookie('refresh_token');
        Log::debug('Received refresh token: ' . ($refreshToken ? substr($refreshToken, 0, 10) . '...' : 'null'));

        if (!$refreshToken) {
            Log::warning('Refresh token tidak ditemukan dalam cookie');
            return response()->json([
                'message' => 'Refresh token tidak ditemukan',
            ], 401);
        }

        // Cari refresh token di database
        $refreshTokenRecord = UserRefreshToken::where('refresh_token', $refreshToken)
            ->where('expires_at', '>', Carbon::now())
            ->first();

        // Debug untuk melihat semua token yang tersedia
        if (config('app.debug')) {
            $allTokens = UserRefreshToken::all()
                ->map(function ($token) use ($refreshToken) {
                    return [
                        'id' => $token->id,
                        'user_id' => $token->user_id,
                        'token_prefix' => substr($token->refresh_token, 0, 10) . '...',
                        'token_length' => strlen($token->refresh_token),
                        'expires_at' => $token->expires_at->format('Y-m-d H:i:s'),
                        'is_expired' => $token->expires_at < Carbon::now(),
                        'matches_request' => $token->refresh_token === $refreshToken
                    ];
                });
            Log::debug('Available tokens in database: ' . json_encode($allTokens));
        }

        if (!$refreshTokenRecord) {
            Log::warning('Refresh token tidak valid atau sudah kadaluarsa');
            return response()->json([
                'message' => 'Refresh token tidak valid atau sudah kadaluarsa',
            ], 401);
        }

        $user = $refreshTokenRecord->user;

        if (!$user) {
            Log::error('User tidak ditemukan untuk refresh token');
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        Log::info('Refresh token valid, membuat token baru untuk user ID: ' . $user->id);

        // Cabut semua token access token
        $user->tokens()->delete();

        // Generate access token baru
        $tokenResult = $user->createToken('access_token', ['*'], now()->addHour());
        $accessToken = $tokenResult->plainTextToken;
        Log::debug('Access token baru dibuat: ' . substr($accessToken, 0, 10) . '... (berlaku hingga: ' . now()->addHour()->format('Y-m-d H:i:s') . ')');

        // Generate refresh token baru
        $newRefreshToken = Str::random(60);
        Log::debug('Refresh token baru dibuat: ' . substr($newRefreshToken, 0, 10) . '...');

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
            env('APP_ENV') === 'production', // Secure hanya di production
            true,
            false,
            'lax'
        );

        // Buat cookie access token baru
        $minutes = 60; // Expire access token 60 menit
        $accessTokenCookie = cookie(
            'access_token',
            $accessToken,
            $minutes,
            null,
            null,
            env('APP_ENV') === 'production', // Secure hanya di production
            true,
            false,
            'lax'
        );

        Log::info('Cookie baru dibuat untuk user: ' . $user->username);

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'Token berhasil diperbarui',
        ])
            ->withCookie($accessTokenCookie)
            ->withCookie($refreshTokenCookie);
    }

    /**
     * Mendapatkan informasi user yang sedang login
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function user(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        // Load relasi puskesmas jika user adalah puskesmas
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

    /**
     * Mengubah password user yang sedang login
     * 
     * @param ChangePasswordRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = Auth::user();

        if (!Hash::check($request->get('current_password'), $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak cocok'
            ], 400);
        }

        $user->password = Hash::make($request->get('new_password'));
        $user->save();

        Log::info('User dengan ID: ' . $user->id . ' berhasil mengubah password');

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

    public function checkAuthStatus(Request $request)
    {
        return response()->json([
            'has_access_token_cookie' => $request->hasCookie('access_token'),
            'has_refresh_token_cookie' => $request->hasCookie('refresh_token'),
            'authenticated' => Auth::check(),
            'user_id' => Auth::check() ? Auth::id() : null,
            'cookie_info' => [
                'cookies_received' => $request->cookie(),
            ],
            'server_info' => [
                'app_url' => config('app.url'),
                'session_domain' => config('session.domain'),
                'session_secure' => config('session.secure'),
                'session_same_site' => config('session.same_site'),
            ]
        ]);
    }
}
