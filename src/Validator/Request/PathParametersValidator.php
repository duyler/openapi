<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Override;

readonly class PathParametersValidator extends AbstractParameterValidator
{
    #[Override]
    protected function getLocation(): string
    {
        return 'path';
    }

    #[Override]
    protected function findParameter(array $data, string $name): mixed
    {
        return $data[$name] ?? null;
    }
}
