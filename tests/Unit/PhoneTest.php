<?php

namespace Tests\Unit;

use App\Support\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    #[DataProvider('validNumbers')]
    public function test_normalizes_indonesian_mobile_numbers(string $input): void
    {
        $this->assertSame('6287874627555', Phone::normalize($input));
    }

    public static function validNumbers(): array
    {
        return [
            'lokal dengan strip' => ['0878-7462-7555'],
            'lokal polos' => ['087874627555'],
            'plus 62 dengan spasi' => ['+62 878 7462 7555'],
            '62 langsung' => ['6287874627555'],
            'tanpa nol depan' => ['87874627555'],
            '62 diikuti nol berlebih' => ['620878 7462 7555'],
            'awalan 00' => ['0062 878-7462-7555'],
            'tanda kurung' => ['(0878) 7462-7555'],
        ];
    }

    #[DataProvider('invalidNumbers')]
    public function test_rejects_invalid_numbers(?string $input): void
    {
        $this->assertNull(Phone::normalize($input));
    }

    public static function invalidNumbers(): array
    {
        return [
            'kosong' => [''],
            'null' => [null],
            'huruf' => ['abc'],
            'terlalu pendek' => ['0878'],
            'terlalu panjang' => ['0878746275551234567'],
            'telepon rumah' => ['0251-1234567'],
            'kode negara lain' => ['+1 415 555 2671'],
        ];
    }

    public function test_does_not_drop_digits_in_the_middle_of_the_number(): void
    {
        $this->assertSame('628123456789', Phone::normalize('0812 3456 789'));
        $this->assertSame('6280123456789', Phone::normalize('+62 801 2345 6789'));
    }

    public function test_display_format(): void
    {
        $this->assertSame('0878-7462-7555', Phone::display('6287874627555'));
        $this->assertNull(Phone::display(null));
    }

    public function test_whatsapp_url_encodes_text(): void
    {
        $url = Phone::whatsappUrl('6287874627555', "Halo & selamat\nKode: KB-1 100%");

        $this->assertSame('https://wa.me/6287874627555?text=Halo%20%26%20selamat%0AKode%3A%20KB-1%20100%25', $url);
        $this->assertSame('https://wa.me/6287874627555', Phone::whatsappUrl('6287874627555'));
        $this->assertNull(Phone::whatsappUrl(null, 'x'));
    }
}
