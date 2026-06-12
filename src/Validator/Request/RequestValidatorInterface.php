<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Operation;
use Psr\Http\Message\ServerRequestInterface;

interface RequestValidatorInterface
{
    public function validate(
        ServerRequestInterface $request,
        Operation $operation,
        string $pathTemplate,
    ): void;
}
