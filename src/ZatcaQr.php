<?php

declare(strict_types=1);

namespace Pixelweb\ZatcaQr;

use DateTimeInterface;
use DateTimeZone;
use Pixelweb\ZatcaQr\Exception\InvalidFieldException;
use Pixelweb\ZatcaQr\Exception\MissingFieldException;

/**
 * Builds the QR code payload required on Saudi tax invoices (ZATCA Phase 1).
 *
 * The payload carries five fields, encoded as Tag-Length-Value and then
 * Base64. The resulting string is what goes inside the QR image printed
 * on the invoice, and what the ZATCA "VAT" mobile application reads.
 *
 * @see https://zatca.gov.sa/
 */
final class ZatcaQr
{
    /**
     * Saudi VAT registration numbers are 15 digits and both start and end with 3.
     */
    private const VAT_NUMBER_PATTERN = '/^3\d{13}3$/';

    private ?string $sellerName = null;

    private ?string $vatNumber = null;

    private ?string $timestamp = null;

    private ?string $totalWithVat = null;

    private ?string $vatTotal = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * Build from an associative array.
     *
     * Recognised keys: seller_name, vat_number, timestamp,
     * total_with_vat, vat_total.
     *
     * @param array<string, mixed> $fields
     */
    public static function fromArray(array $fields): self
    {
        $qr = new self();

        if (isset($fields['seller_name'])) {
            $qr->sellerName((string) $fields['seller_name']);
        }

        if (isset($fields['vat_number'])) {
            $qr->vatNumber((string) $fields['vat_number']);
        }

        if (isset($fields['timestamp'])) {
            $timestamp = $fields['timestamp'];
            $qr->timestamp($timestamp instanceof DateTimeInterface ? $timestamp : (string) $timestamp);
        }

        if (isset($fields['total_with_vat'])) {
            $qr->totalWithVat($fields['total_with_vat']);
        }

        if (isset($fields['vat_total'])) {
            $qr->vatTotal($fields['vat_total']);
        }

        return $qr;
    }

    /**
     * Legal name of the seller, in Arabic or Latin script.
     */
    public function sellerName(string $name): self
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidFieldException('The seller name cannot be empty.');
        }

        $this->sellerName = $name;

        return $this;
    }

    /**
     * Seller VAT registration number: 15 digits, starting and ending with 3.
     */
    public function vatNumber(string $vatNumber): self
    {
        $vatNumber = trim($vatNumber);

        if (preg_match(self::VAT_NUMBER_PATTERN, $vatNumber) !== 1) {
            throw new InvalidFieldException(sprintf(
                'A Saudi VAT registration number must be 15 digits starting and ending with 3; "%s" given.',
                $vatNumber
            ));
        }

        $this->vatNumber = $vatNumber;

        return $this;
    }

    /**
     * Invoice date and time.
     *
     * A DateTimeInterface is converted to UTC and formatted as ISO 8601
     * with a Z suffix. A string is accepted as-is, so a value already in
     * the exact form the invoice shows can be passed through unchanged.
     *
     * @param DateTimeInterface|string $timestamp
     */
    public function timestamp($timestamp): self
    {
        if ($timestamp instanceof DateTimeInterface) {
            $utc = (new \DateTimeImmutable('@' . $timestamp->getTimestamp()))
                ->setTimezone(new DateTimeZone('UTC'));

            $this->timestamp = $utc->format('Y-m-d\TH:i:s\Z');

            return $this;
        }

        $timestamp = trim((string) $timestamp);

        if ($timestamp === '') {
            throw new InvalidFieldException('The timestamp cannot be empty.');
        }

        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * Invoice total including VAT.
     *
     * @param float|int|string $amount
     */
    public function totalWithVat($amount): self
    {
        $this->totalWithVat = $this->normaliseAmount($amount, 'total with VAT');

        return $this;
    }

    /**
     * Total VAT charged on the invoice.
     *
     * @param float|int|string $amount
     */
    public function vatTotal($amount): self
    {
        $this->vatTotal = $this->normaliseAmount($amount, 'VAT total');

        return $this;
    }

    /**
     * The five fields, in ZATCA order.
     *
     * @return array<int, Tag>
     */
    public function tags(): array
    {
        $this->assertComplete();

        /** @var string $sellerName */
        $sellerName = $this->sellerName;
        /** @var string $vatNumber */
        $vatNumber = $this->vatNumber;
        /** @var string $timestamp */
        $timestamp = $this->timestamp;
        /** @var string $totalWithVat */
        $totalWithVat = $this->totalWithVat;
        /** @var string $vatTotal */
        $vatTotal = $this->vatTotal;

        return [
            new Tag(Tag::SELLER_NAME, $sellerName),
            new Tag(Tag::VAT_NUMBER, $vatNumber),
            new Tag(Tag::TIMESTAMP, $timestamp),
            new Tag(Tag::TOTAL_WITH_VAT, $totalWithVat),
            new Tag(Tag::VAT_TOTAL, $vatTotal),
        ];
    }

    /**
     * Raw TLV bytes. Binary — not safe to print.
     */
    public function toTlv(): string
    {
        $bytes = '';

        foreach ($this->tags() as $tag) {
            $bytes .= $tag->toBytes();
        }

        return $bytes;
    }

    /**
     * The value to encode into the QR image.
     */
    public function toBase64(): string
    {
        return base64_encode($this->toTlv());
    }

    /**
     * Lowercase hex of the TLV bytes, for debugging and test fixtures.
     */
    public function toHex(): string
    {
        return bin2hex($this->toTlv());
    }

    public function __toString(): string
    {
        return $this->toBase64();
    }

    /**
     * Format an amount the way ZATCA expects: a plain decimal with two places.
     *
     * @param float|int|string $amount
     */
    private function normaliseAmount($amount, string $label): string
    {
        if (is_string($amount)) {
            $amount = trim($amount);
        }

        if (!is_numeric($amount)) {
            throw new InvalidFieldException(sprintf(
                'The %s must be numeric; %s given.',
                $label,
                is_string($amount) ? sprintf('"%s"', $amount) : gettype($amount)
            ));
        }

        $value = (float) $amount;

        if ($value < 0) {
            throw new InvalidFieldException(sprintf('The %s cannot be negative.', $label));
        }

        return number_format($value, 2, '.', '');
    }

    private function assertComplete(): void
    {
        $missing = [];

        if ($this->sellerName === null) {
            $missing[] = 'seller name';
        }

        if ($this->vatNumber === null) {
            $missing[] = 'VAT number';
        }

        if ($this->timestamp === null) {
            $missing[] = 'timestamp';
        }

        if ($this->totalWithVat === null) {
            $missing[] = 'total with VAT';
        }

        if ($this->vatTotal === null) {
            $missing[] = 'VAT total';
        }

        if ($missing !== []) {
            throw new MissingFieldException(sprintf(
                'Cannot build the ZATCA payload, missing: %s.',
                implode(', ', $missing)
            ));
        }
    }
}
