<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Server;

use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Validator\Server\ServerUrlResolver;
use Duyler\OpenApi\Validator\Server\ServerVariableException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerUrlResolverTest extends TestCase
{
    private ServerUrlResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ServerUrlResolver();
    }

    #[Test]
    public function resolves_url_with_template_variables(): void
    {
        $server = new Server(
            url: 'https://{env}.api.example.com/v{version}',
            variables: [
                'env' => ['default' => 'prod', 'enum' => ['prod', 'staging']],
                'version' => ['default' => '1'],
            ],
        );

        $result = $this->resolver->resolve($server);

        $this->assertSame('https://prod.api.example.com/v1', $result);
    }

    #[Test]
    public function resolves_url_with_variable_overrides(): void
    {
        $server = new Server(
            url: 'https://{env}.api.example.com/v{version}',
            variables: [
                'env' => ['default' => 'prod'],
                'version' => ['default' => '1'],
            ],
        );

        $result = $this->resolver->resolve($server, ['env' => 'staging', 'version' => '2']);

        $this->assertSame('https://staging.api.example.com/v2', $result);
    }

    #[Test]
    public function resolves_url_without_variables(): void
    {
        $server = new Server(
            url: 'https://api.example.com/v1',
        );

        $result = $this->resolver->resolve($server);

        $this->assertSame('https://api.example.com/v1', $result);
    }

    #[Test]
    public function throws_exception_for_missing_variable(): void
    {
        $server = new Server(
            url: 'https://{env}.api.example.com',
        );

        $this->expectException(ServerVariableException::class);

        $this->resolver->resolve($server);
    }

    #[Test]
    public function extract_variable_names_from_template(): void
    {
        $server = new Server(
            url: 'https://{env}.api.example.com/v{version}',
        );

        $names = $this->resolver->extractVariableNames($server);

        $this->assertSame(['env', 'version'], $names);
    }

    #[Test]
    public function extract_variable_names_from_plain_url(): void
    {
        $server = new Server(
            url: 'https://api.example.com/v1',
        );

        $names = $this->resolver->extractVariableNames($server);

        $this->assertSame([], $names);
    }

    #[Test]
    public function validate_variables_passes_for_complete_set(): void
    {
        $server = new Server(
            url: 'https://{env}.api.example.com',
            variables: [
                'env' => ['default' => 'prod'],
            ],
        );

        $this->resolver->validateVariables($server);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_variables_throws_for_missing(): void
    {
        $server = new Server(
            url: 'https://{env}.api.example.com/v{version}',
            variables: [
                'env' => ['default' => 'prod'],
            ],
        );

        $this->expectException(ServerVariableException::class);

        $this->resolver->validateVariables($server);
    }

    #[Test]
    public function override_takes_precedence_over_default(): void
    {
        $server = new Server(
            url: 'https://{env}.api.com',
            variables: [
                'env' => ['default' => 'prod'],
            ],
        );

        $result = $this->resolver->resolve($server, ['env' => 'dev']);

        $this->assertSame('https://dev.api.com', $result);
    }
}
