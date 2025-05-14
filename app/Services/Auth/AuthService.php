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
     * Attempt to login a user
     * 
     * @param string $username
     * @param string $password
     * @return User|false
     */
    public function attemptLogin(string $username, string $password)
    {
        $user = User::where('username', $username)->first();
        
        if (!$user || !Hash::check($password, $user->password)) {
            return false;
        }
        
        return $user;
    }

    /**
     * Create access and refresh tokens for a user
     * 
     * @param User $user
     * @return array
     */
    public function createTokens(User $user)
    {
        // Generate access token (valid for 1 hour)
        $tokenResult = $user->createToken('access_token', ['*'], now()->addHour());
        $accessToken = $tokenResult->plainTextToken;
        
        // Generate refresh token (valid for 30 days)
        $refreshToken = Str::random(60);
        
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
            'expires_in' => 3600, // 1 hour in seconds
        ];
    }

    /**
     * Refresh tokens using a refresh token
     * 
     * @param string $refreshToken
     * @return array|false
     */
    public function refreshToken(string $refreshToken)
    {
        $refreshTokenRecord = UserRefreshToken::where('refresh_token', $refreshToken)
            ->where('expires_at', '>', Carbon::now())
            ->first();
        
        if (!$refreshTokenRecord) {
            return false;
        }
        
        $user = $refreshTokenRecord->user;
        
        if (!$user) {
            return false;
        }
        
        // Revoke all tokens
        $user->tokens()->delete();
        
        // Generate new tokens
        return $this->createTokens($user);
    }

    /**
     * Logout a user by revoking tokens
     * 
     * @param User $user
     * @return bool
     */
    public function logout(User $user)
    {
        // Revoke all tokens
        $user->tokens()->delete();
        
        // Delete refresh tokens
        UserRefreshToken::where('user_id', $user->id)->delete();
        
        return true;
    }

    /**
     * Change user password
     * 
     * @param User $user
     * @param string $currentPassword
     * @param string $newPassword
     * @return bool
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword)
    {
        if (!Hash::check($currentPassword, $user->password)) {
            return false;
        }
        
        $user->password = Hash::make($newPassword);
        $user->save();
        
        return true;
    }
}