<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Dto;

use Duyler\OpenApi\Validator\Dto\DialectProfile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DialectProfileTest extends TestCase
{
    #[Test]
    public function for_open_api_31_allows_type_array_and_ref_siblings(): void
    {
        $profile = DialectProfile::forOpenApi31();

        self::assertTrue($profile->allowTypeArray);
        self::assertTrue($profile->refSiblingsApply);
        self::assertFalse($profile->nullableRequiresType);
    }

    #[Test]
    public function for_open_api_30_rejects_type_array_and_ref_siblings(): void
    {
        $profile = DialectProfile::forOpenApi30();

        self::assertFalse($profile->allowTypeArray);
        self::assertFalse($profile->refSiblingsApply);
        self::assertTrue($profile->nullableRequiresType);
    }

    #[Test]
    public function from_open_api_version_resolves_30_for_3_0_0(): void
    {
        $profile = DialectProfile::fromOpenApiVersion('3.0.0');

        self::assertEquals(
            DialectProfile::forOpenApi30(),
            $profile,
        );
        self::assertFalse($profile->allowTypeArray);
        self::assertFalse($profile->refSiblingsApply);
        self::assertTrue($profile->nullableRequiresType);
    }

    #[Test]
    public function from_open_api_version_resolves_30_for_3_0_3(): void
    {
        $profile = DialectProfile::fromOpenApiVersion('3.0.3');

        self::assertFalse($profile->allowTypeArray);
        self::assertFalse($profile->refSiblingsApply);
        self::assertTrue($profile->nullableRequiresType);
    }

    #[Test]
    public function from_open_api_version_resolves_31_for_3_1_0(): void
    {
        $profile = DialectProfile::fromOpenApiVersion('3.1.0');

        self::assertTrue($profile->allowTypeArray);
        self::assertTrue($profile->refSiblingsApply);
        self::assertFalse($profile->nullableRequiresType);
    }

    #[Test]
    public function from_open_api_version_resolves_31_for_3_2_0(): void
    {
        $profile = DialectProfile::fromOpenApiVersion('3.2.0');

        self::assertTrue($profile->allowTypeArray);
        self::assertTrue($profile->refSiblingsApply);
        self::assertFalse($profile->nullableRequiresType);
    }

    #[Test]
    public function from_open_api_version_resolves_31_for_null(): void
    {
        $profile = DialectProfile::fromOpenApiVersion(null);

        self::assertTrue($profile->allowTypeArray);
        self::assertTrue($profile->refSiblingsApply);
        self::assertFalse($profile->nullableRequiresType);
    }

    #[Test]
    public function named_factories_are_immutable_value_objects(): void
    {
        $first = DialectProfile::forOpenApi31();
        $second = DialectProfile::forOpenApi31();

        self::assertNotSame($first, $second);
        self::assertEquals($first, $second);
    }
}
