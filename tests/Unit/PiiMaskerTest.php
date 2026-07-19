<?php

namespace Tests\Unit;

use App\Services\AiSearch\PiiMasker;
use PHPUnit\Framework\TestCase;

/**
 * Kart/TC maskeleme bekçileri: hassas numara asla ham geçmez; telefon
 * numarası (lead akışının meşru girdisi) ASLA maskelenmez.
 */
class PiiMaskerTest extends TestCase
{
    public function test_valid_card_number_is_masked(): void
    {
        // 4111 1111 1111 1111 — bilinen Luhn-geçerli test kartı
        $r = PiiMasker::mask('kartım 4111 1111 1111 1111 bununla ödeyebilir miyim');
        $this->assertContains('kart', $r['types']);
        $this->assertStringNotContainsString('4111 1111 1111 1111', $r['text']);
        $this->assertStringContainsString('**** **** **** 1111', $r['text']);
    }

    public function test_luhn_invalid_digits_untouched(): void
    {
        // 16 hane ama Luhn tutmuyor → rezervasyon no benzeri, dokunulmaz
        $r = PiiMasker::mask('rezervasyon no 1234 5678 9012 3456');
        $this->assertSame([], $r['types']);
        $this->assertStringContainsString('1234 5678 9012 3456', $r['text']);
    }

    public function test_valid_tc_is_masked(): void
    {
        // 10000000146 — checksum-geçerli örnek TC
        $r = PiiMasker::mask('tc kimlik 10000000146 ile kayıt olur musun');
        $this->assertContains('tc', $r['types']);
        $this->assertStringNotContainsString('10000000146', $r['text']);
    }

    public function test_phone_numbers_are_never_masked(): void
    {
        foreach (['0532 123 45 67', '05321234567', '+90 532 123 45 67', '905321234567'] as $phone) {
            $r = PiiMasker::mask('beni ara: '.$phone);
            $this->assertSame([], $r['types'], "telefon maskelenmemeli: $phone");
        }
    }

    public function test_random_eleven_digits_without_checksum_untouched(): void
    {
        // 11 hane, 0'la başlamıyor ama TC checksum'ı tutmuyor → sipariş no olabilir
        $r = PiiMasker::mask('sipariş 12345678901');
        $this->assertSame([], $r['types']);
    }
}
