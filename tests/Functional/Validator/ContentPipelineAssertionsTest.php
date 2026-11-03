<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContentPipelineAssertionsTest extends TestCase
{
    private const string CONTENT_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Content Pipeline API
  version: 1.0.0
paths: []
components:
  schemas:
    EncodedObject:
      type: string
      contentEncoding: base64
      contentMediaType: application/json
      contentSchema:
        type: object
        required:
          - a
YAML;

    #[Test]
    public function encoded_instance_matching_content_schema_passes_in_assertions_mode(): void
    {
        $validator = $this->buildValidator(true);

        $validator->validateSchema(base64_encode('{"a":1}'), '#/components/schemas/EncodedObject');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function encoded_instance_violating_content_schema_fails_in_assertions_mode(): void
    {
        $validator = $this->buildValidator(true);

        $this->expectException(ValidationException::class);

        $validator->validateSchema(base64_encode('{"b":1}'), '#/components/schemas/EncodedObject');
    }

    #[Test]
    public function encoded_instance_violating_content_schema_passes_in_annotations_mode(): void
    {
        $validator = $this->buildValidator(false);

        $validator->validateSchema(base64_encode('{"b":1}'), '#/components/schemas/EncodedObject');

        $this->addToAssertionCount(1);
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
