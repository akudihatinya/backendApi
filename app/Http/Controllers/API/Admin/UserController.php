<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Display a listing of the users.
     */
    public function index(): JsonResponse
    {
        $users = User::where('role', 'puskesmas')
            ->with('puskesmas')
            ->get();

        return response()->json([
            'users' => UserResource::collection($users),
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (isset($data['password']) && $data['password']) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        if ($request->hasFile('profile_picture')) {
            // Delete old image if exists
            if ($user->profile_picture) {
                Storage::delete('public/' . $user->profile_picture);
            }

            $path = $request->file('profile_picture')->store('profile-pictures', 'public');
            $data['profile_picture'] = $path;
        }

        $user->update($data);

        return response()->json([
            'message' => 'User berhasil diupdate',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Reset user's password.
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all tokens
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil direset',
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user): JsonResponse
    {
        // Pastikan hanya user puskesmas yang bisa dihapus (tidak bisa menghapus admin)
        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Tidak bisa menghapus admin',
            ], 403);
        }

        // Simpan nama user untuk pesan respons
        $userName = $user->name;

        // Hapus foto profil jika ada
        if ($user->profile_picture) {
            Storage::delete('public/' . $user->profile_picture);
        }

        // Hapus user (akan trigger cascade delete untuk relasi puskesmas dll)
        $user->delete();

        return response()->json([
            'message' => "User {$userName} berhasil dihapus",
        ]);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        // Ambil data yang sudah divalidasi
        $data = $request->validated();

        // Debugging
        \Log::info('Request data:', $data);

        // Tangani profile_picture
        if ($request->hasFile('profile_picture')) {
            // Jika berupa file upload
            $path = $request->file('profile_picture')->store('profile-pictures', 'public');
            $data['profile_picture'] = $path;
        } else {
            // Jika string, hapus saja dan set null
            // Kita tidak perlu memparsing URL karena mungkin menyebabkan error
            $data['profile_picture'] = null;
        }

        // Hash password
        $data['password'] = Hash::make($data['password']);

        try {
            DB::beginTransaction();

            // Buat user baru (tanpa profile_picture untuk sementara)
            $user = User::create([
                'username' => $data['username'],
                'password' => $data['password'],
                'name' => $data['name'],
                'profile_picture' => null,
                'role' => $data['role'],
            ]);

            // Buat puskesmas terkait
            $puskesmas = Puskesmas::create([
                'user_id' => $user->id,
                'name' => $data['name'],
            ]);

            DB::commit();

            // Reload user with puskesmas relationship
            $user->load('puskesmas');

            return response()->json([
                'message' => 'User puskesmas berhasil ditambahkan',
                'user' => new UserResource($user),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            // Log error lengkap
            \Log::error('Error creating user: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);

            return response()->json([
                'message' => 'Gagal menambahkan user: ' . $e->getMessage(),
                'debug_info' => app()->environment('local') ? $e->getTraceAsString() : null
            ], 500);
        }
    }
}
