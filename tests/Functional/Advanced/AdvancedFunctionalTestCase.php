<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Advanced;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

abstract class AdvancedFunctionalTestCase extends TestCase
{
    protected Psr17Factory $psrFactory;

    protected function setUp(): void
    {
        $this->psrFactory = new Psr17Factory();
    }

    protected function createValidator(string $specFile): OpenApiValidator
    {
        return OpenApiValidatorBuilder::create()
            ->fromYamlFile($specFile)
            ->build();
    }

    protected function createRequest(string $method, string $path, array $body = []): ServerRequestInterface
    {
        $request = $this->psrFactory->createServerRequest($method, $path);

        if ([] !== $body) {
            $request = $request->withHeader('Content-Type', 'application/json');
            $request = $request->withBody($this->psrFactory->createStream(json_encode($body)));
        }

        return $request;
    }

    protected function createResponse(int $statusCode, array $body = []): ResponseInterface
    {
        $response = $this->psrFactory->createResponse($statusCode);

        if ([] !== $body) {
            $response = $response->withHeader('Content-Type', 'application/json');
            $response = $response->withBody($this->psrFactory->createStream(json_encode($body)));
        }

        return $response;
    }
}
