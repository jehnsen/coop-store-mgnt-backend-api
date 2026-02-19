<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\CreateBranchRequest;
use App\Http\Requests\Settings\CreateUserRequest;
use App\Http\Requests\Settings\UpdateBranchRequest;
use App\Http\Requests\Settings\UpdateCreditSettingsRequest;
use App\Http\Requests\Settings\UpdateReceiptTemplateRequest;
use App\Http\Requests\Settings\UpdateStoreProfileRequest;
use App\Http\Requests\Settings\UpdateTaxSettingsRequest;
use App\Http\Requests\Settings\UpdateUserRequest;
use App\Http\Resources\BranchSettingsResource;
use App\Http\Resources\StoreSettingsResource;
use App\Http\Resources\UserSettingsResource;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    // ==================== STORE PROFILE MANAGEMENT ====================

    /**
     * Get current store settings with all configuration
     */
    public function getStoreProfile(): JsonResponse
    {
        try {
            $user = auth()->user();

            $settings = [
                'store' => [
                    'name' => config('settings.store_name', 'JM Hardware & Construction Supply'),
                    'address' => config('settings.store_address', ''),
                    'city' => config('settings.store_city', ''),
                    'province' => config('settings.store_province', ''),
                    'postal_code' => config('settings.store_postal_code', ''),
                    'phone' => config('settings.store_phone', ''),
                    'email' => config('settings.store_email', ''),
                    'website' => config('settings.store_website', ''),
                    'tin' => config('settings.store_tin', ''),
                    'bir_permit' => config('settings.store_bir_permit', ''),
                    'vat_registered' => config('settings.vat_registered', true),
                    'logo_url' => $this->getLogoUrl(),
                ],
                'stats' => [
                    'total_users' => User::where('store_id', $user->store_id)->count(),
                    'active_users' => User::where('store_id', $user->store_id)->where('is_active', true)->count(),
                    'total_branches' => Branch::where('store_id', $user->store_id)->count(),
                    'storage_used' => $this->getStorageUsage(),
                ],
                'created_at' => $user->store->created_at ?? now(),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve store profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update store profile details
     */
    public function updateStoreProfile(UpdateStoreProfileRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            // Update settings in database (assuming settings table or config)
            foreach ($validated as $key => $value) {
                $configKey = 'settings.store_' . $key;
                DB::table('settings')->updateOrInsert(
                    ['key' => $configKey, 'store_id' => $user->store_id],
                    ['value' => $value, 'updated_at' => now()]
                );
            }

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'update_store_profile',
                'description' => 'Updated store profile settings',
                'properties' => json_encode($validated),
                'ip_address' => request()->ip(),
            ]);

            // Clear cache
            Cache::tags(['settings', "store_{$user->store_id}"])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Store profile updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update store profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload store logo
     */
    public function uploadStoreLogo(Request $request): JsonResponse
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        try {
            $user = auth()->user();
            $file = $request->file('logo');

            // Delete old logo if exists
            $oldLogo = config('settings.store_logo');
            if ($oldLogo && Storage::disk('public')->exists($oldLogo)) {
                Storage::disk('public')->delete($oldLogo);
            }

            // Store new logo
            $filename = 'store_logo_' . $user->store_id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('logos', $filename, 'public');

            // Update settings
            DB::table('settings')->updateOrInsert(
                ['key' => 'settings.store_logo', 'store_id' => $user->store_id],
                ['value' => $path, 'updated_at' => now()]
            );

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'upload_store_logo',
                'description' => 'Uploaded store logo',
                'ip_address' => request()->ip(),
            ]);

            Cache::tags(['settings', "store_{$user->store_id}"])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Logo uploaded successfully',
                'data' => [
                    'logo_url' => Storage::url($path)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload logo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete store logo
     */
    public function deleteStoreLogo(): JsonResponse
    {
        try {
            $user = auth()->user();
            $logo = config('settings.store_logo');

            if ($logo && Storage::disk('public')->exists($logo)) {
                Storage::disk('public')->delete($logo);
            }

            DB::table('settings')
                ->where('key', 'settings.store_logo')
                ->where('store_id', $user->store_id)
                ->delete();

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'delete_store_logo',
                'description' => 'Deleted store logo',
                'ip_address' => request()->ip(),
            ]);

            Cache::tags(['settings', "store_{$user->store_id}"])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Logo deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete logo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== USER MANAGEMENT ====================

    /**
     * List all users with pagination and filters
     */
    public function listUsers(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search');
            $role = $request->input('role');
            $isActive = $request->input('is_active');

            $query = User::where('store_id', $user->store_id)
                ->with(['branch']);

            // Apply search
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            }

            // Apply filters
            if ($role) {
                $query->where('role', $role);
            }

            if ($isActive !== null) {
                $query->where('is_active', filter_var($isActive, FILTER_VALIDATE_BOOLEAN));
            }

            $users = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => UserSettingsResource::collection($users),
                'meta' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new user
     */
    public function createUser(CreateUserRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            // Prevent non-owners from creating owners
            if ($validated['role'] === 'owner' && $user->role !== 'owner') {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to create owner accounts'
                ], 403);
            }

            $newUser = User::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'branch_id' => $validated['branch_id'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Set default permissions based on role
            $this->assignDefaultPermissions($newUser);

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'create_user',
                'description' => "Created user: {$newUser->name} ({$newUser->role})",
                'properties' => json_encode(['user_id' => $newUser->id, 'role' => $newUser->role]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => new UserSettingsResource($newUser)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user details
     */
    public function getUserDetails(string $uuid): JsonResponse
    {
        try {
            $authUser = auth()->user();
            $user = User::where('uuid', $uuid)
                ->where('store_id', $authUser->store_id)
                ->with(['branch'])
                ->firstOrFail();

            // Get user permissions
            $permissions = $this->getUserPermissionsList($user);

            // Get recent activity
            $recentActivity = ActivityLog::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => new UserSettingsResource($user),
                    'permissions' => $permissions,
                    'recent_activity' => $recentActivity
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user details',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update user details
     */
    public function updateUser(UpdateUserRequest $request, string $uuid): JsonResponse
    {
        try {
            $authUser = auth()->user();
            $user = User::where('uuid', $uuid)
                ->where('store_id', $authUser->store_id)
                ->firstOrFail();

            $validated = $request->validated();

            // Prevent non-owners from modifying owner accounts
            if ($user->role === 'owner' && $authUser->role !== 'owner') {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to modify owner accounts'
                ], 403);
            }

            // Prevent non-owners from assigning owner role
            if (isset($validated['role']) && $validated['role'] === 'owner' && $authUser->role !== 'owner') {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to assign owner role'
                ], 403);
            }

            $user->update($validated);

            // Update permissions if role changed
            if (isset($validated['role']) && $user->wasChanged('role')) {
                $this->assignDefaultPermissions($user);
            }

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $authUser->store_id,
                'user_id' => $authUser->id,
                'action' => 'update_user',
                'description' => "Updated user: {$user->name}",
                'properties' => json_encode(['user_id' => $user->id, 'changes' => $validated]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => new UserSettingsResource($user->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Deactivate user
     */
    public function deactivateUser(string $uuid): JsonResponse
    {
        try {
            $authUser = auth()->user();
            $user = User::where('uuid', $uuid)
                ->where('store_id', $authUser->store_id)
                ->firstOrFail();

            // Prevent deactivating owners
            if ($user->role === 'owner') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot deactivate owner accounts'
                ], 403);
            }

            // Prevent self-deactivation
            if ($user->id === $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot deactivate your own account'
                ], 403);
            }

            $user->update(['is_active' => false]);

            // Revoke all tokens
            $user->tokens()->delete();

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $authUser->store_id,
                'user_id' => $authUser->id,
                'action' => 'deactivate_user',
                'description' => "Deactivated user: {$user->name}",
                'properties' => json_encode(['user_id' => $user->id]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deactivated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Activate user
     */
    public function activateUser(string $uuid): JsonResponse
    {
        try {
            $authUser = auth()->user();
            $user = User::where('uuid', $uuid)
                ->where('store_id', $authUser->store_id)
                ->firstOrFail();

            $user->update(['is_active' => true]);

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $authUser->store_id,
                'user_id' => $authUser->id,
                'action' => 'activate_user',
                'description' => "Activated user: {$user->name}",
                'properties' => json_encode(['user_id' => $user->id]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User activated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Soft delete user
     */
    public function deleteUser(string $uuid): JsonResponse
    {
        try {
            $authUser = auth()->user();
            $user = User::where('uuid', $uuid)
                ->where('store_id', $authUser->store_id)
                ->firstOrFail();

            // Prevent deleting owners
            if ($user->role === 'owner') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete owner accounts'
                ], 403);
            }

            // Prevent self-deletion
            if ($user->id === $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete your own account'
                ], 403);
            }

            // Check for dependencies (transactions, etc.)
            $hasTransactions = DB::table('sales')->where('user_id', $user->id)->exists();
            if ($hasTransactions) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete user with existing transactions. Consider deactivating instead.'
                ], 422);
            }

            $userName = $user->name;
            $user->delete();

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $authUser->store_id,
                'user_id' => $authUser->id,
                'action' => 'delete_user',
                'description' => "Deleted user: {$userName}",
                'properties' => json_encode(['user_id' => $user->id]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete user',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset user password (admin action)
     */
    public function resetUserPassword(string $uuid, Request $request): JsonResponse
    {
        $request->validate([
            'new_password' => 'required|string|min:8|confirmed'
        ]);

        try {
            $authUser = auth()->user();
            $user = User::where('uuid', $uuid)
                ->where('store_id', $authUser->store_id)
                ->firstOrFail();

            // Prevent non-owners from resetting owner passwords
            if ($user->role === 'owner' && $authUser->role !== 'owner') {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to reset owner passwords'
                ], 403);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            // Revoke all tokens to force re-login
            $user->tokens()->delete();

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $authUser->store_id,
                'user_id' => $authUser->id,
                'action' => 'reset_user_password',
                'description' => "Reset password for user: {$user->name}",
                'properties' => json_encode(['user_id' => $user->id]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password reset successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== BRANCH MANAGEMENT ====================

    /**
     * List all branches
     */
    public function listBranches(): JsonResponse
    {
        try {
            $user = auth()->user();
            $branches = Branch::where('store_id', $user->store_id)
                ->withCount('users')
                ->orderBy('is_main', 'desc')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => BranchSettingsResource::collection($branches)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve branches',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new branch
     */
    public function createBranch(CreateBranchRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            // If setting as main, unset other main branches
            if ($validated['is_main'] ?? false) {
                Branch::where('store_id', $user->store_id)
                    ->update(['is_main' => false]);
            }

            $branch = Branch::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'name' => $validated['name'],
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'is_main' => $validated['is_main'] ?? false,
                'is_active' => $validated['is_active'] ?? true,
            ]);

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'create_branch',
                'description' => "Created branch: {$branch->name}",
                'properties' => json_encode(['branch_id' => $branch->id]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch created successfully',
                'data' => new BranchSettingsResource($branch)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update branch details
     */
    public function updateBranch(UpdateBranchRequest $request, string $uuid): JsonResponse
    {
        try {
            $user = auth()->user();
            $branch = Branch::where('uuid', $uuid)
                ->where('store_id', $user->store_id)
                ->firstOrFail();

            $validated = $request->validated();

            // If setting as main, unset other main branches
            if (isset($validated['is_main']) && $validated['is_main']) {
                Branch::where('store_id', $user->store_id)
                    ->where('id', '!=', $branch->id)
                    ->update(['is_main' => false]);
            }

            $branch->update($validated);

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'update_branch',
                'description' => "Updated branch: {$branch->name}",
                'properties' => json_encode(['branch_id' => $branch->id, 'changes' => $validated]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch updated successfully',
                'data' => new BranchSettingsResource($branch->fresh())
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete branch
     */
    public function deleteBranch(string $uuid): JsonResponse
    {
        try {
            $user = auth()->user();
            $branch = Branch::where('uuid', $uuid)
                ->where('store_id', $user->store_id)
                ->firstOrFail();

            // Prevent deleting main branch
            if ($branch->is_main) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete main branch'
                ], 403);
            }

            // Check for dependencies
            $hasUsers = User::where('branch_id', $branch->id)->exists();
            if ($hasUsers) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete branch with assigned users. Reassign users first.'
                ], 422);
            }

            $branchName = $branch->name;
            $branch->delete();

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'delete_branch',
                'description' => "Deleted branch: {$branchName}",
                'properties' => json_encode(['branch_id' => $branch->id]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete branch',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== PERMISSION MANAGEMENT ====================

    /**
     * Get all available permissions grouped by module
     */
    public function getPermissions(): JsonResponse
    {
        $permissions = [
            'products' => [
                'products.view' => 'View products',
                'products.create' => 'Create products',
                'products.edit' => 'Edit products',
                'products.delete' => 'Delete products',
                'products.adjust_stock' => 'Adjust stock levels',
            ],
            'sales' => [
                'sales.view' => 'View sales',
                'sales.create' => 'Create sales',
                'sales.void' => 'Void sales',
                'sales.refund' => 'Process refunds',
            ],
            'customers' => [
                'customers.view' => 'View customers',
                'customers.create' => 'Create customers',
                'customers.edit' => 'Edit customers',
                'customers.delete' => 'Delete customers',
                'customers.manage_credit' => 'Manage customer credit',
            ],
            'inventory' => [
                'inventory.view' => 'View inventory',
                'inventory.receive' => 'Receive inventory',
                'inventory.adjust' => 'Adjust inventory',
                'inventory.transfer' => 'Transfer inventory',
            ],
            'reports' => [
                'reports.view_sales' => 'View sales reports',
                'reports.view_inventory' => 'View inventory reports',
                'reports.view_credit' => 'View credit reports',
                'reports.export' => 'Export reports',
            ],
            'settings' => [
                'settings.manage_store' => 'Manage store settings',
                'settings.manage_users' => 'Manage users',
                'settings.manage_branches' => 'Manage branches',
                'settings.manage_permissions' => 'Manage permissions',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }

    /**
     * Get user's permission list
     */
    public function getUserPermissions(int $userId): JsonResponse
    {
        try {
            $authUser = auth()->user();
            $user = User::where('id', $userId)
                ->where('store_id', $authUser->store_id)
                ->firstOrFail();

            $permissions = $this->getUserPermissionsList($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'user_id' => $user->id,
                    'role' => $user->role,
                    'permissions' => $permissions
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user permissions',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update user permissions
     */
    public function updateUserPermissions(int $userId, Request $request): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string'
        ]);

        try {
            $authUser = auth()->user();
            $user = User::where('id', $userId)
                ->where('store_id', $authUser->store_id)
                ->firstOrFail();

            // Store permissions in database (assuming user_permissions table)
            DB::table('user_permissions')->where('user_id', $user->id)->delete();

            foreach ($request->permissions as $permission) {
                DB::table('user_permissions')->insert([
                    'user_id' => $user->id,
                    'permission' => $permission,
                    'created_at' => now(),
                ]);
            }

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $authUser->store_id,
                'user_id' => $authUser->id,
                'action' => 'update_user_permissions',
                'description' => "Updated permissions for user: {$user->name}",
                'properties' => json_encode(['user_id' => $user->id, 'permissions' => $request->permissions]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User permissions updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update user permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get role default permissions
     */
    public function getRolePermissions(string $role): JsonResponse
    {
        $permissions = $this->getDefaultPermissionsForRole($role);

        return response()->json([
            'success' => true,
            'data' => [
                'role' => $role,
                'permissions' => $permissions
            ]
        ]);
    }

    /**
     * Update role template permissions
     */
    public function updateRolePermissions(string $role, Request $request): JsonResponse
    {
        $request->validate([
            'permissions' => 'required|array',
            'permissions.*' => 'string'
        ]);

        try {
            $authUser = auth()->user();

            // Store role permissions template
            DB::table('role_permissions')->updateOrInsert(
                ['store_id' => $authUser->store_id, 'role' => $role],
                ['permissions' => json_encode($request->permissions), 'updated_at' => now()]
            );

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $authUser->store_id,
                'user_id' => $authUser->id,
                'action' => 'update_role_permissions',
                'description' => "Updated default permissions for role: {$role}",
                'properties' => json_encode(['role' => $role, 'permissions' => $request->permissions]),
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role permissions updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update role permissions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== PAYMENT METHODS CONFIGURATION ====================

    /**
     * Get configured payment methods
     */
    public function getPaymentMethods(): JsonResponse
    {
        try {
            $user = auth()->user();

            $methods = [
                'cash' => [
                    'enabled' => true,
                    'name' => 'Cash',
                ],
                'gcash' => [
                    'enabled' => config('settings.payment_gcash_enabled', false),
                    'name' => 'GCash',
                    'api_key_set' => !empty(config('settings.payment_gcash_api_key')),
                ],
                'maya' => [
                    'enabled' => config('settings.payment_maya_enabled', false),
                    'name' => 'Maya (PayMaya)',
                    'api_key_set' => !empty(config('settings.payment_maya_api_key')),
                ],
                'card' => [
                    'enabled' => config('settings.payment_card_enabled', false),
                    'name' => 'Credit/Debit Card',
                ],
                'bank_transfer' => [
                    'enabled' => config('settings.payment_bank_enabled', false),
                    'name' => 'Bank Transfer',
                ],
                'credit' => [
                    'enabled' => config('settings.payment_credit_enabled', true),
                    'name' => 'Credit Account',
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $methods
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update payment methods configuration
     */
    public function updatePaymentMethods(Request $request): JsonResponse
    {
        $request->validate([
            'gcash_enabled' => 'boolean',
            'gcash_api_key' => 'nullable|string',
            'maya_enabled' => 'boolean',
            'maya_api_key' => 'nullable|string',
            'card_enabled' => 'boolean',
            'bank_enabled' => 'boolean',
            'credit_enabled' => 'boolean',
        ]);

        try {
            $user = auth()->user();
            $settings = [];

            // Update each payment method setting
            if ($request->has('gcash_enabled')) {
                $settings['payment_gcash_enabled'] = $request->gcash_enabled;
            }
            if ($request->has('gcash_api_key')) {
                $settings['payment_gcash_api_key'] = Crypt::encryptString($request->gcash_api_key);
            }
            if ($request->has('maya_enabled')) {
                $settings['payment_maya_enabled'] = $request->maya_enabled;
            }
            if ($request->has('maya_api_key')) {
                $settings['payment_maya_api_key'] = Crypt::encryptString($request->maya_api_key);
            }
            if ($request->has('card_enabled')) {
                $settings['payment_card_enabled'] = $request->card_enabled;
            }
            if ($request->has('bank_enabled')) {
                $settings['payment_bank_enabled'] = $request->bank_enabled;
            }
            if ($request->has('credit_enabled')) {
                $settings['payment_credit_enabled'] = $request->credit_enabled;
            }

            foreach ($settings as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['key' => "settings.{$key}", 'store_id' => $user->store_id],
                    ['value' => $value, 'updated_at' => now()]
                );
            }

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'update_payment_methods',
                'description' => 'Updated payment methods configuration',
                'ip_address' => request()->ip(),
            ]);

            Cache::tags(['settings', "store_{$user->store_id}"])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Payment methods updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payment methods',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== RECEIPT TEMPLATE ====================

    /**
     * Get receipt template settings
     */
    public function getReceiptTemplate(): JsonResponse
    {
        try {
            $user = auth()->user();

            $template = [
                'header_text' => config('settings.receipt_header', 'Thank you for shopping with us!'),
                'footer_text' => config('settings.receipt_footer', 'This serves as your official receipt.'),
                'show_logo' => config('settings.receipt_show_logo', true),
                'paper_width' => config('settings.receipt_paper_width', 80),
                'show_bir_info' => config('settings.receipt_show_bir', true),
                'show_cashier' => config('settings.receipt_show_cashier', true),
                'show_customer' => config('settings.receipt_show_customer', true),
            ];

            return response()->json([
                'success' => true,
                'data' => $template
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve receipt template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update receipt template
     */
    public function updateReceiptTemplate(UpdateReceiptTemplateRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            foreach ($validated as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['key' => "settings.receipt_{$key}", 'store_id' => $user->store_id],
                    ['value' => $value, 'updated_at' => now()]
                );
            }

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'update_receipt_template',
                'description' => 'Updated receipt template settings',
                'ip_address' => request()->ip(),
            ]);

            Cache::tags(['settings', "store_{$user->store_id}"])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Receipt template updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update receipt template',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview receipt with current template
     */
    public function previewReceipt(): JsonResponse
    {
        try {
            $user = auth()->user();

            // Generate sample receipt data
            $receipt = [
                'store_name' => config('settings.store_name'),
                'store_address' => config('settings.store_address'),
                'tin' => config('settings.store_tin'),
                'receipt_number' => 'SAMPLE-001',
                'date' => now()->format('Y-m-d H:i:s'),
                'cashier' => $user->name,
                'items' => [
                    ['name' => 'Sample Product 1', 'qty' => 2, 'price' => 150.00, 'total' => 300.00],
                    ['name' => 'Sample Product 2', 'qty' => 1, 'price' => 250.00, 'total' => 250.00],
                ],
                'subtotal' => 550.00,
                'tax' => 66.00,
                'total' => 616.00,
                'header_text' => config('settings.receipt_header'),
                'footer_text' => config('settings.receipt_footer'),
            ];

            return response()->json([
                'success' => true,
                'data' => $receipt
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate receipt preview',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== TAX SETTINGS ====================

    /**
     * Get tax settings
     */
    public function getTaxSettings(): JsonResponse
    {
        try {
            $settings = [
                'vat_rate' => config('settings.vat_rate', 12),
                'vat_inclusive' => config('settings.vat_inclusive', true),
                'is_bmbe' => config('settings.is_bmbe', false),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update tax settings
     */
    public function updateTaxSettings(UpdateTaxSettingsRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            foreach ($validated as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['key' => "settings.{$key}", 'store_id' => $user->store_id],
                    ['value' => $value, 'updated_at' => now()]
                );
            }

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'update_tax_settings',
                'description' => 'Updated tax settings',
                'properties' => json_encode($validated),
                'ip_address' => request()->ip(),
            ]);

            Cache::tags(['settings', "store_{$user->store_id}"])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Tax settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tax settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== CREDIT SETTINGS ====================

    /**
     * Get credit default settings
     */
    public function getCreditSettings(): JsonResponse
    {
        try {
            $settings = [
                'default_credit_limit' => config('settings.default_credit_limit', 50000),
                'default_terms_days' => config('settings.default_terms_days', 30),
                'reminder_days_before' => config('settings.reminder_days_before', 3),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve credit settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update credit settings
     */
    public function updateCreditSettings(UpdateCreditSettingsRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $validated = $request->validated();

            foreach ($validated as $key => $value) {
                DB::table('settings')->updateOrInsert(
                    ['key' => "settings.{$key}", 'store_id' => $user->store_id],
                    ['value' => $value, 'updated_at' => now()]
                );
            }

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'update_credit_settings',
                'description' => 'Updated credit default settings',
                'properties' => json_encode($validated),
                'ip_address' => request()->ip(),
            ]);

            Cache::tags(['settings', "store_{$user->store_id}"])->flush();

            return response()->json([
                'success' => true,
                'message' => 'Credit settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update credit settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== SYSTEM SETTINGS ====================

    /**
     * Get system settings
     */
    public function getSystemSettings(): JsonResponse
    {
        try {
            $settings = [
                'app_version' => config('app.version', '1.0.0'),
                'timezone' => config('settings.timezone', 'Asia/Manila'),
                'currency' => config('settings.currency', 'PHP'),
                'date_format' => config('settings.date_format', 'Y-m-d'),
                'time_format' => config('settings.time_format', 'H:i:s'),
                'number_format' => config('settings.number_format', 'en_PH'),
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve system settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update system settings
     */
    public function updateSystemSettings(Request $request): JsonResponse
    {
        $request->validate([
            'timezone' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'date_format' => 'nullable|string',
            'time_format' => 'nullable|string',
            'number_format' => 'nullable|string',
        ]);

        try {
            $user = auth()->user();
            $validated = $request->only(['timezone', 'currency', 'date_format', 'time_format', 'number_format']);

            foreach ($validated as $key => $value) {
                if ($value !== null) {
                    DB::table('settings')->updateOrInsert(
                        ['key' => "settings.{$key}", 'store_id' => $user->store_id],
                        ['value' => $value, 'updated_at' => now()]
                    );
                }
            }

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'update_system_settings',
                'description' => 'Updated system settings',
                'properties' => json_encode($validated),
                'ip_address' => request()->ip(),
            ]);

            Cache::tags(['settings', "store_{$user->store_id}"])->flush();

            return response()->json([
                'success' => true,
                'message' => 'System settings updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update system settings',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear application cache
     */
    public function clearCache(): JsonResponse
    {
        try {
            $user = auth()->user();

            Artisan::call('cache:clear');
            Artisan::call('config:clear');
            Artisan::call('route:clear');

            // Log activity
            ActivityLog::create([
                'uuid' => Str::uuid(),
                'store_id' => $user->store_id,
                'user_id' => $user->id,
                'action' => 'clear_cache',
                'description' => 'Cleared application cache',
                'ip_address' => request()->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Cache cleared successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear cache',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Get logo URL
     */
    private function getLogoUrl(): ?string
    {
        $logo = config('settings.store_logo');
        return $logo ? Storage::url($logo) : null;
    }

    /**
     * Get storage usage (in MB)
     */
    private function getStorageUsage(): float
    {
        try {
            $size = 0;
            $path = storage_path('app/public');

            if (is_dir($path)) {
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path)) as $file) {
                    if ($file->isFile()) {
                        $size += $file->getSize();
                    }
                }
            }

            return round($size / 1024 / 1024, 2); // Convert to MB
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Assign default permissions based on role
     */
    private function assignDefaultPermissions(User $user): void
    {
        $permissions = $this->getDefaultPermissionsForRole($user->role);

        DB::table('user_permissions')->where('user_id', $user->id)->delete();

        foreach ($permissions as $permission) {
            DB::table('user_permissions')->insert([
                'user_id' => $user->id,
                'permission' => $permission,
                'created_at' => now(),
            ]);
        }
    }

    /**
     * Get default permissions for role
     */
    private function getDefaultPermissionsForRole(string $role): array
    {
        $defaults = [
            'owner' => [
                'products.*', 'sales.*', 'customers.*', 'inventory.*',
                'reports.*', 'settings.*'
            ],
            'manager' => [
                'products.*', 'sales.*', 'customers.*', 'inventory.*',
                'reports.view_sales', 'reports.view_inventory', 'reports.view_credit', 'reports.export'
            ],
            'cashier' => [
                'products.view', 'sales.view', 'sales.create',
                'customers.view', 'reports.view_sales'
            ],
            'inventory_staff' => [
                'products.view', 'products.edit', 'products.adjust_stock',
                'inventory.*', 'reports.view_inventory'
            ],
        ];

        return $defaults[$role] ?? [];
    }

    /**
     * Get user's permissions list
     */
    private function getUserPermissionsList(User $user): array
    {
        if ($user->role === 'owner') {
            return ['*']; // Owner has all permissions
        }

        $permissions = DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->pluck('permission')
            ->toArray();

        return $permissions ?: $this->getDefaultPermissionsForRole($user->role);
    }
}
