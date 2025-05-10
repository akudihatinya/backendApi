<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Login user menggunakan session Laravel
     */
    public function login(Request $request)
    {
        // Validasi input
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

        // Regenerate session untuk keamanan
        $request->session()->regenerate();

        // Get user data dengan relasi
        $user = Auth::user();
        if ($user->isPuskesmas()) {
            $user->load('puskesmas');
        }

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'Login berhasil',
        ], 200);
    }

    /**
     * Logout user (menggunakan session destroy)
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            Log::info('User logout: ' . $user->username . ' (ID: ' . $user->id . ')');
            Auth::logout();
            $request->session()->invalidate(); // Hapus session
            $request->session()->regenerateToken(); // Regenerasi CSRF token
        }

        return response()->json([
            'message' => 'Berhasil logout',
        ]);
    }

    /**
     * Mendapatkan informasi user yang sedang login
     */
    public function user(Request $request)
    {
        // Cek apakah user sudah login
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

        $user = Auth::user();

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
     * Check authentication status
     */
    public function check(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->isPuskesmas()) {
                $user->load('puskesmas');
            }

            return response()->json([
                'authenticated' => true,
                'user' => new UserResource($user),
            ]);
        }

        return response()->json([
            'authenticated' => false,
        ]);
    }

    /**
     * Mengubah password user yang sedang login
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
     * Mendapatkan profil Puskesmas beserta data user-nya
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
