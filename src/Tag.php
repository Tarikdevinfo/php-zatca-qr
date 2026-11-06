<?php

declare(strict_types=1);

namespace Pixelweb\ZatcaQr;

use Pixelweb\ZatcaQr\Exception\InvalidFieldException;

/**
 * A single Tag-Length-Value field of a ZATCA QR payload.
 *
 * The binary form is one tag byte, one length byte, then the raw UTF-8 value.
 * The length counts bytes, not characters — this matters for Arabic values,
 * where a single character occupies two bytes.
 */
final class Tag
{
    public const SELLER_NAME = 1;
    public const VAT_NUMBER = 2;
    public const TIMESTAMP = 3;
    public const TOTAL_WITH_VAT = 4;
    public const VAT_TOTAL = 5;

    /**
     * Maximum value length, imposed by the single length byte.
     */
    public const MAX_VALUE_BYTES = 255;

    private int $tag;

    private string $value;

    public function __construct(int $tag, string $value)
    {
        if ($tag < 1 || $tag > 255) {
            throw new InvalidFieldException(
                sprintf('Tag number must be between 1 and 255, %d given.', $tag)
            );
        }

        $length = strlen($value);

        if ($length > self::MAX_VALUE_BYTES) {
            throw new InvalidFieldException(sprintf(
                'Value for tag %d is %d bytes long; the TLV length byte allows at most %d.',
                $tag,
                $length,
                self::MAX_VALUE_BYTES
            ));
        }

        $this->tag = $tag;
        $this->value = $value;
    }

    public function tag(): int
    {
        return $this->tag;
    }

    public function value(): string
    {
        return $this->value;
    }

    /**
     * Byte length of the value, as written into the length byte.
     */
    public function length(): int
    {
        return strlen($this->value);
    }

    /**
     * Encode this field as raw TLV bytes.
     */
    public function toBytes(): string
    {
        return chr($this->tag) . chr($this->length()) . $this->value;
    }

    public function __toString(): string
    {
        return $this->toBytes();
    }
}
