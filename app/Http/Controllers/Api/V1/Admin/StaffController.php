<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Staff\ChangeStaffStatus;
use App\Actions\Staff\CreateStaff;
use App\Actions\Staff\SyncStaffPermissions;
use App\Actions\Staff\UpdateStaff;
use App\Enums\UserStatus;
use App\Http\Controllers\Api\V1\Admin\Concerns\RespondsWithAdminPage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateStaffRequest;
use App\Http\Requests\Admin\SyncPermissionsRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Http\Requests\Admin\UpdateStaffStatusRequest;
use App\Http\Resources\Admin\StaffResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Support\Rbac\RolePermissionMatrix;
use App\Support\Rbac\StaffAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class StaffController extends Controller
{
    use RespondsWithAdminPage;

    public function index(Request $request): JsonResponse
    {
        $sort = $request->string('sort')->toString();
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['email', 'status', 'last_login_at', 'created_at'];

        $page = User::query()
            ->staff()
            ->with(['profile', 'roles'])
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('email', 'like', '%'.$search.'%')
                        ->orWhere('phone', 'like', '%'.$search.'%')
                        ->orWhereHas('profile', fn ($profile) => $profile->where('display_name', 'like', '%'.$search.'%'));
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('role'), function ($query) use ($request): void {
                $query->whereHas('roles', fn ($roles) => $roles->where('name', $request->string('role')));
            })
            ->when(
                in_array($sort, $allowedSorts, true),
                fn ($query) => $query->orderBy($sort, $direction),
                fn ($query) => $query->latest(),
            )
            ->paginate($request->integer('per_page', 20));

        return $this->adminPage(StaffResource::collection($page->getCollection())->resolve(), $page);
    }

    public function store(CreateStaffRequest $request, CreateStaff $create): JsonResponse
    {
        $staff = $create($request->user(), $request->validated(), $request);

        return ApiResponse::created(new StaffResource($staff), 'Staff account created. A password setup code was sent.');
    }

    public function show(User $staff, StaffAccess $access): JsonResponse
    {
        $access->assertStaff($staff);

        return ApiResponse::success(new StaffResource($staff));
    }

    public function update(UpdateStaffRequest $request, User $staff, UpdateStaff $update): JsonResponse
    {
        return ApiResponse::success(new StaffResource($update($request->user(), $staff, $request->validated(), $request)));
    }

    public function status(UpdateStaffStatusRequest $request, User $staff, ChangeStaffStatus $change): JsonResponse
    {
        $updated = $change(
            $request->user(),
            $staff,
            UserStatus::from($request->string('status')->toString()),
            $request->input('reason'),
            $request,
        );

        return ApiResponse::success(new StaffResource($updated), 'Staff status updated');
    }

    public function permissions(User $staff, StaffAccess $access): JsonResponse
    {
        $access->assertStaff($staff);

        return ApiResponse::success(array_merge(
            (new StaffResource($staff))->resolve(),
            RolePermissionMatrix::breakdown($staff),
        ));
    }

    public function syncPermissions(SyncPermissionsRequest $request, User $staff, SyncStaffPermissions $sync): JsonResponse
    {
        $breakdown = $sync($request->user(), $staff, $request->validated('permissions'), $request);

        return ApiResponse::success(array_merge(
            (new StaffResource($staff->fresh(['profile', 'roles']) ?? $staff))->resolve(),
            $breakdown,
        ));
    }
}
