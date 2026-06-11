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
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function assert;

final class SchemaValidator implements SchemaValidatorInterface
{
    public readonly FormatRegistry $formatRegistry;
    private ?ValidatorRegistryInterface $cachedRegistry = null;

    public function __construct(
        private readonly ValidatorPool $pool,
        ?FormatRegistry $formatRegistry = null,
        private readonly ?ValidatorRegistryInterface $registry = null,
        private readonly bool $strictFormats = false,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $reportDeprecated = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::instance();
    }

    #[Override]
    public function validate(array|int|string|float|bool|null $data, Schema $schema, ?ValidationContext $context = null): void
    {
        $registry = $this->registry ?? $this->cachedRegistry ??= new DefaultValidatorRegistry(
            $this->pool,
            $this->formatRegistry,
            $this->strictFormats,
            $this->logger,
            $this->reportDeprecated,
            $this->eventDispatcher,
        );

        foreach ($registry->getAllValidators() as $validator) {
            assert($validator instanceof SchemaValidatorInterface);
            $validator->validate($data, $schema, $context);
        }
    }
}
