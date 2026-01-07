<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Duyler\OpenApi\Validator\Error\Breadcrumb;
use Override;
use RuntimeException;
use Throwable;

abstract class AbstractValidationError extends RuntimeException implements ValidationErrorInterface
{
    protected readonly string $dataPath;

    public function __construct(
        string $message,
        protected readonly string $keyword,
        string|Breadcrumb $dataPath,
        protected readonly string $schemaPath,
        /**
         * @var array<string, mixed>
         */
        protected readonly array $params,
        protected readonly ?string $suggestion = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
        $this->dataPath = $dataPath instanceof Breadcrumb ? $dataPath->toString() : $dataPath;
    }

    #[Override]
    public function message(): string
    {
        return $this->getMessage();
    }

    #[Override]
    public function keyword(): string
    {
        return $this->keyword;
    }

    #[Override]
    public function dataPath(): string
    {
        return $this->dataPath;
    }

    #[Override]
    public function schemaPath(): string
    {
        return $this->schemaPath;
    }

    #[Override]
    public function params(): array
    {
        return $this->params;
    }

    #[Override]
    public function suggestion(): ?string
    {
        return $this->suggestion;
    }

    #[Override]
    public function getType(): string
    {
        return $this->keyword;
    }
}
