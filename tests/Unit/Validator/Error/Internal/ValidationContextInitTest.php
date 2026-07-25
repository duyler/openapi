<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error\Internal;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\BreadcrumbManager;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationContextInit::class)]
#[CoversClass(ValidationContext::class)]
final class ValidationContextInitTest extends TestCase
{
    #[Test]
    public function from_init_uses_defaults_when_init_has_only_pool(): void
    {
        $pool = new ValidatorPool();

        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));

        self::assertSame($pool, $context->pool);
        self::assertInstanceOf(SimpleFormatter::class, $context->errorFormatter);
        self::assertTrue($context->nullableAsType);
        self::assertSame(EmptyArrayStrategy::AllowBoth, $context->emptyArrayStrategy);
        self::assertNull($context->mode);
        self::assertInstanceOf(BreadcrumbManager::class, $context->breadcrumbs);
        self::assertSame('/', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function from_init_propagates_all_fields(): void
    {
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();
        $mode = ValidatorMode::Request;

        $context = ValidationContext::fromInit(
            new ValidationContextInit(
                pool: $pool,
                errorFormatter: $formatter,
                nullableAsType: false,
                emptyArrayStrategy: EmptyArrayStrategy::Reject,
                mode: $mode,
            ),
        );

        self::assertSame($pool, $context->pool);
        self::assertSame($formatter, $context->errorFormatter);
        self::assertFalse($context->nullableAsType);
        self::assertSame(EmptyArrayStrategy::Reject, $context->emptyArrayStrategy);
        self::assertSame($mode, $context->mode);
    }

    #[Test]
    public function from_init_yields_equivalent_state_to_create_static_constructor(): void
    {
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();

        $viaInit = ValidationContext::fromInit(
            new ValidationContextInit(
                pool: $pool,
                errorFormatter: $formatter,
                nullableAsType: false,
                emptyArrayStrategy: EmptyArrayStrategy::PreferArray,
            ),
        );

        // Equivalence: same field values as the deprecated create()
        // (depth defaults to 0 in both paths, breadcrumbs are independent instances)
        self::assertSame($pool, $viaInit->pool);
        self::assertSame($formatter, $viaInit->errorFormatter);
        self::assertFalse($viaInit->nullableAsType);
        self::assertSame(EmptyArrayStrategy::PreferArray, $viaInit->emptyArrayStrategy);
        self::assertSame(0, $viaInit->depth());
    }
}
