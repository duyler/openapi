<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\Internal\CustomKeywordValidatorAdapter;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CustomKeywordValidatorAdapterTest extends TestCase
{
    #[Test]
    public function is_applicable_delegates_to_wrapped_validator(): void
    {
        $validator = new SpyKeywordValidator(applies: true);
        $adapter = new CustomKeywordValidatorAdapter($validator, new ValidatorPool());

        self::assertTrue($adapter->isApplicable(new Schema(type: 'integer')));

        $notApplicable = new CustomKeywordValidatorAdapter(
            new SpyKeywordValidator(applies: false),
            new ValidatorPool(),
        );

        self::assertFalse($notApplicable->isApplicable(new Schema(type: 'integer')));
    }

    #[Test]
    public function validate_passes_data_schema_and_context_to_wrapped_validator(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $schema = new Schema(type: 'integer');
        $validator = new SpyKeywordValidator(applies: true);
        $adapter = new CustomKeywordValidatorAdapter($validator, new ValidatorPool());

        $adapter->validate(3, $schema, $context);

        self::assertSame(3, $validator->data);
        self::assertSame($schema, $validator->schema);
        self::assertSame($context, $validator->context);
    }

    #[Test]
    public function validate_without_context_creates_fallback_context(): void
    {
        $validator = new SpyKeywordValidator(applies: true);
        $adapter = new CustomKeywordValidatorAdapter($validator, new ValidatorPool());

        $adapter->validate(3, new Schema(type: 'integer'));

        self::assertInstanceOf(ValidationContext::class, $validator->context);
    }

    #[Test]
    public function validator_returns_wrapped_validator_instance(): void
    {
        $validator = new SpyKeywordValidator(applies: true);
        $adapter = new CustomKeywordValidatorAdapter($validator, new ValidatorPool());

        self::assertSame($validator, $adapter->validator());
    }
}

final class SpyKeywordValidator implements KeywordValidatorInterface
{
    public mixed $data = null;

    public ?Schema $schema = null;

    public ?ValidationContext $context = null;

    public function __construct(
        private readonly bool $applies,
    ) {}

    #[Override]
    public function appliesTo(Schema $schema): bool
    {
        return $this->applies;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ValidationContext $context): void
    {
        $this->data = $data;
        $this->schema = $schema;
        $this->context = $context;
    }
}
