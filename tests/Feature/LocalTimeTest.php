<?php

namespace Tests\Feature;

use App\Support\LocalTime;
use Tests\TestCase;

class LocalTimeTest extends TestCase
{
    public function test_local_input_is_stored_as_utc_and_comes_back_unchanged(): void
    {
        $utc = LocalTime::fromInput('2026-12-01T19:00');

        $this->assertSame('2026-12-01 12:00:00', $utc->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01T19:00', LocalTime::toInput($utc));
    }

    public function test_label_uses_weekday_and_local_time(): void
    {
        $this->assertSame('Tue, 1 Dec 2026 · 19:00', LocalTime::label(LocalTime::fromInput('2026-12-01T19:00')));
        $this->assertNull(LocalTime::label(null));
        $this->assertSame('WIB', LocalTime::abbreviation());
    }

    public function test_blank_input_is_null(): void
    {
        $this->assertNull(LocalTime::fromInput(''));
        $this->assertNull(LocalTime::fromInput(null));
        $this->assertNull(LocalTime::toInput(null));
    }

    public function test_day_boundaries_are_local(): void
    {
        $this->assertSame('2026-11-30 17:00:00', LocalTime::startOfDay('2026-12-01')->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-01 16:59:59', LocalTime::endOfDay('2026-12-01')->utc()->format('Y-m-d H:i:s'));
    }
}
