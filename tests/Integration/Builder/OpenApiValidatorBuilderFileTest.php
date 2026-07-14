<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Builder;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;

/** @internal */
final class OpenApiValidatorBuilderFileTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/openapi_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        array_map(unlink(...), glob($this->tempDir . '/*') ?: []);
        rmdir($this->tempDir);
    }

    #[Test]
    public function build_from_yaml_file(): void
    {
        $path = $this->writeYamlSpec(<<<YAML
openapi: '3.1.0'
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      operationId: getUsers
      responses:
        '200':
          description: OK
YAML);

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile($path)
            ->build();

        $this->assertNotNull($validator);
    }

    #[Test]
    public function build_from_json_file(): void
    {
        $path = $this->writeJsonSpec(json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/users' => [
                    'get' => [
                        'operationId' => 'getUsers',
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ]));

        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonFile($path)
            ->build();

        $this->assertNotNull($validator);
    }

    #[Test]
    public function build_throws_for_nonexistent_file(): void
    {
        $this->expectException(BuilderException::class);

        OpenApiValidatorBuilder::create()
            ->fromYamlFile('/nonexistent/path/openapi.yaml')
            ->build();
    }

    #[Test]
    public function build_throws_without_spec(): void
    {
        $this->expectException(BuilderException::class);
        $this->expectExceptionMessage('Spec not loaded');

        OpenApiValidatorBuilder::create()->build();
    }

    #[Test]
    public function build_from_yaml_string_validates_request(): void
    {
        $yaml = <<<YAML
openapi: '3.1.0'
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      operationId: getUsers
      parameters:
        - name: limit
          in: query
          schema:
            type: integer
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $this->assertNotNull($validator);
    }

    #[Test]
    public function build_with_coercion(): void
    {
        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [
                '/test' => [
                    'get' => [
                        'responses' => ['200' => ['description' => 'OK']],
                    ],
                ],
            ],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($json)
            ->enableCoercion()
            ->build();

        $this->assertNotNull($validator);
    }

    #[Test]
    public function build_with_custom_format(): void
    {
        $formatValidator = new class implements FormatValidatorInterface {
            public function validate(mixed $data): void {}
        };

        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($json)
            ->withFormat('string', 'custom', $formatValidator)
            ->build();

        $this->assertNotNull($validator);
    }

    #[Test]
    public function build_with_event_dispatcher(): void
    {
        $dispatcher = new ArrayDispatcher([]);

        $json = json_encode([
            'openapi' => '3.1.0',
            'info' => ['title' => 'Test', 'version' => '1.0.0'],
            'paths' => [],
        ]);

        $validator = OpenApiValidatorBuilder::create()
            ->fromJsonString($json)
            ->withEventDispatcher($dispatcher)
            ->build();

        $this->assertNotNull($validator);
    }

    private function writeYamlSpec(string $content): string
    {
        $path = $this->tempDir . '/openapi.yaml';
        file_put_contents($path, $content);

        return $path;
    }

    private function writeJsonSpec(string $content): string
    {
        $path = $this->tempDir . '/openapi.json';
        file_put_contents($path, $content);

        return $path;
    }
}
