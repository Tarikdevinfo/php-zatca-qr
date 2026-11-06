<?php

declare(strict_types=1);

namespace Pixelweb\ZatcaQr\Exception;

/**
 * Thrown when the payload is built before every required field is set.
 */
final class MissingFieldException extends ZatcaQrException
{
}
