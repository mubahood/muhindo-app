<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** A client's own projects — read-only, scoped to their linked Client record. */
class ProjectController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $client = $request->user()->client;
        if (! $client) {
            return ApiResponse::success([]);
        }

        return ApiResponse::success(Project::where('client_id', $client->id)->latest()->get());
    }

    public function show(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        return ApiResponse::success($project->load(['tasks', 'updates', 'documents']));
    }
}
