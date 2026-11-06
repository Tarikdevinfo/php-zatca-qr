<?php

declare(strict_types=1);

namespace Pixelweb\ZatcaQr\Exception;

/**
 * Thrown when a field is present but does not satisfy the ZATCA rules.
 */
final class InvalidFieldException extends ZatcaQrException
{
}
