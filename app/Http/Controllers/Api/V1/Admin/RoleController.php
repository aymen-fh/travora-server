<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Staff\SyncRolePermissions;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SyncPermissionsRequest;
use App\Http\Responses\ApiResponse;
use App\Support\Rbac\RolePermissionMatrix;
use App\Support\Rbac\StaffAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

final class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        $counts = $this->userCounts();

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', RolePermissionMatrix::staffRoleNames())
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => $this->payload($role, $counts[$role->id] ?? 0));

        return ApiResponse::success($roles);
    }

    public function show(Role $role, StaffAccess $access): JsonResponse
    {
        $access->assertCanEditRole(request()->user(), $role);
        $role->load('permissions:id,name');

        return ApiResponse::success($this->payload($role, $this->userCounts()[$role->id] ?? 0));
    }

    public function store(Request $request, RecordAuditLog $audit, StaffAccess $access): JsonResponse
    {
        if (! $access->isSuperAdmin($request->user())) {
            throw new AuthorizationException('Forbidden.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:roles,name'],
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        if (in_array($data['name'], RolePermissionMatrix::builtInRoles(), true)) {
            throw new AuthorizationException('Forbidden.');
        }

        $role = Role::query()->create([
            'name' => $data['name'],
            'guard_name' => 'web',
            'description' => $data['description'] ?? null,
        ]);

        $audit(
            'role.created',
            $request->user(),
            'role',
            null,
            null,
            ['role' => $role->name, 'role_id' => $role->id, 'description' => $role->description],
            $request->ip(),
            $request->userAgent(),
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return ApiResponse::created($this->payload($role->load('permissions'), 0));
    }

    public function update(Request $request, Role $role, RecordAuditLog $audit, StaffAccess $access): JsonResponse
    {
        $access->assertCanEditRole($request->user(), $role);

        if (! $access->isSuperAdmin($request->user())) {
            throw new AuthorizationException('Forbidden.');
        }

        $data = $request->validate([
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'name' => ['sometimes', 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('roles', 'name')->ignore($role->id)],
        ]);

        if (in_array($role->name, RolePermissionMatrix::builtInRoles(), true) && isset($data['name']) && $data['name'] !== $role->name) {
            throw new AuthorizationException('Forbidden.');
        }

        $previous = ['name' => $role->name, 'description' => $role->description];
        if (array_key_exists('description', $data)) {
            $role->description = $data['description'];
        }
        if (isset($data['name'])) {
            $role->name = $data['name'];
        }
        $role->save();

        $audit(
            'role.updated',
            $request->user(),
            'role',
            null,
            $previous,
            ['role' => $role->name, 'role_id' => $role->id, 'description' => $role->description],
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success($this->payload($role->load('permissions'), $this->userCounts()[$role->id] ?? 0));
    }

    public function destroy(Request $request, Role $role, RecordAuditLog $audit, StaffAccess $access): JsonResponse
    {
        if (! $access->isSuperAdmin($request->user())) {
            throw new AuthorizationException('Forbidden.');
        }

        if (in_array($role->name, RolePermissionMatrix::builtInRoles(), true) || $this->roleHasUsers($role)) {
            throw new AuthorizationException('Forbidden.');
        }

        $name = $role->name;
        $id = $role->id;
        $role->delete();

        $audit(
            'role.deleted',
            $request->user(),
            'role',
            null,
            ['role' => $name, 'role_id' => $id],
            null,
            $request->ip(),
            $request->userAgent(),
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        return ApiResponse::success(null, 'Role deleted');
    }

    public function permissions(): JsonResponse
    {
        return ApiResponse::success([
            'groups' => RolePermissionMatrix::catalog(),
        ]);
    }

    public function syncPermissions(SyncPermissionsRequest $request, Role $role, SyncRolePermissions $sync): JsonResponse
    {
        $visible = $sync($request->user(), $role, $request->validated('permissions'), $request);
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $role->load('permissions:id,name');

        return ApiResponse::success(array_merge($this->payload($role, $this->userCounts()[$role->id] ?? 0), [
            'permissions' => $visible,
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Role $role, int $usersCount = 0): array
    {
        $permissions = RolePermissionMatrix::visiblePermissions($role->permissions->pluck('name')->all());
        sort($permissions);

        return [
            'id' => $role->id,
            'name' => $role->name,
            'label' => RolePermissionMatrix::label($role->name),
            'description' => $role->description ?: RolePermissionMatrix::description($role->name),
            'users_count' => $usersCount,
            'permissions_count' => count($permissions),
            'permissions' => $permissions,
            'protected' => $role->name === RoleName::SuperAdmin->value,
            'built_in' => in_array($role->name, RolePermissionMatrix::builtInRoles(), true),
        ];
    }

    /**
     * @return array<int|string, int>
     */
    private function userCounts(): array
    {
        $table = config('permission.table_names.model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?? 'role_id';

        return DB::table($table)
            ->selectRaw($roleKey.' as role_id, count(*) as aggregate')
            ->groupBy($roleKey)
            ->pluck('aggregate', 'role_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    private function roleHasUsers(Role $role): bool
    {
        $table = config('permission.table_names.model_has_roles');
        $roleKey = config('permission.column_names.role_pivot_key') ?? 'role_id';

        return DB::table($table)->where($roleKey, $role->id)->exists();
    }
}
