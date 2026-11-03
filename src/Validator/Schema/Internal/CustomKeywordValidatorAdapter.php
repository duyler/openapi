<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\KeywordApplicable;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

/** @internal */
final readonly class CustomKeywordValidatorAdapter implements KeywordApplicable
{
    public function __construct(
        private readonly KeywordValidatorInterface $validator,
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return $this->validator->appliesTo($schema);
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        $this->validator->validate(
            $data,
            $schema,
            $context ?? ValidationContext::fromInit(new ValidationContextInit(pool: $this->pool)),
        );
    }

    public function validator(): KeywordValidatorInterface
    {
        return $this->validator;
    }
}
