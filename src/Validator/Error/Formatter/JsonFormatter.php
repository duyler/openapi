<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Override;
use ValueError;

/**
 * JSON error formatter for structured output
 *
 * Produces JSON-formatted error messages suitable for API responses.
 */
readonly class JsonFormatter implements ErrorFormatterInterface
{
    #[Override]
    public function format(AbstractValidationError $error): string
    {
        $data = [
            'breadcrumb' => $error->dataPath(),
            'message' => $error->getMessage(),
            'type' => $error->getType(),
            'details' => $this->getDetails($error),
        ];

        if (null !== $error->suggestion()) {
            $data['suggestion'] = $error->suggestion();
        }

        $result = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $result) {
            throw new ValueError('Failed to encode error data to JSON');
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDetails(AbstractValidationError $error): array
    {
        return $error->params();
    }

    #[Override]
    public function formatMultiple(array $errors): string
    {
        $decodedErrors = [];
        foreach ($errors as $error) {
            $decoded = json_decode($this->format($error), true);
            if (false === is_array($decoded)) {
                throw new ValueError('Failed to decode formatted error to array');
            }
            $decodedErrors[] = $decoded;
        }

        $data = [
            'errors' => $decodedErrors,
            'count' => count($errors),
        ];

        $result = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (false === $result) {
            throw new ValueError('Failed to encode error data to JSON');
        }

        return $result;
    }
}
