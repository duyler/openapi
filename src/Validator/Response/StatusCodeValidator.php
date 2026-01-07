<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Validator\Exception\UndefinedResponseException;

final readonly class StatusCodeValidator
{
    /**
     * @param array<string, Response> $responses
     */
    public function validate(int $statusCode, array $responses): void
    {
        // Check exact match
        if (isset($responses[(string) $statusCode])) {
            return;
        }

        // Check range (2XX, 3XX, etc.)
        $range = $this->getRange($statusCode);
        if (isset($responses[$range])) {
            return;
        }

        // Check default
        if (isset($responses['default'])) {
            return;
        }

        throw new UndefinedResponseException($statusCode, array_keys($responses));
    }

    private function getRange(int $statusCode): string
    {
        $firstDigit = (int) floor($statusCode / 100);

        return $firstDigit . 'XX';
    }
}
