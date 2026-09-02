<?php

namespace Tests\Unit\Support;

use App\Support\CancellationNotice;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CancellationNoticeTest extends TestCase
{
    public function test_is_last_minute_when_cancelled_less_than_threshold_before_start(): void
    {
        $start = Carbon::parse('2026-01-10 10:00:00');
        $cancelledAt = Carbon::parse('2026-01-10 00:00:01'); // ~9h59min antes

        $this->assertTrue(CancellationNotice::isLastMinute($cancelledAt, $start));
    }

    public function test_is_not_last_minute_when_cancelled_at_exactly_threshold(): void
    {
        $start = Carbon::parse('2026-01-10 10:00:00');
        $cancelledAt = Carbon::parse('2026-01-09 10:00:00'); // exatamente 24h antes

        $this->assertFalse(CancellationNotice::isLastMinute($cancelledAt, $start));
    }

    public function test_is_not_last_minute_when_cancelled_well_in_advance(): void
    {
        $start = Carbon::parse('2026-01-10 10:00:00');
        $cancelledAt = Carbon::parse('2026-01-01 10:00:00');

        $this->assertFalse(CancellationNotice::isLastMinute($cancelledAt, $start));
    }

    public function test_custom_threshold_is_respected(): void
    {
        $start = Carbon::parse('2026-01-10 10:00:00');
        $cancelledAt = Carbon::parse('2026-01-10 04:00:00'); // 6h antes

        $this->assertTrue(CancellationNotice::isLastMinute($cancelledAt, $start, thresholdHours: 12));
        $this->assertFalse(CancellationNotice::isLastMinute($cancelledAt, $start, thresholdHours: 4));
    }
}
