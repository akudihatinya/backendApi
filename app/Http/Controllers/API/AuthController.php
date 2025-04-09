<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Dinas;
use App\Models\Puskesmas;

class AuthController extends Controller
{
    /**
     * Login user
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $credentials = $request->only('username', 'password');

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            $token = $user->createToken('auth-token')->plainTextToken;
            
            // Get user's role
            $roles = $user->getRoleNames();
            
            // Get additional data based on user type
            $additionalData = [];
            if ($user->isadmin) {
                $dinas = Dinas::find($user->dinas_id);
                if ($dinas) {
                    $additionalData['dinas'] = [
                        'id' => $dinas->id,
                        'kode' => $dinas->kode,
                        'nama' => $dinas->nama
                    ];
                }
            } else {
                $puskesmas = Puskesmas::where('nama', $user->nama_puskesmas)->first();
                if ($puskesmas) {
                    $additionalData['puskesmas'] = [
                        'id' => $puskesmas->id,
                        'kode' => $puskesmas->kode,
                        'nama' => $puskesmas->nama,
                        'dinas_id' => $puskesmas->dinas_id
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Login berhasil',
                'data' => [
                    'user' => $user,
                    'roles' => $roles,
                    'is_admin' => $user->isadmin,
                    'token' => $token,
                    'additional_data' => $additionalData
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Username atau password salah',
        ], 401);
    }

    /**
     * Register a new user
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string|unique:users',
            'password' => 'required|string|min:6',
            'nama_puskesmas' => 'required|string',
            'isadmin' => 'boolean',
            'dinas_id' => 'nullable|exists:dinas,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Default role
        $role = 'puskesmas';
        
        // Check if this is an admin user
        $isAdmin = $request->isadmin ?? false;
        if ($isAdmin) {
            // Validate dinas_id is provided for admin users
            if (empty($request->dinas_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dinas ID diperlukan untuk pengguna admin',
                ], 422);
            }
            
            $role = 'dinas'; // Default role for dinas admin
            
            // If username is 'admin', assign admin role
            if ($request->username === 'admin') {
                $role = 'admin';
            }
        } else {
            // Validate puskesmas exists
            $puskesmas = Puskesmas::where('nama', $request->nama_puskesmas)->first();
            if (!$puskesmas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Puskesmas dengan nama tersebut tidak ditemukan',
                ], 422);
            }
        }

        $user = User::create([
            'username' => $request->username,
            'password' => bcrypt($request->password),
            'nama_puskesmas' => $request->nama_puskesmas,
            'isadmin' => $isAdmin,
            'dinas_id' => $isAdmin ? $request->dinas_id : null,
        ]);
        
        // Assign role
        $user->assignRole($role);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'User berhasil dibuat',
            'data' => [
                'user' => $user,
                'role' => $role,
                'token' => $token
            ]
        ]);
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        // Either use this approach
        if ($request->user()) {
            $request->user()->tokens()->delete(); // Deletes all tokens
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil'
        ]);
    }

    /**
     * Get authenticated user with additional data
     */
    public function user(Request $request)
    {
        $user = $request->user();
        
        // Get user's role
        $roles = $user->getRoleNames();
        
        // Get additional data based on user type
        $additionalData = [];
        if ($user->isadmin) {
            $dinas = Dinas::find($user->dinas_id);
            if ($dinas) {
                $additionalData['dinas'] = [
                    'id' => $dinas->id,
                    'kode' => $dinas->kode,
                    'nama' => $dinas->nama
                ];
            }
        } else {
            $puskesmas = Puskesmas::where('nama', $user->nama_puskesmas)->first();
            if ($puskesmas) {
                $additionalData['puskesmas'] = [
                    'id' => $puskesmas->id,
                    'kode' => $puskesmas->kode,
                    'nama' => $puskesmas->nama,
                    'dinas_id' => $puskesmas->dinas_id
                ];
            }
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'roles' => $roles,
                'is_admin' => $user->isadmin,
                'additional_data' => $additionalData
            ]
        ]);
    }
}