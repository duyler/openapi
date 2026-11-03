<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

/**
 * Decodes string instance contents for content keyword assertions.
 */
interface ContentDecoderInterface
{
    public function decodeBase64(string $data): string|false;
}
