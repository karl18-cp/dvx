<?php

namespace App\Http\Controllers;

use App\Services\DailyBriefService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyBriefController extends Controller
{
    public function show(Request $request, DailyBriefService $brief): JsonResponse
    {
        return response()->json($brief->data($request->user()));
    }

    public function complete(Request $request, DailyBriefService $brief): JsonResponse
    {
        $data = $request->validate(['answer' => ['required', 'integer', 'between:0,3']]);

        return response()->json($brief->complete($request->user(), $data['answer']));
    }
}
