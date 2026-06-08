<?php

namespace Tests\Unit;

use App\Services\GradeAggregationService;
use PHPUnit\Framework\TestCase;

class GradeAggregationServiceTest extends TestCase
{
    private GradeAggregationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        // bna3mel object gded mn el-service 3ashan n-test beh
        $this->service = new GradeAggregationService();
    }

    /**
     * Test single component normalization.
     */
    public function test_it_correctly_normalizes_a_single_score(): void
    {
        // law talib gab 80 mn 100, w el-weight 20% -> lazm el-natiga tkon 16.0
        $this->assertEquals(16.0, $this->service->normalizeScore(80.0, 100.0, 20.0));
    }

    /**
     * Test total course aggregation with multiple weight combinations.
     */
    public function test_it_aggregates_multiple_components_for_course_total(): void
    {
        // bn-prepare array feha kza component b-weights mokhtalefa
        $components = [
            ['raw_score' => 10, 'raw_max' => 10, 'weight' => 20], // Full mark fe lab -> 20.0
            ['raw_score' => 40, 'raw_max' => 50, 'weight' => 30], // 80% fe midterm -> 24.0
            ['raw_score' => 75, 'raw_max' => 100, 'weight' => 50], // 75% fe final -> 37.5
        ];

        // Total lazm yb2a: 20.0 + 24.0 + 37.5 = 81.5
        $this->assertEquals(81.5, $this->service->calculateCourseTotal($components));
    }
}
