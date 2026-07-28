<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Dto\ValidatorDependencies;

use function is_string;

/**
 * Regression test for the {@see ValidatorDependencies}
 * / {@see \Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies} /
 * {@see \Duyler\OpenApi\Validator\Validation\ValidatorDependencies} short-name collision
 * (audit item B-API-3). Two of the three classes are marked `@internal` so
 * static analyzers flag user dependencies on the internal wiring DTOs; the public
 * 1.0 surface (used by {@see OpenApiValidatorBuilder} as
 * `ValidationAssembler`) stays unmarked. Renaming the public class would be a BC
 * break locked out by the 1.x stability contract.
 */
final class ValidatorDependenciesInternalMarkTest extends TestCase
{
    #[Test]
    public function dto_validator_dependencies_is_marked_internal(): void
    {
        $reflection = new ReflectionClass(ValidatorDependencies::class);
        $docComment = $reflection->getDocComment();

        self::assertNotFalse($docComment, 'Dto\ValidatorDependencies must carry a PHPDoc block.');
        self::assertStringContainsString('@internal', $docComment);
    }

    #[Test]
    public function schema_validator_validator_dependencies_is_marked_internal(): void
    {
        $reflection = new ReflectionClass(\Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies::class);
        $docComment = $reflection->getDocComment();

        self::assertNotFalse($docComment, 'SchemaValidator\ValidatorDependencies must carry a PHPDoc block.');
        self::assertStringContainsString('@internal', $docComment);
    }

    #[Test]
    public function validation_validator_dependencies_is_not_marked_internal(): void
    {
        $reflection = new ReflectionClass(\Duyler\OpenApi\Validator\Validation\ValidatorDependencies::class);
        $docComment = $reflection->getDocComment();

        // The public 1.0 surface (consumed by OpenApiValidatorBuilder as
        // `ValidationAssembler`) must not carry the @internal marker. A missing
        // class-level PHPDoc block is the strongest form of compliance.
        if (is_string($docComment)) {
            self::assertStringNotContainsString(
                '@internal',
                $docComment,
                'Validation\ValidatorDependencies is the public 1.0 surface consumed by '
                . 'OpenApiValidatorBuilder; it must not carry the @internal marker.',
            );
        } else {
            self::assertFalse(
                $reflection->isInternal(),
                'Validation\ValidatorDependencies must remain a userland (non-built-in) class.',
            );
        }
    }

    #[Test]
    public function all_three_classes_remain_loadable_after_internal_mark(): void
    {
        // BC guard: the @internal marker must not break existing imports.
        self::assertTrue(class_exists(ValidatorDependencies::class));
        self::assertTrue(class_exists(\Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies::class));
        self::assertTrue(class_exists(\Duyler\OpenApi\Validator\Validation\ValidatorDependencies::class));
    }
}
