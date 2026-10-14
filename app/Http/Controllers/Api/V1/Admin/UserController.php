<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Audit\RecordAuditLog;
use App\Actions\Users\SuspendUser;
use App\Actions\Users\UpdateUser;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SuspendUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\Admin\AdminUserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

final class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $staffRoles = [
            RoleName::Support->value,
            RoleName::Operator->value,
            RoleName::Finance->value,
            RoleName::Admin->value,
            RoleName::SuperAdmin->value,
        ];

        $page = User::query()
            ->with(['profile', 'roles'])
            ->withCount(['orders', 'esimProfiles as subscriptions_count'])
            ->whereDoesntHave('roles', fn ($query) => $query->whereIn('name', $staffRoles))
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $driver = $query->getConnection()->getDriverName();
                $like = $driver === 'pgsql' ? 'ilike' : 'like';
                $needle = '%'.$search.'%';

                $query->where(function ($inner) use ($like, $needle, $driver): void {
                    $inner->where('email', $like, $needle)
                        ->orWhere('phone', $like, $needle)
                        ->orWhereHas('profile', function ($profile) use ($like, $needle): void {
                            $profile->where('display_name', $like, $needle)
                                ->orWhere('first_name', $like, $needle)
                                ->orWhere('last_name', $like, $needle);
                        });

                    if ($driver === 'pgsql') {
                        $inner->orWhereRaw('id::text ILIKE ?', [$needle]);
                    } else {
                        $inner->orWhere('id', 'like', $needle);
                    }
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            AdminUserResource::collection($page->getCollection())->resolve(),
            'OK',
            200,
            [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        );
    }

    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorize('view', $user);

        $load = ['profile', 'roles'];
        if ($request->user()?->can(PermissionName::WalletsView->value)) {
            $load[] = 'wallets';
        }

        return ApiResponse::success(new AdminUserResource($user->load($load)->loadCount(['orders', 'esimProfiles as subscriptions_count'])));
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUser $update): JsonResponse
    {
        $this->authorize('update', $user);

        return ApiResponse::success(new AdminUserResource(($update)($request->user(), $user, $request->validated(), $request)));
    }

    public function suspend(SuspendUserRequest $request, User $user, SuspendUser $suspend): JsonResponse
    {
        $this->authorize('suspend', $user);

        return ApiResponse::success(
            new AdminUserResource(($suspend)($request->user(), $user, $request->string('reason')->toString(), $request)),
            'User suspended',
        );
    }

    public function sessions(User $user): JsonResponse
    {
        $this->authorize('viewSessions', $user);

        $tokens = $user->tokens()->orderByDesc('last_used_at')->get()->map(fn (PersonalAccessToken $token): array => [
            'id' => $token->id,
            'name' => $token->name,
            'last_used_at' => $token->last_used_at?->toIso8601String(),
            'created_at' => $token->created_at?->toIso8601String(),
        ]);

        return ApiResponse::success($tokens);
    }

    public function revokeSession(Request $request, User $user, int $token, RecordAuditLog $audit): JsonResponse
    {
        $this->authorize('revokeSession', $user);

        $user->tokens()->whereKey($token)->delete();

        $audit(
            'user.session.revoked',
            $request->user(),
            User::class,
            $user->id,
            null,
            ['token_id' => $token],
            $request->ip(),
            $request->userAgent(),
        );

        return ApiResponse::success(null, 'Session revoked');
    }
}
