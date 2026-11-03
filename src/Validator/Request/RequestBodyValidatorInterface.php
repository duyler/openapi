<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\RequestBody;
use Psr\Http\Message\StreamInterface;

interface RequestBodyValidatorInterface
{
    public function validate(
        string $body,
        string $contentType,
        ?RequestBody $requestBody,
    ): void;

    public function validateStream(
        StreamInterface $stream,
        string $contentType,
        ?RequestBody $requestBody,
    ): void;
}
