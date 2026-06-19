<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Override;
use ValueError;

use function count;

use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class JsonFormatter implements ErrorFormatterInterface
{
    #[Override]
    public function format(ValidationErrorInterface $error): string
    {
        $data = $this->buildErrorData($error);

        $result = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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

        $data = [
            'errors' => $errorsData,
            'count' => count($errors),
        ];

        $result = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
        $data = [
            'breadcrumb' => $error->dataPath(),
            'message' => $error->message(),
            'type' => $error->getType(),
            'details' => $this->getDetails($error),
        ];

        if (null !== $error->suggestion()) {
            $data['suggestion'] = $error->suggestion();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDetails(ValidationErrorInterface $error): array
    {
        return $error->params();
    }
}
