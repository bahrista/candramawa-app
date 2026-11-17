<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserWhatsappTest extends TestCase
{
    #[DataProvider('numbers')]
    public function test_whatsapp_numbers_are_normalised(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, User::normaliseWhatsapp($input));
    }

    public static function numbers(): array
    {
        return [
            'local with spaces' => ['0812 3456 7890', '+6281234567890'],
            'local with dashes' => ['0812-3456-7890', '+6281234567890'],
            'country code only' => ['6281234567890', '+6281234567890'],
            'double zero prefix' => ['0062 812 3456 7890', '+628123456789'.'0'],
            'already international' => ['+62 812 3456 7890', '+6281234567890'],
            'blank' => ['  ', null],
            'null' => [null, null],
        ];
    }
}
