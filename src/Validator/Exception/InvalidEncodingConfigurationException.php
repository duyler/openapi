<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

final class InvalidEncodingConfigurationException extends RuntimeException
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function mixedModes(): self
    {
        return new self('The encoding field MUST NOT be present if prefixEncoding or itemEncoding are present');
    }

    public static function missingArrayModel(): self
    {
        return new self('To use the prefixEncoding and/or itemEncoding fields, either itemSchema or an array schema MUST be present');
    }
}
