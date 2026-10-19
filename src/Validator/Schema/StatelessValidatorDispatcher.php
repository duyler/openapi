<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\SchemaValidator\KeywordApplicable;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use WeakMap;

use function array_filter;
use function array_values;

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
            $context = ValidationContext::create(pool: $this->pool);
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
        if ($this->applicableValidators->offsetExists($schema)) {
            /** @var list<SchemaValidatorInterface> $cached */
            $cached = $this->applicableValidators[$schema];

            return $cached;
        }

        /** @var list<SchemaValidatorInterface> $applicable */
        $applicable = array_values(array_filter(
            $this->validators,
            static function (SchemaValidatorInterface $validator) use ($schema): bool {
                return false === ($validator instanceof KeywordApplicable) || $validator->isApplicable($schema);
            },
        ));
        $this->applicableValidators[$schema] = $applicable;

        return $applicable;
    }
}
