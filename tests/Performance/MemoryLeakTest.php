<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Performance;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

final class MemoryLeakTest extends TestCase
{
    #[Test]
    public function no_memory_leak_on_repeated_validation(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->build();

        $initialMemory = memory_get_usage();

        for ($i = 0; $i < 100; $i++) {
            $request = $this->createPsr7Request('/pets', 'GET');
            try {
                $validator->validateRequest($request);
            } catch (Exception) {
            }
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $growth = ($finalMemory - $initialMemory) / $initialMemory * 100;

        self::assertLessThan(50, $growth, 'Memory growth should be less than 50%');
    }

    #[Test]
    public function no_memory_leak_on_schema_parsing(): void
    {
        $initialMemory = memory_get_usage();

        for ($i = 0; $i < 10; $i++) {
            $validator = OpenApiValidatorBuilder::create()
                ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
                ->build();
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $growth = ($finalMemory - $initialMemory) / $initialMemory * 100;

        self::assertLessThan(20, $growth, 'Memory growth should be less than 20%');
    }

    #[Test]
    public function weakmap_prevents_memory_leaks(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlFile(__DIR__ . '/../fixtures/petstore.yaml')
            ->build();

        $initialMemory = memory_get_usage();

        for ($i = 0; $i < 100; $i++) {
            $request = $this->createPsr7Request('/pets', 'GET');
            try {
                $validator->validateRequest($request);
            } catch (Exception) {
            }

            if (0 === $i % 20) {
                gc_collect_cycles();
            }
        }

        gc_collect_cycles();
        $finalMemory = memory_get_usage();
        $growth = ($finalMemory - $initialMemory) / $initialMemory * 100;

        self::assertLessThan(30, $growth, 'WeakMap should prevent memory leaks');
    }

    private function createPsr7Request(
        string $uri,
        string $method,
        array $headers = [],
        string $body = '',
    ): object {
        $request = $this->createMock(ServerRequestInterface::class);

        $request
            ->method('getMethod')
            ->willReturn($method);

        $uriMock = $this->createMock(UriInterface::class);
        $uriMock
            ->method('getPath')
            ->willReturn($uri);
        $uriMock
            ->method('getQuery')
            ->willReturn('');

        $request
            ->method('getUri')
            ->willReturn($uriMock);

        $request
            ->method('getHeaders')
            ->willReturn($headers);

        $request
            ->method('getHeaderLine')
            ->willReturnCallback(function ($headerName) use ($headers) {
                if ('Content-Type' === $headerName) {
                    return 'application/json';
                }

                if ('Cookie' === $headerName) {
                    return '';
                }

                return '';
            });

        $stream = $this->createMock(StreamInterface::class);
        $stream
            ->method('__toString')
            ->willReturn($body);

        $request
            ->method('getBody')
            ->willReturn($stream);

        return $request;
    }
}
