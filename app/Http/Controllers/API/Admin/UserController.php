<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Puskesmas;
use App\Models\User;
use App\Models\UserRefreshToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();
        
        // Filter berdasarkan parameter is_admin
        if ($request->has('is_admin')) {
            $isAdmin = filter_var($request->is_admin, FILTER_VALIDATE_BOOLEAN);
            
            if ($isAdmin) {
                // Jika is_admin=1, tampilkan hanya admin
                $query->where('role', 'admin');
            } else {
                // Jika is_admin=0, tampilkan selain admin
                $query->where('role', '!=', 'admin');
            }
        }
        // Jika tidak ada parameter is_admin, tampilkan semua user (termasuk admin)
        
        // Load relationship puskesmas untuk user yang memiliki role puskesmas
        $users = $query->with(['puskesmas' => function($query) {
            // Relationship akan dimuat hanya jika user memiliki relasi puskesmas
        }])->get();

        return response()->json([
            'users' => UserResource::collection($users),
            'count' => $users->count(),
            'filter' => $request->has('is_admin') ? ($request->is_admin ? 'admin' : 'non-admin') : 'all'
        ]);
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('profile_picture')) {
            $data['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        } else {
            $data['profile_picture'] = null;
        }

        $data['password'] = Hash::make($data['password']);

        try {
            DB::beginTransaction();

            $user = User::create([
                'username' => $data['username'],
                'password' => $data['password'],
                'name' => $data['name'],
                'profile_picture' => $data['profile_picture'],
                'role' => $data['role'],
            ]);

            Puskesmas::create([
                'user_id' => $user->id,
                'name' => $data['name'],
            ]);

            DB::commit();

            $user->load('puskesmas');

            // Generate token
            $accessToken = $user->createToken('auth_token')->plainTextToken;
            $refreshToken = Str::random(60);

            UserRefreshToken::create([
                'user_id' => $user->id,
                'refresh_token' => $refreshToken,
                'expires_at' => now()->addDays(30),
            ]);

            return response()->json([
                'message' => 'User puskesmas berhasil ditambahkan',
                'user' => new UserResource($user),
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'token_type' => 'Bearer',
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            \Log::error('Error creating user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data,
            ]);

            return response()->json([
                'message' => 'Gagal menambahkan user',
                'debug_info' => app()->environment('local') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture) {
                Storage::delete('public/' . $user->profile_picture);
            }

            $data['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        }

        $user->update($data);

        return response()->json([
            'message' => 'User berhasil diupdate',
            'user' => new UserResource($user),
        ]);
    }

    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil direset',
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Tidak bisa menghapus admin',
            ], 403);
        }

        $userName = $user->name;

        if ($user->profile_picture) {
            Storage::delete('public/' . $user->profile_picture);
        }

        $user->delete();

        return response()->json([
            'message' => "User {$userName} berhasil dihapus",
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function updateMe(UpdateUserRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture) {
                Storage::delete('public/' . $user->profile_picture);
            }

            $data['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        }

        $user->update($data);

        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user' => new UserResource($user),
        ]);
    }
}