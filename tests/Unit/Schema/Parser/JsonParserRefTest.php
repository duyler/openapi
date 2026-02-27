<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonParserRefTest extends TestCase
{
    private JsonParser $parser;

    protected function setUp(): void
    {
        $this->parser = new JsonParser();
    }

    #[Test]
    public function parameter_with_ref_parses_correctly(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{"/test":{"get":{"parameters":[{"$ref":"#/components/parameters/TestId"}],"responses":{"200":{"description":"OK"}}}}},"components":{"parameters":{"TestId":{"name":"id","in":"path","required":true,"schema":{"type":"string"}}}}}';

        $document = $this->parser->parse($json);

        self::assertNotNull($document->paths);
        self::assertArrayHasKey('/test', $document->paths->paths);
        $pathItem = $document->paths->paths['/test'];
        self::assertNotNull($pathItem->get);
        self::assertNotNull($pathItem->get->parameters);
        self::assertCount(1, $pathItem->get->parameters->parameters);
        $param = $pathItem->get->parameters->parameters[0];
        self::assertNotNull($param->ref);
        self::assertSame('#/components/parameters/TestId', $param->ref);
        self::assertNull($param->name);
        self::assertNull($param->in);
    }

    #[Test]
    public function response_with_ref_parses_correctly(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{"/test":{"get":{"responses":{"200":{"$ref":"#/components/responses/Success"}}}}},"components":{"responses":{"Success":{"description":"Success"}}}}';

        $document = $this->parser->parse($json);

        self::assertNotNull($document->paths);
        self::assertArrayHasKey('/test', $document->paths->paths);
        $pathItem = $document->paths->paths['/test'];
        self::assertNotNull($pathItem->get);
        self::assertNotNull($pathItem->get->responses);
        self::assertArrayHasKey('200', $pathItem->get->responses->responses);
        $response = $pathItem->get->responses->responses['200'];
        self::assertNotNull($response->ref);
        self::assertSame('#/components/responses/Success', $response->ref);
        self::assertNull($response->description);
    }

    #[Test]
    public function parameter_without_ref_requires_name_and_in(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{"/test":{"get":{"parameters":[{"description":"Missing name and in"}],"responses":{"200":{"description":"OK"}}}}}}';

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Parameter must have name and in fields');

        $this->parser->parse($json);
    }

    #[Test]
    public function json_yaml_equivalence_for_parameter_ref(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{"/test":{"get":{"parameters":[{"$ref":"#/components/parameters/TestId"}],"responses":{"200":{"description":"OK"}}}}},"components":{"parameters":{"TestId":{"name":"id","in":"path","required":true,"schema":{"type":"string"}}}}}';

        $yaml = <<<'YAML'
openapi: "3.0.0"
info:
  title: Test
  version: "1.0"
paths:
  /test:
    get:
      parameters:
        - $ref: "#/components/parameters/TestId"
      responses:
        "200":
          description: OK
components:
  parameters:
    TestId:
      name: id
      in: path
      required: true
      schema:
        type: string
YAML;

        $jsonDocument = $this->parser->parse($json);
        $yamlParser = new YamlParser();
        $yamlDocument = $yamlParser->parse($yaml);

        $jsonParam = $jsonDocument->paths?->paths['/test']->get?->parameters?->parameters[0];
        $yamlParam = $yamlDocument->paths?->paths['/test']->get?->parameters?->parameters[0];

        self::assertNotNull($jsonParam->ref);
        self::assertNotNull($yamlParam->ref);
        self::assertSame($jsonParam->ref, $yamlParam->ref);
    }

    #[Test]
    public function json_yaml_equivalence_for_response_ref(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{"/test":{"get":{"responses":{"200":{"$ref":"#/components/responses/Success"}}}}},"components":{"responses":{"Success":{"description":"Success"}}}}';

        $yaml = <<<'YAML'
openapi: "3.0.0"
info:
  title: Test
  version: "1.0"
paths:
  /test:
    get:
      responses:
        "200":
          $ref: "#/components/responses/Success"
components:
  responses:
    Success:
      description: Success
