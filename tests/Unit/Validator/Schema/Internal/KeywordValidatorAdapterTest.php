<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\Internal\KeywordValidatorAdapter;
use Duyler\OpenApi\Validator\SchemaValidator\KeywordApplicable;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class KeywordValidatorAdapterTest extends TestCase
{
    #[Test]
    public function applies_to_delegates_to_keyword_applicable_validator(): void
    {
        $applicable = new KeywordValidatorAdapter(new ApplicableTestValidator(true));
        $notApplicable = new KeywordValidatorAdapter(new ApplicableTestValidator(false));

        self::assertTrue($applicable->appliesTo(new Schema(type: 'string')));
        self::assertFalse($notApplicable->appliesTo(new Schema(type: 'string')));
    }

    #[Test]
    public function applies_to_returns_true_for_validator_without_keyword_applicable(): void
    {
        $adapter = new KeywordValidatorAdapter(new PlainTestValidator());

        self::assertTrue($adapter->appliesTo(new Schema(type: 'string')));
    }

    #[Test]
    public function validate_passes_data_schema_and_context_to_validator(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $schema = new Schema(type: 'string');
        $plain = new PlainTestValidator();
        $adapter = new KeywordValidatorAdapter($plain);

        $adapter->validate(['value'], $schema, $context);

        self::assertSame(['value'], $plain->data);
        self::assertSame($schema, $plain->schema);
        self::assertSame($context, $plain->context);
    }

    #[Test]
    public function validator_returns_wrapped_validator_instance(): void
    {
        $plain = new PlainTestValidator();
        $adapter = new KeywordValidatorAdapter($plain);

        self::assertSame($plain, $adapter->validator());
    }
}

final readonly class ApplicableTestValidator implements KeywordApplicable
{
    public function __construct(
        private readonly bool $isApplicable,
    ) {}

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return $this->isApplicable;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void {}
}

final class PlainTestValidator implements SchemaValidatorInterface
{
    public mixed $data = null;

    public ?Schema $schema = null;

    public ?ValidationContext $context = null;

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        $this->data = $data;
        $this->schema = $schema;
        $this->context = $context;
    }
}
