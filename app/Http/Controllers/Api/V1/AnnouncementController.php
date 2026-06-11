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

        // ANN-2 / ENG-5: Instructor window enforcement (Date-only comparison)
        if ($request->user()->role === 'instructor') {
            $hasActiveEngagement = \App\Models\Engagement::where('instructor_id', $request->user()->id)
                ->whereHas('cohorts', function ($q) use ($cohort) {
                    $q->where('cohorts.id', $cohort->id);
                })
                ->where('start_date', '<=', now()->toDateString())
                ->where('end_date', '>=', now()->toDateString())
                ->exists();

            if (!$hasActiveEngagement) {
                return $this->errorResponse('Instructors can only post announcements during their active engagement window.', 403);
            }
        }

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

    // student or instructor sees their announcements
    // GET /api/v1/me/announcements
    public function myAnnouncements(\Illuminate\Http\Request $request): JsonResponse
    {
        $user = $request->user();
        $cohortIds = [];

        if ($user->role == 'student') {
            $cohortIds = $user->enrolledCohorts()->pluck('cohorts.id')->toArray();
        } elseif ($user->role == 'instructor') {
            $cohorts = Cohort::whereHas('engagements', function ($q) use ($user) {
                $q->where('engagements.instructor_id', $user->id);
            })->get();

            foreach ($cohorts as $cohort) {
                $cohortIds[] = $cohort->id;
            }
        } else {
            // track admin or branch manager sees all
            $cohortIds = Cohort::pluck('id')->toArray();
        }

        $announcements = Announcement::with('author:id,name,role')->whereIn('cohort_id', $cohortIds)
            ->latest('published_at')
            ->get();

        return $this->successResponse($announcements, 'Announcements retrieved successfully.');
    }
}