<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\Internal\ApplicableValidators;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use WeakMap;

final class StatelessValidatorDispatcher implements SchemaValidatorInterface
{
    /** @var WeakMap<Schema, list<SchemaValidatorInterface>> */
    private WeakMap $applicableValidators;

    /**
     * @param list<SchemaValidatorInterface> $validators
     */
    public function __construct(
        private readonly array $validators,
        private readonly ValidatorPool $pool,
    ) {
        /** @var WeakMap<Schema, list<SchemaValidatorInterface>> $applicableValidators */
        $applicableValidators = new WeakMap();
        $this->applicableValidators = $applicableValidators;
    }

    #[Override]
    public function validate(array|int|string|float|bool|null $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $context) {
            $context = ValidationContext::fromInit(new ValidationContextInit(pool: $this->pool));
        }

        $context->incrementDepth();

        try {
            foreach ($this->applicableValidators($schema) as $validator) {
                $validator->validate($data, $schema, $context);
            }
        } finally {
            $context->decrementDepth();
        }
    }

    /**
     * @return list<SchemaValidatorInterface>
     */
    private function applicableValidators(Schema $schema): array
    {
        /** @var list<SchemaValidatorInterface> $cached */
        $cached = $this->applicableValidators[$schema] ?? $this->computeApplicableValidators($schema);
        $this->applicableValidators[$schema] = $cached;

        return $cached;
    }

    /**
     * @return list<SchemaValidatorInterface>
     */
    private function computeApplicableValidators(Schema $schema): array
    {
        return ApplicableValidators::filter($this->validators, $schema);
    }
}
