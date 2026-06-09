<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\Cohort;

class AnnouncementController extends Controller
{
    public function store(Request $request)
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

        return response()->json([
            'message' => 'Announcement published successfully',
            'data' => $announcement
        ], 201);
    }
}