<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Parser\YamlParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Schema;

class ExclusiveMinMaxParserVersioningTest extends TestCase
{
    #[Test]
    public function openapi_30_exclusive_minimum_bool_converted_to_number(): void
    {
        $yaml = $this->buildYaml('3.0.3', "                type: integer\n                minimum: 5\n                exclusiveMinimum: true");

        $schema = $this->getSchemaFromResponse($yaml);

        self::assertNotNull($schema);
        self::assertSame(5.0, $schema->exclusiveMinimum);
        self::assertSame(5.0, $schema->minimum);
    }

    #[Test]
    public function openapi_30_exclusive_maximum_bool_converted_to_number(): void
    {
        $yaml = $this->buildYaml('3.0.3', "                type: integer\n                maximum: 10\n                exclusiveMaximum: true");

        $schema = $this->getSchemaFromResponse($yaml);

        self::assertNotNull($schema);
        self::assertSame(10.0, $schema->exclusiveMaximum);
        self::assertSame(10.0, $schema->maximum);
    }

    #[Test]
    public function openapi_30_exclusive_minimum_false_ignored(): void
    {
        $yaml = $this->buildYaml('3.0.3', "                type: integer\n                minimum: 5\n                exclusiveMinimum: false");

        $schema = $this->getSchemaFromResponse($yaml);

        self::assertNotNull($schema);
        self::assertNull($schema->exclusiveMinimum);
        self::assertSame(5.0, $schema->minimum);
    }

    #[Test]
    public function openapi_31_exclusive_minimum_as_number(): void
    {
        $yaml = $this->buildYaml('3.1.0', "                type: integer\n                exclusiveMinimum: 5");

        $schema = $this->getSchemaFromResponse($yaml);

        self::assertNotNull($schema);
        self::assertSame(5.0, $schema->exclusiveMinimum);
    }

    #[Test]
    public function openapi_32_exclusive_minimum_as_number(): void
    {
        $yaml = $this->buildYaml('3.2.0', "                type: integer\n                exclusiveMinimum: 5");

        $schema = $this->getSchemaFromResponse($yaml);

        self::assertNotNull($schema);
        self::assertSame(5.0, $schema->exclusiveMinimum);
    }

    #[Test]
    public function openapi_31_exclusive_maximum_as_number(): void
    {
        $yaml = $this->buildYaml('3.1.0', "                type: integer\n                exclusiveMaximum: 10");

        $schema = $this->getSchemaFromResponse($yaml);

        self::assertNotNull($schema);
        self::assertSame(10.0, $schema->exclusiveMaximum);
    }

    private function buildYaml(string $openapiVersion, string $schemaYaml): string
    {
        return <<<YAML
openapi: "{$openapiVersion}"
info:
  title: Test API
  version: "1.0.0"
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
          content:
            application/json:
              schema:
{$schemaYaml}
YAML;
    }

    private function getSchemaFromResponse(string $yaml): ?Schema
    {
        $parser = new YamlParser();
        $document = $parser->parse($yaml);

        return $document->paths->paths['/test']->get->responses->responses['200']
            ->content->mediaTypes['application/json']->schema;
    }
}
