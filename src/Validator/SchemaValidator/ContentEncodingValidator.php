<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;

use function base64_decode;
use function is_string;

final readonly class ContentEncodingValidator implements SchemaValidatorInterface
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->contentEncoding || false === is_string($data)) {
            return;
        }

        match ($schema->contentEncoding) {
            'base64' => $this->validateBase64($data),
            default => null,
        };
    }

    private function validateBase64(string $data): void
    {
        $decoded = base64_decode($data, strict: true);

        if (false === $decoded || base64_encode($decoded) !== $data) {
            throw new InvalidContentEncodingException(
                $data,
                'base64',
            );
        }
    }
}
