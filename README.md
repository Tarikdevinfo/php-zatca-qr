# php-zatca-qr

[![CI](https://github.com/Tarikdevinfo/php-zatca-qr/actions/workflows/ci.yml/badge.svg)](https://github.com/Tarikdevinfo/php-zatca-qr/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/pixelweb/php-zatca-qr.svg)](https://packagist.org/packages/pixelweb/php-zatca-qr)
[![PHP Version](https://img.shields.io/packagist/dependency-v/pixelweb/php-zatca-qr/php.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)

**Encode the QR payload required on Saudi tax invoices (ZATCA Phase 1) — Tag-Length-Value plus Base64, with zero dependencies.**

Every invoice issued in Saudi Arabia must carry a QR code holding five fields, encoded as TLV and then Base64. Getting the encoding subtly wrong — counting characters instead of bytes on an Arabic seller name, for instance — produces a QR code that the official ZATCA *VAT* application silently fails to read. This library does that one job, correctly.

## Install

```bash
composer require pixelweb/php-zatca-qr
```

Requires PHP 7.4 or later. No dependencies.

## Usage

```php
use Pixelweb\ZatcaQr\ZatcaQr;

$payload = ZatcaQr::make()
    ->sellerName('Bobs Records')
    ->vatNumber('310122393500003')
    ->timestamp('2022-04-25T15:30:00Z')
    ->totalWithVat('1000.00')
    ->vatTotal('150.00')
    ->toBase64();

// AQxCb2JzIFJlY29yZHMCDzMxMDEyMjM5MzUwMDAwMwMUMjAyMi0wNC0yNVQxNTozMDowMFoEBzE...
```

That string is what goes inside the QR image printed on the invoice.

### Arabic seller names

Arabic works without any extra step. The length byte counts **bytes**, not characters, which this library handles for you:

```php
$payload = ZatcaQr::make()
    ->sellerName('شركة تجريبية')
    ->vatNumber('310122393500003')
    ->timestamp(new DateTimeImmutable('now'))
    ->totalWithVat(1150.00)
    ->vatTotal(150.00)
    ->toBase64();
```

### Timestamps

Pass a `DateTimeInterface` and it is converted to UTC and formatted as ISO 8601 with a `Z` suffix, as ZATCA requires:

```php
$riyadh = new DateTimeImmutable('2022-04-25 18:30:00', new DateTimeZone('Asia/Riyadh'));

ZatcaQr::make()->timestamp($riyadh);   // encodes 2022-04-25T15:30:00Z
```

Pass a string instead when you need the exact value printed on the invoice to go through untouched.

### Amounts

Integers, floats and numeric strings are all accepted, and normalised to two decimal places:

```php
->totalWithVat(1000)        // 1000.00
->totalWithVat(1000.5)      // 1000.50
->totalWithVat('1000.456')  // 1000.46
```

### From an array

```php
$payload = ZatcaQr::fromArray([
    'seller_name'    => 'Bobs Records',
    'vat_number'     => '310122393500003',
    'timestamp'      => new DateTimeImmutable('now'),
    'total_with_vat' => 1150.00,
    'vat_total'      => 150.00,
])->toBase64();
```

### Rendering the image

This library produces the payload, not the picture — pick whichever QR renderer you already use:

```php
// endroid/qr-code
$writer->write(Builder::create()->data($payload)->build());

// chillerlan/php-qrcode
echo (new QRCode())->render($payload);
```

## Validation

Invalid input fails fast rather than producing a QR code nobody can read:

| Rule | Exception |
|---|---|
| Seller name must not be empty | `InvalidFieldException` |
| VAT number must be 15 digits, starting and ending with `3` | `InvalidFieldException` |
| Amounts must be numeric and not negative | `InvalidFieldException` |
| A value must fit in the single TLV length byte (255 bytes) | `InvalidFieldException` |
| All five fields must be set before encoding | `MissingFieldException` |

Both extend `Pixelweb\ZatcaQr\Exception\ZatcaQrException`, so one catch block covers the library.

## Inspecting the output

```php
$qr = ZatcaQr::make()/* … */;

$qr->toBase64();  // what goes in the QR
$qr->toTlv();     // raw binary TLV
$qr->toHex();     // lowercase hex, handy in tests
$qr->tags();      // Tag[] — tag number, value and byte length
```

## The encoding

Each field is one tag byte, one length byte, then the raw UTF-8 value:

```
Tag  Field                     Example
 1   Seller name               Bobs Records
 2   VAT registration number   310122393500003
 3   Timestamp (ISO 8601 Z)    2022-04-25T15:30:00Z
 4   Invoice total with VAT    1000.00
 5   VAT total                 150.00
```

Concatenated and Base64-encoded. The test suite asserts the result against the example published with the ZATCA specification.

## Scope

This library covers **Phase 1** (generation). It does not sign invoices, build XML, or report to ZATCA's Phase 2 integration platform.

## Development

```bash
composer install
composer test      # PHPUnit
composer analyse   # PHPStan level 8
composer lint      # PSR-12
composer check     # all three
```

## License

MIT. See [LICENSE](LICENSE).

## Author

[Tarik Leghnimi El Idrissi](https://github.com/Tarikdevinfo) — [Pixelweb](https://pixelweb.ma), Casablanca, Morocco.

Extracted from [Taxvoice](https://wordpress.org/plugins/taxvoice/), a WooCommerce invoicing plugin.
