<?php

declare(strict_types=1);

namespace Pixelweb\ZatcaQr\Tests;

use PHPUnit\Framework\TestCase;
use Pixelweb\ZatcaQr\Exception\InvalidFieldException;
use Pixelweb\ZatcaQr\Tag;

final class TagTest extends TestCase
{
    public function testItEncodesTagLengthValue(): void
    {
        $tag = new Tag(3, 'abc');

        self::assertSame("\x03\x03abc", $tag->toBytes());
        self::assertSame(3, $tag->tag());
        self::assertSame('abc', $tag->value());
        self::assertSame(3, $tag->length());
    }

    public function testAnEmptyValueEncodesWithZeroLength(): void
    {
        $tag = new Tag(1, '');

        self::assertSame("\x01\x00", $tag->toBytes());
        self::assertSame(0, $tag->length());
    }

    public function testItAcceptsAValueOfExactlyTheMaximumLength(): void
    {
        $tag = new Tag(1, str_repeat('a', Tag::MAX_VALUE_BYTES));

        self::assertSame(255, $tag->length());
        self::assertSame(255, ord($tag->toBytes()[1]));
    }

    public function testItRejectsAValueLongerThanTheLengthByteAllows(): void
    {
        $this->expectException(InvalidFieldException::class);

        new Tag(1, str_repeat('a', Tag::MAX_VALUE_BYTES + 1));
    }

    /**
     * @dataProvider outOfRangeTagProvider
     */
    public function testItRejectsTagNumbersOutsideASingleByte(int $tagNumber): void
    {
        $this->expectException(InvalidFieldException::class);

        new Tag($tagNumber, 'value');
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function outOfRangeTagProvider(): array
    {
        return [
            'zero' => [0],
            'negative' => [-1],
            'above one byte' => [256],
        ];
    }

    public function testItCastsToItsBinaryForm(): void
    {
        $tag = new Tag(5, '150.00');

        self::assertSame($tag->toBytes(), (string) $tag);
    }
}
