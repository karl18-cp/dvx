<?php

namespace App\Http\Controllers;

use App\Services\OperationsHealthService;
use App\Services\RolePermissionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OperationsHealthController extends Controller
{
    public function __invoke(Request $request, OperationsHealthService $health)
    {
        abort_unless(app(RolePermissionService::class)->can($request->user(), 'operations.view'), 403);

        return Inertia::render('operations-health', $health->snapshot());
    }
}
