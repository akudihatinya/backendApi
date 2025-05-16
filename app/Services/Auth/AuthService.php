<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserAuthService
{
    protected $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Attempt to login a user with username and password
     */
    public function attemptLogin(string $username, string $password): ?User
    {
        // Attempt authentication
        if (!Auth::attempt(['username' => $username, 'password' => $password])) {
            Log::info('Login failed for username: ' . $username);
            return null;
        }
        
        // Return authenticated user
        $user = Auth::user();
        Log::info('User logged in successfully: ' . $user->username . ' (ID: ' . $user->id . ')');
        
        return $user;
    }

    /**
     * Create access and refresh tokens for a user
     */
    public function createTokens(User $user): array
    {
        // Create access token
        $accessToken = $this->tokenService->createAccessToken($user);
        
        // Create refresh token
        $refreshToken = $this->tokenService->createRefreshToken($user);
        
        return [
            'access_token' => $accessToken['token'],
            'refresh_token' => $refreshToken['token'],
            'expires_in' => $accessToken['expires_in'],
        ];
    }

    /**
     * Refresh tokens using a refresh token
     */
    public function refreshToken(string $refreshToken): ?array
    {
        // Validate refresh token and get user
        $user = $this->tokenService->validateRefreshToken($refreshToken);
        
        if (!$user) {
            Log::warning('Invalid refresh token attempted');
            return null;
        }
        
        Log::info('Token refreshed for user: ' . $user->username . ' (ID: ' . $user->id . ')');
        
        // Return refreshed tokens
        return $this->tokenService->refreshTokens($user);
    }

    /**
     * Logout a user by revoking all tokens
     */
    public function logout(User $user): void
    {
        $this->tokenService->revokeAllTokens($user);
        Log::info('User logged out: ' . $user->username . ' (ID: ' . $user->id . ')');
    }

    /**
     * Change user password
     */
    public function changePassword(User $user, string $currentPassword, string $newPassword): bool
    {
        // Verify current password
        if (!Hash::check($currentPassword, $user->password)) {
            Log::warning('Failed password change attempt for user ID: ' . $user->id . ' - Current password mismatch');
            return false;
        }
        
        // Update password
        $user->password = Hash::make($newPassword);
        $user->save();
        
        Log::info('Password changed for user ID: ' . $user->id);
        
        return true;
    }
}