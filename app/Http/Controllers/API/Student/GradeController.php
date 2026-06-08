<?php

namespace App\Http\Controllers\API\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\GradeResource;
use App\Services\GrandTotalService;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    protected $grandTotalService;

    /**
     * Injecting the GrandTotalService into the controller.
     */
    public function __construct(GrandTotalService $grandTotalService)
    {
        $this->grandTotalService = $grandTotalService;
    }

    /**
     * Display a listing of the authenticated student's grades.
     */
    public function index(Request $request)
    {
        $student = $request->user();

        // Fetch grades with eager-loaded course and lab submission relationships
        $grades = $student->grades()->with(['course', 'labSubmission'])->get();

        // Calculate the final score dynamically using your tested service logic
        foreach ($grades as $grade) {
            $grade->final_score = $this->grandTotalService->calculate($grade);
        }

        return GradeResource::collection($grades);
    }
}
