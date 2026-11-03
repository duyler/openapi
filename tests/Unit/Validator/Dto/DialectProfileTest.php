<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Dto;

use Duyler\OpenApi\Validator\Dto\DialectProfile;
use PHPUnit\Framework\Attributes\DataProvider;
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
    #[DataProvider('factory_flags_provider')]
    public function factory_sets_canonical_flags(DialectProfile $profile, array $expectedFlags): void
    {
        self::assertSame(
            $expectedFlags,
            [
                $profile->allowTypeArray,
                $profile->refSiblingsApply,
                $profile->nullableRequiresType,
                $profile->readOnlyRequiredRelaxation,
                $profile->discriminatorIsHint,
            ],
        );
    }

    public static function factory_flags_provider(): array
    {
        return [
            'forOpenApi30 (3.0: relaxation, no hint)' => [
                DialectProfile::forOpenApi30(),
                [false, false, true, true, false],
            ],
            'forOpenApi31 (3.1: required enforced, discriminator decisive)' => [
                DialectProfile::forOpenApi31(),
                [true, true, false, false, false],
            ],
            'forOpenApi32 (3.2: discriminator is hint, no relaxation)' => [
                DialectProfile::forOpenApi32(),
                [true, true, false, false, true],
            ],
        ];
    }

    #[Test]
    #[DataProvider('version_to_profile_provider')]
    public function from_open_api_version_resolves_expected_profile(?string $openapi, DialectProfile $expected): void
    {
        self::assertEquals($expected, DialectProfile::fromOpenApiVersion($openapi));
    }

    public static function version_to_profile_provider(): array
    {
        return [
            '3.0.0 resolves to 30 profile' => ['3.0.0', DialectProfile::forOpenApi30()],
            '3.0.3 resolves to 30 profile' => ['3.0.3', DialectProfile::forOpenApi30()],
            '3.1.0 resolves to 31 profile' => ['3.1.0', DialectProfile::forOpenApi31()],
            '3.2.0 resolves to 32 profile' => ['3.2.0', DialectProfile::forOpenApi32()],
            '3.3.0 resolves to 32 profile' => ['3.3.0', DialectProfile::forOpenApi32()],
            'null resolves to 31 profile' => [null, DialectProfile::forOpenApi31()],
        ];
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
    public function from_open_api_version_resolves_32_for_3_2_0(): void
    {
        $profile = DialectProfile::fromOpenApiVersion('3.2.0');

        self::assertTrue($profile->allowTypeArray);
        self::assertTrue($profile->refSiblingsApply);
        self::assertFalse($profile->nullableRequiresType);
        self::assertFalse($profile->readOnlyRequiredRelaxation);
        self::assertTrue($profile->discriminatorIsHint);
    }

    #[Test]
    public function from_open_api_version_resolves_31_for_null(): void
    {
        $profile = DialectProfile::fromOpenApiVersion(null);

        self::assertTrue($profile->allowTypeArray);
        self::assertTrue($profile->refSiblingsApply);
        self::assertFalse($profile->nullableRequiresType);
        self::assertFalse($profile->readOnlyRequiredRelaxation);
        self::assertFalse($profile->discriminatorIsHint);
    }

    #[Test]
    public function with_read_only_required_relaxation_changes_only_its_flag(): void
    {
        $profile = DialectProfile::forOpenApi31();

        $relaxed = $profile->withReadOnlyRequiredRelaxation(true);

        self::assertTrue($relaxed->readOnlyRequiredRelaxation);
        self::assertFalse($relaxed->discriminatorIsHint);
        self::assertTrue($relaxed->allowTypeArray);
        self::assertTrue($relaxed->refSiblingsApply);
        self::assertFalse($relaxed->nullableRequiresType);
    }

    #[Test]
    public function with_discriminator_is_hint_changes_only_its_flag(): void
    {
        $profile = DialectProfile::forOpenApi31();

        $hinted = $profile->withDiscriminatorIsHint(true);

        self::assertTrue($hinted->discriminatorIsHint);
        self::assertFalse($hinted->readOnlyRequiredRelaxation);
        self::assertTrue($hinted->allowTypeArray);
        self::assertTrue($hinted->refSiblingsApply);
        self::assertFalse($hinted->nullableRequiresType);
    }

    #[Test]
    public function withers_can_disable_their_flags(): void
    {
        $relaxed = DialectProfile::forOpenApi30()->withReadOnlyRequiredRelaxation(false);
        $decisive = DialectProfile::forOpenApi32()->withDiscriminatorIsHint(false);

        self::assertFalse($relaxed->readOnlyRequiredRelaxation);
        self::assertTrue($relaxed->nullableRequiresType);
        self::assertFalse($decisive->discriminatorIsHint);
        self::assertFalse($decisive->nullableRequiresType);
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
