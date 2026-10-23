<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentAssertionsModeTest extends TestCase
{
    private const string CONTENT_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Content API
  version: 1.0.0
paths: []
components:
  schemas:
    Payload:
      type: string
      contentEncoding: base64
      contentMediaType: application/json
YAML;

    public static function content_assertions_provider(): array
    {
        return [
            'default_annotations_only' => [false],
            'enable_content_assertions' => [true],
        ];
    }

    #[Test]
    #[DataProvider('content_assertions_provider')]
    public function invalid_base64_fails_only_with_content_assertions(bool $enableContentAssertions): void
    {
        $validator = $this->buildValidator($enableContentAssertions);

        if ($enableContentAssertions) {
            $this->expectException(ValidationException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        $validator->validateSchema('not-base64!!!', '#/components/schemas/Payload');
    }

    private function buildValidator(bool $enableContentAssertions): OpenApiValidatorInterface
    {
        $builder = OpenApiValidatorBuilder::create()->fromYamlString(self::CONTENT_YAML);

        if ($enableContentAssertions) {
            $builder = $builder->enableContentAssertions();
        }

        return $builder->build();
    }
}
