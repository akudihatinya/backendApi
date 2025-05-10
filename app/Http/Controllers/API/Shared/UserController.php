<?php

namespace App\Http\Controllers\API\Shared;

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
use Carbon\Carbon;
use Illuminate\Support\Facades\Cookie;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Filter based on is_admin parameter
        if ($request->has('is_admin')) {
            $isAdmin = filter_var($request->is_admin, FILTER_VALIDATE_BOOLEAN);

            if ($isAdmin) {
                $query->where('role', 'admin');
            } else {
                $query->where('role', '!=', 'admin');
            }
        }

        // Set pagination per page
        $perPage = 10;
        if ($request->has('per_page')) {
            $requestedPerPage = (int)$request->per_page;
            if (in_array($requestedPerPage, [10, 25, 100])) {
                $perPage = $requestedPerPage;
            }
        }

        // Load puskesmas relationship
        $users = $query->with(['puskesmas' => function ($query) {
            // Load puskesmas relationship
        }])->paginate($perPage);

        return response()->json([
            'users' => UserResource::collection($users),
            'pagination' => [
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
                'next_page_url' => $users->nextPageUrl(),
                'prev_page_url' => $users->previousPageUrl(),
            ],
            'filter' => $request->has('is_admin') ? ($request->is_admin ? 'admin' : 'non-admin') : 'all'
        ]);
    }

    /**
     * Display the specified user
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Store a newly created user
     */
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

            return response()->json([
                'message' => 'User puskesmas berhasil ditambahkan',
                'user' => new UserResource($user),
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

    /**
     * Update the specified user
     */
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

    /**
     * Reset password for specified user
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke all active tokens for this user
        $user->tokens()->delete();

        // Delete all refresh tokens
        $user->refreshTokens()->delete();

        return response()->json([
            'message' => 'Password berhasil direset',
        ]);
    }

    /**
     * Remove the specified user
     */
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

        // Delete all refresh tokens
        if (method_exists($user, 'refreshTokens')) {
            $user->refreshTokens()->delete();
        } else {
            UserRefreshToken::where('user_id', $user->id)->delete();
        }

        // Delete all access tokens
        $user->tokens()->delete();

        // Delete user
        $user->delete();

        return response()->json([
            'message' => "User {$userName} berhasil dihapus",
        ]);
    }

    /**
     * Get authenticated user information
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    /**
     * Update authenticated user profile
     */
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
