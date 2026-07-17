<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Override;

use function assert;
use function is_string;

final readonly class PatternValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->pattern && '' !== $schema->pattern;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_string($data) || null === $schema->pattern || '' === $schema->pattern) {
            return;
        }

        $pattern = $this->regexValidator()->normalize($schema->pattern);

        assert('' !== $pattern);

        $result = $this->pregExecutor()->match($pattern, $data);

        if (false === $result) {
            throw new InvalidPatternException(
                pattern: $schema->pattern,
                reason: 'Pattern validation failed',
            );
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
