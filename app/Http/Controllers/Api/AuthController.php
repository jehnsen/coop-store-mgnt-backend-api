<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class AuthController extends Controller
{
    use ApiResponse;

    /**
     * Handle user login and generate API token.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return $this->errorResponse('Invalid credentials', 401);
        }

        $user = Auth::user();

        if (!$user->is_active) {
            Auth::logout();
            return $this->errorResponse('Your account has been deactivated. Please contact support.', 403);
        }

        // Update last login timestamp
        $user->update(['last_login_at' => now()]);

        // Create Sanctum token
        $token = $user->createToken('api-token')->plainTextToken;

        // Load relationships for response
        $user->load(['store', 'branch']);

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], 'Login successful');
    }

    /**
     * Logout user and revoke current token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->successResponse(null, 'Logged out successfully');
    }

    /**
     * Get authenticated user information.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['store', 'branch']);

        return $this->successResponse(new UserResource($user));
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Handle avatar upload
        if ($request->hasFile('avatar')) {
            // Delete old avatar if exists
            if ($user->avatar_path) {
                \Storage::disk('public')->delete($user->avatar_path);
            }

            // Store new avatar
            $path = $request->file('avatar')->store('avatars', 'public');
            $data['avatar_path'] = $path;
        }

        $user->update($data);
        $user->load(['store', 'branch']);

        return $this->successResponse(new UserResource($user), 'Profile updated successfully');
    }

    /**
     * Change user password.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return $this->errorResponse('Current password is incorrect', 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return $this->successResponse(null, 'Password changed successfully');
    }

    /**
     * Send password reset token.
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Return success even if user doesn't exist (security best practice)
            return $this->successResponse(null, 'If an account exists with this email, you will receive password reset instructions.');
        }

        // Generate reset token
        $token = Str::random(64);

        // Store in password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // TODO: Send email/SMS with reset token
        // For now, return success message
        // In production, you would send the token via email/SMS

        return $this->successResponse(
            ['token' => $token], // Remove this in production
            'If an account exists with this email, you will receive password reset instructions.'
        );
    }

    /**
     * Reset password using token.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        // Find password reset record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$resetRecord) {
            return $this->errorResponse('Invalid or expired reset token', 422);
        }

        // Verify token (tokens expire after 60 minutes)
        if (!Hash::check($request->token, $resetRecord->token)) {
            return $this->errorResponse('Invalid or expired reset token', 422);
        }

        if (Carbon::parse($resetRecord->created_at)->addMinutes(60)->isPast()) {
            return $this->errorResponse('Reset token has expired', 422);
        }

        // Find user and update password
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return $this->errorResponse('User not found', 404);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Delete reset token
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        return $this->successResponse(null, 'Password reset successfully');
    }
}
