<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Override;

use function assert;
use function is_string;

readonly class PatternValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_string($data) || null === $schema->pattern || '' === $schema->pattern) {
            return;
        }

        $pattern = RegexValidator::normalize($schema->pattern);
        RegexValidator::validate($pattern);

        assert($pattern !== '');

        $result = preg_match($pattern, $data);

        if (false === $result) {
            return;
        }

        if (0 === $result) {
            $dataPath = $this->getDataPath($context);
            throw new PatternMismatchError(
                pattern: $schema->pattern,
                dataPath: $dataPath,
                schemaPath: '/pattern',
            );
        }
    }
}
