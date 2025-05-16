<?php

namespace App\Http\Controllers\API\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class UserProfileController extends Controller
{
    /**
     * Get current user profile
     */
    public function show(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        // If user is puskesmas, load the puskesmas relationship
        if ($user->isPuskesmas()) {
            $user->load('puskesmas');
        }
        
        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
    
    /**
     * Update current user profile
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = Auth::user();
        $data = $request->validated();
        
        // Handle profile picture if provided
        if ($request->hasFile('profile_picture')) {
            // Delete old image if exists
            if ($user->profile_picture) {
                Storage::delete('public/' . $user->profile_picture);
            }
            
            // Store new image
            $data['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        }
        
        // Update user
        $user->update($data);
        
        // If this is a puskesmas user, update puskesmas name as well
        if ($user->isPuskesmas() && $user->puskesmas && isset($data['name'])) {
            $user->puskesmas->update(['name' => $data['name']]);
        }
        
        // Refresh user with updated data
        $user->refresh();
        
        // If user is puskesmas, load the puskesmas relationship
        if ($user->isPuskesmas()) {
            $user->load('puskesmas');
        }
        
        return response()->json([
            'message' => 'Profil berhasil diperbarui',
            'user' => new UserResource($user),
        ]);
    }
    
    /**
     * Update user password
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => 'required|string|current_password',
            'password' => 'required|string|min:8|confirmed',
        ]);
        
        $user = Auth::user();
        $user->password = Hash::make($request->password);
        $user->save();
        
        // Revoke all tokens except current one
        $currentTokenId = $request->user()->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        
        return response()->json([
            'message' => 'Password berhasil diperbarui',
        ]);
    }
}