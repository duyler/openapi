<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\KeywordApplicable;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Override;

use function assert;
use function is_array;
use function is_scalar;

/** @internal */
final readonly class KeywordValidatorAdapter implements KeywordValidatorInterface
{
    public function __construct(
        private readonly SchemaValidatorInterface $validator,
    ) {}

    #[Override]
    public function appliesTo(Schema $schema): bool
    {
        return false === $this->validator instanceof KeywordApplicable
            || $this->validator->isApplicable($schema);
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ValidationContext $context): void
    {
        assert(null === $data || is_scalar($data) || is_array($data));

        $this->validator->validate($data, $schema, $context);
    }

    public function validator(): SchemaValidatorInterface
    {
        return $this->validator;
    }
}
