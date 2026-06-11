<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Server;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Server\ServerUrlMismatchException;
use Duyler\OpenApi\Validator\Server\ServerUrlValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerUrlValidatorTest extends TestCase
{
    private const string SERVERS_YAML = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
servers:
  - url: https://{env}.api.example.com/v{version}
    description: Main server
    variables:
      env:
        default: prod
        enum:
          - prod
          - staging
      version:
        default: "1"
  - url: https://static.api.example.com
    description: Static server
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
YAML;

    #[Test]
    public function validates_matching_server_url(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SERVERS_YAML)
            ->build();

        $serverValidator = new ServerUrlValidator();

        $serverValidator->validate($validator->document, 'https://prod.api.example.com/v1');

        $this->assertTrue(true);
    }

    #[Test]
    public function validates_static_server_url(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SERVERS_YAML)
            ->build();

        $serverValidator = new ServerUrlValidator();

        $serverValidator->validate($validator->document, 'https://static.api.example.com');

        $this->assertTrue(true);
    }

    #[Test]
    public function throws_for_non_matching_url(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SERVERS_YAML)
            ->build();

        $serverValidator = new ServerUrlValidator();

        $this->expectException(ServerUrlMismatchException::class);

        $serverValidator->validate($validator->document, 'https://unknown.example.com');
    }

    #[Test]
    public function is_valid_returns_true_for_match(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SERVERS_YAML)
            ->build();

        $serverValidator = new ServerUrlValidator();

        $this->assertTrue($serverValidator->isValid($validator->document, 'https://prod.api.example.com/v1'));
    }

    #[Test]
    public function is_valid_returns_false_for_no_match(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SERVERS_YAML)
            ->build();

        $serverValidator = new ServerUrlValidator();

        $this->assertFalse($serverValidator->isValid($validator->document, 'https://unknown.example.com'));
    }

    #[Test]
    public function matches_url_with_path_suffix(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SERVERS_YAML)
            ->build();

        $serverValidator = new ServerUrlValidator();

        $this->assertTrue($serverValidator->isValid($validator->document, 'https://prod.api.example.com/v1/users'));
    }

    #[Test]
    public function matches_with_variable_overrides(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SERVERS_YAML)
            ->build();

        $serverValidator = new ServerUrlValidator();

        $this->assertTrue(
            $serverValidator->isValid(
                $validator->document,
                'https://staging.api.example.com/v2',
                ['env' => 'staging', 'version' => '2'],
            ),
        );
    }

    #[Test]
    public function find_matching_servers_returns_matching(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SERVERS_YAML)
            ->build();

        $serverValidator = new ServerUrlValidator();

        $matches = $serverValidator->findMatchingServers(
            $validator->document,
            'https://prod.api.example.com/v1',
        );

        $this->assertCount(1, $matches);
        $this->assertSame('https://{env}.api.example.com/v{version}', $matches[0]->url);
    }

    #[Test]
    public function passes_when_no_servers_defined(): void
    {
        $yaml = <<<YAML
openapi: 3.1.0
info:
  title: Test API
  version: 1.0.0
paths:
  /test:
    get:
      responses:
        '200':
          description: OK
YAML;

        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        $serverValidator = new ServerUrlValidator();

        $serverValidator->validate($validator->document, 'https://anything.example.com');

        $this->assertTrue(true);
    }
}
