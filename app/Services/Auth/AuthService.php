<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserRefreshToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthService
{
    /**
     * Attempt to authenticate a user with credentials
     */
    public function attemptLogin(string $username, string $password): ?User
    {
        $user = User::where('username', $username)->first();
        
        if (!$user || !Hash::check($password, $user->password)) {
            return null;
        }
        
        return $user;
    }

    /**
     * Create access and refresh tokens for a user
     */
    public function createTokens(User $user): array
    {
        // Generate access token (valid for 1 hour)
        $tokenResult = $user->createToken('access_token');
        $tokenExpiration = config('sanctum.expiration', 60); // Minutes
        
        $accessToken = $tokenResult->plainTextToken;
        
        // Generate refresh token (valid for 30 days)
        $refreshToken = Str::random(100);
        
        // Remove old refresh tokens
        UserRefreshToken::where('user_id', $user->id)->delete();
        
        // Save new refresh token
        UserRefreshToken::create([
            'user_id' => $user->id,
            'refresh_token' => $refreshToken,
            'expires_at' => Carbon::now()->addDays(30),
        ]);
        
        return [
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'expires_in' => $tokenExpiration * 60, // Convert to seconds
        ];
    }

    /**
     * Refresh tokens using a refresh token
     */
    public function refreshToken(string $refreshToken): ?array
    {
        $refreshTokenRecord = UserRefreshToken::where('refresh_token', $refreshToken)
            ->where('expires_at', '>', Carbon::now())
            ->first();
        
        if (!$refreshTokenRecord) {
            return null;
        }
        
        $user = $refreshTokenRecord->user;
        
        if (!$user) {
            return null;
        }
        
        // Revoke existing tokens
        $user->tokens()->delete();
        
        // Generate new tokens
        return $this->createTokens($user);
    }

    /**
     * Log out a user by revoking all tokens
     */
    public function logout(User $user): bool
    {
        // Revoke all tokens
        $user->tokens()->delete();
        
        // Delete refresh tokens
        UserRefreshToken::where('user_id', $user->id)->delete();
        
        return true;
    }

    /**
     * Change user password
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return false;
        }
        
        $user->update([
            'password' => Hash::make($newPassword)
        ]);
        
        return true;
    }
}