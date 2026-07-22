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
        $result = json_encode(
            $this->buildErrorData($error),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if (false === $result) {
            throw new ValueError('Failed to encode error data to JSON');
        }

        return $result;
    }

    #[Override]
    public function formatMultiple(array $errors): string
    {
        $errorsData = [];

        foreach ($errors as $error) {
            $errorsData[] = $this->buildErrorData($error);
        }

        $result = json_encode(
            [
                'errors' => $errorsData,
                'count' => count($errors),
            ],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        if (false === $result) {
            throw new ValueError('Failed to encode error data to JSON');
        }

        return $result;
    }

    #[Override]
    public function formatException(ValidationException $exception): string
    {
        return $this->formatMultiple($exception->getErrors());
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

        if (null !== $error->suggestion()) {
            $data['suggestion'] = $error->suggestion();
        }

        return $data;
    }
}
