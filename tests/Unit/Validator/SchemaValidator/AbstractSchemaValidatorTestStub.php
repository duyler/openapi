<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\SchemaValidator\AbstractSchemaValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Override;

/**
 * Test-only concrete subclass exposing protected hooks of
 * AbstractSchemaValidator so line branches can be exercised
 * directly without going through a heavy production subclass.
 *
 * @internal
 */
final readonly class AbstractSchemaValidatorTestStub extends AbstractSchemaValidator
{
    public function __construct(ValidatorDependencies $dependencies)
    {
        parent::__construct($dependencies);
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        // No-op concrete implementation: tests in AbstractSchemaValidatorTest
        // target the shared protected helpers, not the validate() entry point.
    }

    public function exposeGetDataPath(?ValidationContext $context): string
    {
        return $this->getDataPath($context);
    }

    /**
     * @param string|list<string>|null $type
     */
    public function exposeFormatSchemaType(array|string|null $type, string $default = 'scalar'): string
    {
        return $this->formatSchemaType($type, $default);
    }

    public function exposeCreateSchemaValidator(): SchemaValidatorInterface
    {
        return $this->createSchemaValidator();
    }

    public function exposeDependencies(): ValidatorDependencies
    {
        return $this->dependencies;
    }
}
