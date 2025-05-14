<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    protected $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Login user and provide access token and refresh token
     * 
     * @param LoginRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $user = $this->authService->attemptLogin(
            $request->username,
            $request->password
        );

        if (!$user) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Login gagal',
                    'errors' => 'Username atau password salah'
                ],
                401
            );
        }

        Log::info('User berhasil login: ' . $user->username . ' (ID: ' . $user->id . ')');

        $tokens = $this->authService->createTokens($user);

        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'message' => 'Login berhasil',
        ]);
    }

    /**
     * Logout user and revoke all tokens
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            Log::info('User logout: ' . $user->username . ' (ID: ' . $user->id . ')');
            $this->authService->logout($user);
        }

        return response()->json([
            'message' => 'Berhasil logout',
        ]);
    }

    /**
     * Refresh access token using refresh token
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function refresh(Request $request)
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        $refreshToken = $request->refresh_token;
        
        $tokens = $this->authService->refreshToken($refreshToken);

        if (!$tokens) {
            Log::warning('Refresh token tidak valid atau sudah kadaluarsa');
            return response()->json([
                'message' => 'Refresh token tidak valid atau sudah kadaluarsa',
            ], 401);
        }

        // Re-fetch the user after token refresh
        $user = Auth::user();
        
        return response()->json([
            'user' => new UserResource($user),
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_in' => $tokens['expires_in'],
            'message' => 'Token berhasil diperbarui',
        ]);
    }

    /**
     * Get current authenticated user information
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

        // Load puskesmas relation if user is puskesmas
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
     * Change password for current user
     * 
     * @param ChangePasswordRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePassword(ChangePasswordRequest $request)
    {
        $user = Auth::user();
        $result = $this->authService->changePassword(
            $user,
            $request->current_password,
            $request->new_password
        );

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Password lama tidak cocok'
            ], 400);
        }

        Log::info('User dengan ID: ' . $user->id . ' berhasil mengubah password');

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah'
        ]);
    }
}