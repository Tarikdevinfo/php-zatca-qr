<?php

declare(strict_types=1);

namespace Pixelweb\ZatcaQr\Tests;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Pixelweb\ZatcaQr\Exception\InvalidFieldException;
use Pixelweb\ZatcaQr\Exception\MissingFieldException;
use Pixelweb\ZatcaQr\Tag;
use Pixelweb\ZatcaQr\ZatcaQr;

final class ZatcaQrTest extends TestCase
{
    /**
     * The example published with the ZATCA Phase 1 specification.
     */
    private const REFERENCE_BASE64 =
        'AQxCb2JzIFJlY29yZHMCDzMxMDEyMjM5MzUwMDAwMwMUMjAyMi0wNC0yNVQxNTozMDowMFoEBzEwMDAuMDAFBjE1MC4wMA==';

    private function reference(): ZatcaQr
    {
        return ZatcaQr::make()
            ->sellerName('Bobs Records')
            ->vatNumber('310122393500003')
            ->timestamp('2022-04-25T15:30:00Z')
            ->totalWithVat('1000.00')
            ->vatTotal('150.00');
    }

    public function testItMatchesTheZatcaReferenceExample(): void
    {
        self::assertSame(self::REFERENCE_BASE64, $this->reference()->toBase64());
    }

    public function testItCastsToTheBase64Payload(): void
    {
        self::assertSame(self::REFERENCE_BASE64, (string) $this->reference());
    }

    public function testFromArrayProducesTheSamePayload(): void
    {
        $qr = ZatcaQr::fromArray([
            'seller_name' => 'Bobs Records',
            'vat_number' => '310122393500003',
            'timestamp' => '2022-04-25T15:30:00Z',
            'total_with_vat' => '1000.00',
            'vat_total' => '150.00',
        ]);

        self::assertSame(self::REFERENCE_BASE64, $qr->toBase64());
    }

    public function testTlvStructureIsTagLengthValue(): void
    {
        $tlv = $this->reference()->toTlv();

        // Tag 1, length 12, then "Bobs Records".
        self::assertSame(1, ord($tlv[0]));
        self::assertSame(12, ord($tlv[1]));
        self::assertSame('Bobs Records', substr($tlv, 2, 12));

        // Tag 2 follows immediately.
        self::assertSame(2, ord($tlv[14]));
        self::assertSame(15, ord($tlv[15]));
        self::assertSame('310122393500003', substr($tlv, 16, 15));
    }

    public function testHexOutputMatchesTheTlvBytes(): void
    {
        $qr = $this->reference();

        self::assertSame(bin2hex($qr->toTlv()), $qr->toHex());
        self::assertStringStartsWith('010c426f6273', $qr->toHex());
    }

    public function testArabicSellerNameIsMeasuredInBytesNotCharacters(): void
    {
        $name = 'شركة';            // 4 characters, 8 bytes in UTF-8
        $tag = new Tag(Tag::SELLER_NAME, $name);

        self::assertSame(8, $tag->length());
        self::assertSame(8, ord($tag->toBytes()[1]));
    }

    public function testArabicPayloadRoundTripsThroughBase64(): void
    {
        $qr = ZatcaQr::make()
            ->sellerName('شركة تجريبية')
            ->vatNumber('310122393500003')
            ->timestamp('2022-04-25T15:30:00Z')
            ->totalWithVat('1000.00')
            ->vatTotal('150.00');

        $decoded = base64_decode($qr->toBase64(), true);

        self::assertIsString($decoded);
        self::assertSame($qr->toTlv(), $decoded);
        self::assertStringContainsString('شركة تجريبية', $decoded);
    }

    public function testDateTimeIsConvertedToUtcZuluFormat(): void
    {
        $riyadh = new DateTimeImmutable('2022-04-25 18:30:00', new DateTimeZone('Asia/Riyadh'));

        $qr = $this->reference()->timestamp($riyadh);

        // Riyadh is UTC+3, so 18:30 local is 15:30 Z.
        self::assertSame(self::REFERENCE_BASE64, $qr->toBase64());
    }

    /**
     * @dataProvider amountProvider
     *
     * @param float|int|string $input
     */
    public function testAmountsAreFormattedWithTwoDecimals($input, string $expected): void
    {
        $qr = $this->reference()->totalWithVat($input);

        self::assertStringContainsString($expected, $qr->toTlv());
    }

    /**
     * @return array<string, array{0: float|int|string, 1: string}>
     */
    public static function amountProvider(): array
    {
        return [
            'integer' => [1000, '1000.00'],
            'float' => [1000.5, '1000.50'],
            'string without decimals' => ['1000', '1000.00'],
            'float needing rounding' => [1000.456, '1000.46'],
            'zero' => [0, '0.00'],
        ];
    }

    /**
     * @dataProvider invalidVatNumberProvider
     */
    public function testItRejectsMalformedVatNumbers(string $vatNumber): void
    {
        $this->expectException(InvalidFieldException::class);

        ZatcaQr::make()->vatNumber($vatNumber);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidVatNumberProvider(): array
    {
        return [
            'too short' => ['31012239350003'],
            'too long' => ['3101223935000033'],
            'does not start with 3' => ['410122393500003'],
            'does not end with 3' => ['310122393500004'],
            'contains letters' => ['31012239350000A'],
            'empty' => [''],
        ];
    }

    public function testItRejectsAnEmptySellerName(): void
    {
        $this->expectException(InvalidFieldException::class);

        ZatcaQr::make()->sellerName('   ');
    }

    public function testItRejectsNonNumericAmounts(): void
    {
        $this->expectException(InvalidFieldException::class);

        ZatcaQr::make()->totalWithVat('one thousand');
    }

    public function testItRejectsNegativeAmounts(): void
    {
        $this->expectException(InvalidFieldException::class);

        ZatcaQr::make()->vatTotal(-1);
    }

    public function testItRefusesToBuildAnIncompletePayload(): void
    {
        $this->expectException(MissingFieldException::class);
        $this->expectExceptionMessage('timestamp');

        ZatcaQr::make()
            ->sellerName('Bobs Records')
            ->vatNumber('310122393500003')
            ->totalWithVat('1000.00')
            ->vatTotal('150.00')
            ->toBase64();
    }

    public function testTagsAreReturnedInZatcaOrder(): void
    {
        $tags = $this->reference()->tags();

        self::assertCount(5, $tags);
        self::assertSame(
            [Tag::SELLER_NAME, Tag::VAT_NUMBER, Tag::TIMESTAMP, Tag::TOTAL_WITH_VAT, Tag::VAT_TOTAL],
            array_map(static fn (Tag $tag): int => $tag->tag(), $tags)
        );
    }
}
