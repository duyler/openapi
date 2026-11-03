<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use Duyler\OpenApi\Schema\Parser\OpenApiBuilder;
use Duyler\OpenApi\Validator\Dto\DialectProfile;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpenApiBuilderDialectProfileTest extends TestCase
{
    #[Test]
    public function build_document_computes_openapi_30_profile_for_3_0_version(): void
    {
        $builder = new ExposedContextOpenApiBuilder();

        $builder->buildFromArray([
            'openapi' => '3.0.3',
            'info' => ['title' => 'Legacy API', 'version' => '1.0.0'],
        ]);

        $profile = $builder->exposedContext()->dialectProfile;

        self::assertFalse($profile->allowTypeArray);
        self::assertFalse($profile->refSiblingsApply);
        self::assertTrue($profile->nullableRequiresType);
    }

    #[Test]
    public function build_document_computes_openapi_31_profile_for_3_1_version(): void
    {
        $builder = new ExposedContextOpenApiBuilder();

        $builder->buildFromArray([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Modern API', 'version' => '1.0.0'],
        ]);

        $profile = $builder->exposedContext()->dialectProfile;

        self::assertTrue($profile->allowTypeArray);
        self::assertTrue($profile->refSiblingsApply);
        self::assertFalse($profile->nullableRequiresType);
    }

    #[Test]
    public function build_document_defaults_context_profile_to_openapi_31(): void
    {
        $context = new OpenApiBuildContext();

        self::assertEquals(
            DialectProfile::forOpenApi31(),
            $context->dialectProfile,
        );
    }
}

final class ExposedContextOpenApiBuilder extends OpenApiBuilder
{
    public function buildFromArray(array $data): OpenApiDocument
    {
        return $this->buildDocument($data);
    }

    public function exposedContext(): OpenApiBuildContext
    {
        return $this->context;
    }

    #[Override]
    protected function parseContent(string $content): mixed
    {
        return [];
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'test';
    }
}
