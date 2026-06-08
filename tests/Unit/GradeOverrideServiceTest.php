<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\GradeOverrideService;
use App\Models\Grade;
use InvalidArgumentException;

class GradeOverrideServiceTest extends TestCase
{
    private GradeOverrideService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GradeOverrideService();
    }

    /**
     * Test that empty override note strictly throws an exception.
     */
    public function test_it_throws_exception_if_override_note_is_empty(): void
    {
        // bn-expect in el-code hy-throw InvalidArgumentException
        $this->expectException(InvalidArgumentException::class);

        // bn-create mock le-grade model 3ashan n-pass el-argument bs
        $grade = new Grade();

        // bn-try n-execute b-note fadya -> lazm y-fail hna
        $this->service->executeOverride($grade, 90.0, 1, '   ');
    }
}
