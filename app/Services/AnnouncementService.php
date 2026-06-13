<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Announcement;
use App\Models\Cohort;
use App\Models\EngagementSession;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AnnouncementService
{
    /**
     * Get paginated history of announcements authored by the instructor.
     */
    public function getInstructorAnnouncements(User $instructor): LengthAwarePaginator
    {
        return Announcement::with('cohort:id,name')
            ->where('author_id', $instructor->id)
            ->latest('published_at')
            ->paginate(10);
    }

    /**
     * Create a new announcement, enforcing the ENG-5 active session rule.
     */
    public function createAnnouncement(User $instructor, array $data): Announcement
    {
        // Enforce strict ENG-5: Check active session right now
        $now = now();
        $currentTime = $now->format('H:i:s');
        $currentDate = $now->toDateString();

        $hasActiveSession = EngagementSession::where('session_date', $currentDate)
            ->where('start_time', '<=', $currentTime)
            ->where('end_time', '>=', $currentTime)
            ->whereHas('engagement', function ($query) use ($instructor) {
                $query->where('instructor_id', $instructor->id);
            })
            ->exists();

        if (!$hasActiveSession) {
            throw new HttpException(403, 'You can only publish announcements during an active session.');
        }

        $cohort = Cohort::findOrFail($data['cohort_id']);

        return Announcement::create([
            'cohort_id' => $cohort->id,
            'author_id' => $instructor->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'published_at' => now(),
        ]);
    }
}
