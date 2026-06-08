<?php

namespace Tests\Unit;

use App\Services\GrandTotalService;
use PHPUnit\Framework\TestCase;

class GrandTotalServiceTest extends TestCase
{
    private GrandTotalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GrandTotalService();
    }

    /**
     * Test grand total calculation with complete scores.
     */
    public function test_it_calculates_weighted_grand_total_correctly(): void
    {
        $courses = [
            ['name' => 'Database', 'normalized_score' => 80.0, 'credits' => 3], // 240 points
            ['name' => 'Web Dev',  'normalized_score' => 90.0, 'credits' => 4], // 360 points
        ];

        // Total points = 600, Total credits = 7 -> 600 / 7 = 85.71
        $this->assertEquals(85.71, $this->service->calculateGrandTotal($courses));
    }

    /**
     * Test edge case: skipping courses with null scores safely.
     */
    public function test_it_skips_courses_with_null_scores_safely(): void
    {
        $courses = [
            ['name' => 'Database', 'normalized_score' => 80.0, 'credits' => 3], // 240 points
            ['name' => 'Web Dev',  'normalized_score' => null, 'credits' => 4], // Lsa manzletsh -> Skip!
        ];

        // Total points = 240, Total credits = 3 (after skipping null) -> 240 / 3 = 80.0
        $this->assertEquals(80.0, $this->service->calculateGrandTotal($courses));
    }
}
