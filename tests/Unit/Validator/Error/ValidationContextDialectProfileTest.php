<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error;

use Duyler\OpenApi\Validator\Dto\DialectProfile;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidationContextDialectProfileTest extends TestCase
{
    #[Test]
    public function from_init_defaults_to_openapi_31_profile(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        self::assertEquals(
            DialectProfile::forOpenApi31(),
            $context->dialectProfile,
        );
    }

    #[Test]
    public function from_init_carries_openapi_30_profile(): void
    {
        $profile = DialectProfile::forOpenApi30();

        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: new ValidatorPool(),
            dialectProfile: $profile,
        ));

        self::assertSame($profile, $context->dialectProfile);
    }

    #[Test]
    public function fork_for_branch_carries_parent_openapi_30_profile(): void
    {
        $profile = DialectProfile::forOpenApi30();
        $parent = ValidationContext::fromInit(new ValidationContextInit(
            pool: new ValidatorPool(),
            dialectProfile: $profile,
        ));

        $child = $parent->forkForBranch();

        self::assertSame($profile, $child->dialectProfile);
        self::assertSame($parent->dialectProfile, $child->dialectProfile);
    }

    #[Test]
    public function fork_for_branch_keeps_default_profile_without_parent_profile(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $child = $parent->forkForBranch();

        self::assertEquals(
            DialectProfile::forOpenApi31(),
            $child->dialectProfile,
        );
    }
}
