<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\ApiResponse;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Login endpoint
     * POST /api/auth/login
     */
    public function login(LoginRequest $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return ApiResponse::error('Invalid credentials', null, 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Login successful', 200);
    }

    /**
     * Get current user
     * GET /api/auth/user
     */
    public function user()
    {
        return ApiResponse::success(new UserResource(auth()->user()), 'User retrieved');
    }

    /**
     * Logout endpoint
     * POST /api/auth/logout
     */
    public function logout()
    {
        auth()->user()->currentAccessToken()->delete();

        return ApiResponse::success(null, 'Logout successful');
    }

    /**
     * Refresh token endpoint
     * POST /api/auth/refresh
     */
    public function refresh()
    {
        $user = auth()->user();
        // Revoke old token
        $user->currentAccessToken()->delete();
        // Create new token
        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 'Token refreshed');
    }
}
