<?php

namespace Tests\Unit;

use App\Services\LatePenaltyService;
use PHPUnit\Framework\TestCase;

class LatePenaltyServiceTest extends TestCase
{
    private LatePenaltyService $service;

    /**
     * Setup method executed before each individual test case.
     */
    protected function setUp(): void
    {
        parent::setUp();
        // bna3mel object gded mn el-service 3ashan n-test beh
        $this->service = new LatePenaltyService();
    }

    /**
     * Scenario 1: Zero days late should yield the exact full raw score.
     */
    public function test_it_applies_no_penalty_for_zero_days_late(): void
    {
        // mfeesh takhreer (0 ayyam) -> el-daraga bterga3 100 zay ma hya
        $this->assertEquals(100.0, $this->service->calculate(100.0, 0));
    }

    /**
     * Scenario 2: 1 day late should deduct exactly 25%.
     */
    public function test_it_applies_twenty_five_percent_penalty_for_one_day_late(): void
    {
        // takhreer yom wahed -> khasm 25% -> el-natiga 75
        $this->assertEquals(75.0, $this->service->calculate(100.0, 1));
    }

    /**
     * Scenario 3: 2 days late should deduct exactly 50%.
     */
    public function test_it_applies_fifty_percent_penalty_for_two_days_late(): void
    {
        // takhreer yomeen -> khasm 50% -> el-natiga 50
        $this->assertEquals(50.0, $this->service->calculate(100.0, 2));
    }

    /**
     * Scenario 4: 4 days late should hit the 100% maximum penalty cap and return 0.
     */
    public function test_it_caps_penalty_at_zero_for_four_days_late(): void
    {
        // takhreer 4 ayyam -> khasm 100% -> el-natiga lazm tkon zero
        $this->assertEquals(0.0, $this->service->calculate(100.0, 4));
    }

    /**
     * Scenario 5: More than 4 days (e.g., 5 days) must strictly remain 0 and never go negative.
     */
    public function test_it_does_not_go_negative_for_five_days_late(): void
    {
        // takhreer 5 ayyam -> lazm yfdel zero w maynzelshe l el-saleb
        $this->assertEquals(0.0, $this->service->calculate(100.0, 5));
    }
}
