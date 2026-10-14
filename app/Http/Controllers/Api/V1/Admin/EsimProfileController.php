<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Esim\CommandEsimProfile;
use App\Actions\Esim\RefreshEsimUsage;
use App\Actions\Esim\SendEsimSms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendEsimSmsRequest;
use App\Http\Resources\Admin\AdminEsimProfileResource;
use App\Http\Responses\ApiResponse;
use App\Models\EsimProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EsimProfileController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EsimProfile::class);

        $page = EsimProfile::query()
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($inner) use ($search): void {
                    $inner->where('iccid', 'like', '%'.$search.'%')
                        ->orWhere('external_profile_id', 'like', '%'.$search.'%');
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->string('user_id')))
            ->with(['user.profile', 'plan:id,name', 'provider:id,name'])
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::success(
            AdminEsimProfileResource::collection($page->getCollection())->resolve(),
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

    public function show(EsimProfile $profile): JsonResponse
    {
        $this->authorize('view', $profile);

        return ApiResponse::success(new AdminEsimProfileResource(
            $profile->load(['user.profile', 'plan:id,name', 'provider.services']),
        ));
    }

    public function usage(EsimProfile $profile): JsonResponse
    {
        $this->authorize('viewUsage', $profile);

        $snapshots = $profile->usageSnapshots()->latest('recorded_at')->limit(50)->get()->map(fn ($usage): array => [
            'id' => $usage->id,
            'used_mb' => $usage->used_mb,
            'remaining_mb' => $usage->remaining_mb,
            'source' => $usage->source,
            'recorded_at' => $usage->recorded_at?->toIso8601String(),
        ]);

        return ApiResponse::success($snapshots);
    }

    public function syncUsage(EsimProfile $profile, RefreshEsimUsage $refresh): JsonResponse
    {
        $this->authorize('syncUsage', $profile);

        $usage = $refresh($profile);

        return ApiResponse::success([
            'id' => $usage->id,
            'used_mb' => $usage->used_mb,
            'remaining_mb' => $usage->remaining_mb,
            'source' => $usage->source,
            'recorded_at' => $usage->recorded_at?->toIso8601String(),
        ], 'Usage synced');
    }

    public function cancel(Request $request, EsimProfile $profile, CommandEsimProfile $command): JsonResponse
    {
        return $this->command($request, $profile, $command, 'cancel');
    }

    public function suspend(Request $request, EsimProfile $profile, CommandEsimProfile $command): JsonResponse
    {
        return $this->command($request, $profile, $command, 'suspend');
    }

    public function unsuspend(Request $request, EsimProfile $profile, CommandEsimProfile $command): JsonResponse
    {
        return $this->command($request, $profile, $command, 'unsuspend');
    }

    public function revoke(Request $request, EsimProfile $profile, CommandEsimProfile $command): JsonResponse
    {
        return $this->command($request, $profile, $command, 'revoke');
    }

    public function sendSms(SendEsimSmsRequest $request, EsimProfile $profile, SendEsimSms $send): JsonResponse
    {
        $this->authorize('sendSms', $profile);

        $send($this->actor($request), $profile, $request->string('message')->toString(), $request->ip(), $request->userAgent());

        return ApiResponse::success(['sent' => true], 'SMS sent');
    }

    private function command(Request $request, EsimProfile $profile, CommandEsimProfile $command, string $name): JsonResponse
    {
        $this->authorize('command', $profile);

        $updated = $command($this->actor($request), $profile, $name, $request->ip(), $request->userAgent());

        return ApiResponse::success(new AdminEsimProfileResource(
            $updated->load(['user.profile', 'plan:id,name', 'provider.services']),
        ), 'Updated');
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(401);
        }

        return $actor;
    }
}
