<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

final class MissingSecurityCredentialsError extends AbstractValidationError
{
    public function __construct(
        protected readonly string $schemeName,
        protected readonly string $schemeType,
        protected readonly string $location,
    ) {
        parent::__construct(
            message: 'Authentication required: missing or invalid credentials',
            keyword: 'security',
            dataPath: '/security',
            schemaPath: '/security',
            params: [],
        );
    }

    public function schemeName(bool $reveal = false): string
    {
        return $reveal ? $this->schemeName : '<redacted>';
    }

    public function schemeType(bool $reveal = false): string
    {
        return $reveal ? $this->schemeType : '<redacted>';
    }

    public function location(bool $reveal = false): string
    {
        return $reveal ? $this->location : '<redacted>';
    }
}
