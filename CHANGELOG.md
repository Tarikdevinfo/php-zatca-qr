# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-11-06

### Added

- `ZatcaQr` builder producing the ZATCA Phase 1 payload as Base64, raw TLV or hex.
- `Tag` value object encoding a single Tag-Length-Value field, measuring the
  value in bytes so Arabic seller names are encoded correctly.
- Validation of the Saudi VAT registration number format, seller name,
  amounts and TLV value length.
- `ZatcaQr::fromArray()` for building from an associative array.
- Automatic conversion of a `DateTimeInterface` to UTC ISO 8601 with a `Z` suffix.
- Test suite asserting the output against the example published with the
  ZATCA specification.

[1.0.0]: https://github.com/Tarikdevinfo/php-zatca-qr/releases/tag/v1.0.0
