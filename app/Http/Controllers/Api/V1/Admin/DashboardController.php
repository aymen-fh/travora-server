<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Queries\Admin\BuildAdminDashboard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DashboardController extends Controller
{
    public function __invoke(Request $request, BuildAdminDashboard $dashboard): JsonResponse
    {
        $this->authorize(PermissionName::DashboardView->value);

        /** @var User $actor */
        $actor = $request->user();

        return ApiResponse::success($dashboard(
            $actor,
            $request->string('range', '30d')->toString() ?: '30d',
            $request->input('from'),
            $request->input('to'),
        ));
    }
}
