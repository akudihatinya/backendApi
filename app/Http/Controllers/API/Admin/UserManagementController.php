<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Puskesmas;
use App\Events\UserCreated;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class UserManagementController extends Controller
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
        $users = $query->with('puskesmas')->paginate($perPage);

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
     * Store a newly created user
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $plainPassword = $data['password']; // Store original password to send to user
        $data['password'] = Hash::make($data['password']);

        try {
            DB::beginTransaction();

            // Create user
            $user = User::create([
                'username' => $data['username'],
                'password' => $data['password'],
                'name' => $data['name'],
                'profile_picture' => $data['profile_picture'] ?? null,
                'role' => $data['role'],
            ]);

            // If user is puskesmas, create puskesmas entry
            if ($data['role'] === 'puskesmas') {
                $puskesmas = Puskesmas::create([
                    'user_id' => $user->id,
                    'name' => $data['name'],
                ]);
                
                // Update user with puskesmas_id
                $user->puskesmas_id = $puskesmas->id;
                $user->save();
            }

            DB::commit();

            // Fire user created event to send email notification
            event(new UserCreated($user, $plainPassword));

            return response()->json([
                'message' => 'User berhasil ditambahkan',
                'user' => new UserResource($user),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating user: ' . $e->getMessage());
            return response()->json([
                'message' => 'Gagal menambahkan user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified user
     */
    public function show(User $user): JsonResponse
    {
        $user->load('puskesmas');
        
        return response()->json([
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        // Only hash password if it's provided
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        // Handle profile picture if provided
        if ($request->hasFile('profile_picture')) {
            if ($user->profile_picture) {
                // Delete old image
                Storage::delete('public/' . $user->profile_picture);
            }
            $data['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        }

        $user->update($data);

        // If this is a puskesmas user, update puskesmas name as well
        if ($user->isPuskesmas() && $user->puskesmas && isset($data['name'])) {
            $user->puskesmas->update(['name' => $data['name']]);
        }

        return response()->json([
            'message' => 'User berhasil diupdate',
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Remove the specified user
     */
    public function destroy(User $user): JsonResponse
    {
        if ($user->role === 'admin') {
            return response()->json([
                'message' => 'Tidak dapat menghapus user admin',
            ], 403);
        }

        try {
            DB::beginTransaction();
            
            // If this is a puskesmas user, delete the related puskesmas
            if ($user->isPuskesmas() && $user->puskesmas) {
                $user->puskesmas->delete();
            }
            
            // Delete profile picture if exists
            if ($user->profile_picture) {
                Storage::delete('public/' . $user->profile_picture);
            }
            
            // Delete user
            $user->delete();
            
            DB::commit();
            
            return response()->json([
                'message' => 'User berhasil dihapus',
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting user: ' . $e->getMessage());
            
            return response()->json([
                'message' => 'Gagal menghapus user',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset password for a user
     */
    public function resetPassword(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'password' => 'required|string|min:8|confirmed',
        ]);

        $plainPassword = $request->password;
        $user->password = Hash::make($plainPassword);
        $user->save();

        // Revoke all tokens
        $user->tokens()->delete();
        $user->refreshTokens()->delete();

        // Notify user about password reset
        event(new UserCreated($user, $plainPassword));

        return response()->json([
            'message' => 'Password berhasil direset',
        ]);
    }
}