<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\Cohort;
use App\Services\AnnouncementService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AnnouncementController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AnnouncementService $announcementService
    ) {}

    public function index(Cohort $cohort): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);

        $announcements = Announcement::where('cohort_id', $cohort->id)
            ->latest('published_at')
            ->get();

        return $this->successResponse($announcements, 'Announcements retrieved successfully');
    }

    public function instructorIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if ($user->role !== 'instructor') {
            return $this->errorResponse('Only instructors can access this history.', 403);
        }

        $announcements = $this->announcementService->getInstructorAnnouncements($user);
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
        $this->authorize('create', [Announcement::class, $cohort]);

        if ($request->user()->role === 'instructor') {
            try {
                $announcement = $this->announcementService->createAnnouncement($request->user(), $validated);
                return $this->successResponse($announcement, 'Announcement published successfully', 201);
            } catch (HttpException $e) {
                return $this->errorResponse($e->getMessage(), $e->getStatusCode());
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
        } elseif ($user->role === 'track_admin') {
            $cohortIds = $user->administeredCohorts()->pluck('cohorts.id')->toArray();
        } else {
            // branch manager sees all
            $cohortIds = Cohort::pluck('id')->toArray();
        }

        $announcements = Announcement::with('author:id,name,role')->whereIn('cohort_id', $cohortIds)
            ->latest('published_at')
            ->get();

        return $this->successResponse($announcements, 'Announcements retrieved successfully.');
    }
}