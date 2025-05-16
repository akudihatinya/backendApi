<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Models\UserRefreshToken;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TokenService
{
    /**
     * Create a new access token for the user
     */
    public function createAccessToken(User $user, string $name = 'api-token'): array
    {
        // Revoke any existing tokens with the same name
        $user->tokens()->where('name', $name)->delete();
        
        // Set expiration time (1 hour)
        $expiration = now()->addHour();
        
        // Create token with expiration
        $token = $user->createToken($name, ['*'], $expiration);
        
        return [
            'token' => $token->plainTextToken,
            'expires_at' => $expiration,
            'expires_in' => 3600, // 1 hour in seconds
        ];
    }

    /**
     * Create a new refresh token for the user
     */
    public function createRefreshToken(User $user): array
    {
        // Generate random secure token
        $refreshToken = Str::random(60);
        
        // Set expiration time (1 week)
        $expiration = now()->addWeek();
        
        // Store refresh token in database
        UserRefreshToken::updateOrCreate(
            ['user_id' => $user->id],
            [
                'refresh_token' => $refreshToken,
                'expires_at' => $expiration,
            ]
        );
        
        return [
            'token' => $refreshToken,
            'expires_at' => $expiration,
            'expires_in' => 604800, // 1 week in seconds
        ];
    }

    /**
     * Validate a refresh token and return the associated user
     */
    public function validateRefreshToken(string $refreshToken): ?User
    {
        $tokenRecord = UserRefreshToken::where('refresh_token', $refreshToken)
            ->where('expires_at', '>', now())
            ->first();
            
        if (!$tokenRecord) {
            return null;
        }
        
        return User::find($tokenRecord->user_id);
    }

    /**
     * Revoke all tokens for a user
     */
    public function revokeAllTokens(User $user): void
    {
        // Revoke all access tokens
        $user->tokens()->delete();
        
        // Delete all refresh tokens
        UserRefreshToken::where('user_id', $user->id)->delete();
    }

    /**
     * Refresh tokens for a user
     */
    public function refreshTokens(User $user): array
    {
        // Revoke old access tokens
        $user->tokens()->delete();
        
        // Create new access token
        $accessToken = $this->createAccessToken($user);
        
        // Create new refresh token
        $refreshToken = $this->createRefreshToken($user);
        
        return [
            'access_token' => $accessToken['token'],
            'refresh_token' => $refreshToken['token'],
            'expires_in' => $accessToken['expires_in'],
        ];
    }
}