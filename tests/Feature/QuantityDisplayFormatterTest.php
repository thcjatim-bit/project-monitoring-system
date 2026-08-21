<?php

namespace Tests\Feature;

use App\Support\QuantityDisplayFormatter;
use Tests\TestCase;

class QuantityDisplayFormatterTest extends TestCase
{
    public function test_qty_formatter_groups_thousands_and_removes_unneeded_fractional_zeroes(): void
    {
        $this->assertSame('2.312.279', QuantityDisplayFormatter::format('2312279.000'));
        $this->assertSame('1.250,5', QuantityDisplayFormatter::format('1250.500'));
        $this->assertSame('-1.234,125', QuantityDisplayFormatter::format('-1234.125'));
    }
}
