<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\Cohort;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

class AnnouncementController extends Controller
{
    use ApiResponse;

    public function index(Cohort $cohort): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $announcements = Announcement::where('cohort_id', $cohort->id)
            ->latest('published_at')
            ->get();

        return $this->successResponse($announcements, 'Announcements retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cohort_id' => 'required|exists:cohorts,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
        ]);

        $cohort = Cohort::findOrFail($validated['cohort_id']);

        // validate announcement using policy
        $this->authorize('create', [Announcement::class, $cohort]);

        $announcement = Announcement::create([
            'cohort_id' => $cohort->id,
            'author_id' => $request->user()->id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'published_at' => now(),
        ]);

        return $this->successResponse($announcement, 'Announcement published successfully', 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $this->authorize('view', $announcement);

        return $this->successResponse($announcement, 'Announcement retrieved successfully');
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $this->authorize('update', $announcement);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'body' => 'sometimes|string',
        ]);

        $announcement->update($validated);

        return $this->successResponse($announcement, 'Announcement updated successfully');
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->authorize('delete', $announcement);

        $announcement->delete();

        return $this->successResponse(null, 'Announcement deleted successfully');
    }
}