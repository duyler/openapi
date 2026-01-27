<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\RequestBody;

interface RequestBodyValidatorInterface
{
    public function validate(
        string $body,
        string $contentType,
        ?RequestBody $requestBody,
    ): void;
}
