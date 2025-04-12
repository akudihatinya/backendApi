<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function index()
    {
        $users = User::where('role', 'puskesmas')
            ->with('puskesmas')
            ->get();
        
        return response()->json([
            'users' => UserResource::collection($users),
        ]);
    }
    
    public function show(User $user)
    {
        return response()->json([
            'user' => new UserResource($user),
        ]);
    }
    
    public function update(UpdateUserRequest $request, User $user)
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
    
    public function resetPassword(Request $request, User $user)
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
}