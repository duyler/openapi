<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;
use ValueError;

use function count;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class JsonFormatter implements ErrorFormatterInterface
{
    public function __construct(
        private bool $includeSensitiveValues = false,
    ) {}

    #[Override]
    public function format(ValidationErrorInterface $error): string
    {
        return $this->encode($this->buildErrorData($error));
    }

    #[Override]
    public function formatMultiple(array $errors): string
    {
        return $this->encode([
            'errors' => array_map($this->buildErrorData(...), $errors),
            'count' => count($errors),
        ]);
    }

    #[Override]
    public function formatException(ValidationException $exception): string
    {
        return $this->formatMultiple($exception->getErrors());
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        $result = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if (false === $result) {
            throw new ValueError('Failed to encode error data to JSON');
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildErrorData(ValidationErrorInterface $error): array
    {
        $details = $error->params();

        if ($this->includeSensitiveValues && $error instanceof InvalidFormatException) {
            $details = array_merge($details, ['value' => $error->value(reveal: true)]);
        }

        $data = [
            'breadcrumb' => $error->dataPath(),
            'message' => $error->message(),
            'type' => $error->keyword(),
            'details' => $details,
        ];

        $suggestion = $error->suggestion();
        if (null !== $suggestion) {
            $data['suggestion'] = $suggestion;
        }

        return $data;
    }
}