YAML;

        $jsonDocument = $this->parser->parse($json);
        $yamlParser = new YamlParser();
        $yamlDocument = $yamlParser->parse($yaml);

        $jsonResponse = $jsonDocument->paths?->paths['/test']->get?->responses?->responses['200'];
        $yamlResponse = $yamlDocument->paths?->paths['/test']->get?->responses?->responses['200'];

        self::assertNotNull($jsonResponse->ref);
        self::assertNotNull($yamlResponse->ref);
        self::assertSame($jsonResponse->ref, $yamlResponse->ref);
    }

    #[Test]
    public function parameter_with_ref_in_components(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{},"components":{"parameters":{"RefParam":{"$ref":"#/components/parameters/BaseParam"},"BaseParam":{"name":"base","in":"query"}}}}';

        $document = $this->parser->parse($json);

        self::assertNotNull($document->components);
        self::assertNotNull($document->components->parameters);
        self::assertArrayHasKey('RefParam', $document->components->parameters);
        $refParam = $document->components->parameters['RefParam'];
        self::assertNotNull($refParam->ref);
        self::assertSame('#/components/parameters/BaseParam', $refParam->ref);
    }

    #[Test]
    public function response_with_ref_in_components(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{},"components":{"responses":{"RefResponse":{"$ref":"#/components/responses/BaseResponse"},"BaseResponse":{"description":"Base response"}}}}';

        $document = $this->parser->parse($json);

        self::assertNotNull($document->components);
        self::assertNotNull($document->components->responses);
        self::assertArrayHasKey('RefResponse', $document->components->responses);
        $refResponse = $document->components->responses['RefResponse'];
        self::assertNotNull($refResponse->ref);
        self::assertSame('#/components/responses/BaseResponse', $refResponse->ref);
    }

    #[Test]
    public function parameter_with_ref_ignores_other_fields(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{"/test":{"get":{"parameters":[{"$ref":"#/components/parameters/TestId","name":"ignored","in":"query"}],"responses":{"200":{"description":"OK"}}}}},"components":{"parameters":{"TestId":{"name":"id","in":"path"}}}}';

        $document = $this->parser->parse($json);

        $param = $document->paths?->paths['/test']->get?->parameters?->parameters[0];
        self::assertNotNull($param->ref);
        self::assertSame('#/components/parameters/TestId', $param->ref);
        self::assertNull($param->name);
        self::assertNull($param->in);
    }

    #[Test]
    public function response_with_ref_ignores_other_fields(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{"/test":{"get":{"responses":{"200":{"$ref":"#/components/responses/Success","description":"ignored"}}}}},"components":{"responses":{"Success":{"description":"Success"}}}}';

        $document = $this->parser->parse($json);

        $response = $document->paths?->paths['/test']->get?->responses?->responses['200'];
        self::assertNotNull($response->ref);
        self::assertSame('#/components/responses/Success', $response->ref);
        self::assertNull($response->description);
    }

    #[Test]
    public function multiple_parameters_with_ref(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{"/test":{"get":{"parameters":[{"$ref":"#/components/parameters/Param1"},{"$ref":"#/components/parameters/Param2"}],"responses":{"200":{"description":"OK"}}}}},"components":{"parameters":{"Param1":{"name":"param1","in":"query"},"Param2":{"name":"param2","in":"header"}}}}';

        $document = $this->parser->parse($json);

        $params = $document->paths?->paths['/test']->get?->parameters?->parameters;
        self::assertCount(2, $params);
        self::assertSame('#/components/parameters/Param1', $params[0]->ref);
        self::assertSame('#/components/parameters/Param2', $params[1]->ref);
    }

    #[Test]
    public function multiple_responses_with_ref(): void
    {
        $json = '{"openapi":"3.0.0","info":{"title":"Test","version":"1.0"},"paths":{"/test":{"get":{"responses":{"200":{"$ref":"#/components/responses/Success"},"400":{"$ref":"#/components/responses/Error"}}}}},"components":{"responses":{"Success":{"description":"OK"},"Error":{"description":"Bad Request"}}}}';

        $document = $this->parser->parse($json);

        $responses = $document->paths?->paths['/test']->get?->responses?->responses;
        self::assertCount(2, $responses);
        self::assertSame('#/components/responses/Success', $responses['200']->ref);
        self::assertSame('#/components/responses/Error', $responses['400']->ref);
    }
}
