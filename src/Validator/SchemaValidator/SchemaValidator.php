<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Registry\DefaultValidatorRegistry;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function assert;

final readonly class SchemaValidator implements SchemaValidatorInterface
{
    public readonly FormatRegistry $formatRegistry;

    public function __construct(
        private readonly ValidatorPool $pool,
        ?FormatRegistry $formatRegistry = null,
        private readonly ?ValidatorRegistryInterface $registry = null,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::create();
    }

    #[Override]
    public function validate(array|int|string|float|bool|null $data, Schema $schema, ?ValidationContext $context = null): void
    {
        $registry = $this->registry ?? new DefaultValidatorRegistry($this->pool, $this->formatRegistry);

        foreach ($registry->getAllValidators() as $validator) {
            assert($validator instanceof SchemaValidatorInterface);
            $validator->validate($data, $schema, $context);
        }
    }
}
