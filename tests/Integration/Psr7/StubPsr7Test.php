<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Psr7;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Nyholm\Psr7\Uri;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

use function array_key_exists;
use function implode;
use function is_array;
use function json_encode;
use function strlen;
use function strtolower;

use const JSON_THROW_ON_ERROR;
use const SEEK_SET;

/**
 * P-100 fallback: a minimal PSR-7 StreamInterface stub proves that the
 * validator depends only on the PSR-7 contract, not on any concrete
 * implementation. The validator must consume the body via the documented
 * StreamInterface methods (getSize / isSeekable / rewind / eof / read)
 * without downcasting to a specific class.
 *
 * @internal
 */
final class StubPsr7Test extends TestCase
{
    private const string SPEC = <<<'YAML'
openapi: 3.2.0
info:
  title: Stub PSR-7 Contract API
  version: 1.0.0
paths:
  /echo:
    post:
      operationId: echoBody
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required:
                - message
              properties:
                message:
                  type: string
                  minLength: 1
      responses:
        '201':
          description: OK
YAML;

    private StubStreamFactory $streamFactory;

    #[Override]
    protected function setUp(): void
    {
        $this->streamFactory = new StubStreamFactory();
    }

    #[Test]
    public function validates_request_with_stub_stream_interface(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $body = $this->streamFactory->createStream(json_encode([
            'message' => 'hello',
        ], JSON_THROW_ON_ERROR));

        $request = new StubServerRequest('POST', '/echo', $body, ['Content-Type' => 'application/json']);

        $operation = $validator->validateRequest($request);

        self::assertSame('POST', $operation->method);
        self::assertSame('/echo', $operation->path);
    }

    #[Test]
    public function surfaces_validation_error_with_stub_stream_interface(): void
    {
        $validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();

        $body = $this->streamFactory->createStream(json_encode([
            'message' => '',
        ], JSON_THROW_ON_ERROR));

        $request = new StubServerRequest('POST', '/echo', $body, ['Content-Type' => 'application/json']);

        $this->expectException(ValidationException::class);

        $validator->validateRequest($request);
    }
}

/**
 * Minimal ServerRequestInterface implementation backed by in-memory state.
 *
 * @internal
 */
final readonly class StubServerRequest implements ServerRequestInterface
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        private string $method,
        private string $uri,
        private StreamInterface $body,
        private array $headers = [],
    ) {}

    public function getServerParams(): array
    {
        return [];
    }

    public function getCookieParams(): array
    {
        return [];
    }

    public function withCookieParams(array $cookies): static
    {
        return $this;
    }

    public function getQueryParams(): array
    {
        return [];
    }

    public function withQueryParams(array $query): static
    {
        return $this;
    }

    public function getUploadedFiles(): array
    {
        return [];
    }

    public function withUploadedFiles(array $uploadedFiles): static
    {
        return $this;
    }

    public function getParsedBody(): array|object|null
    {
        return null;
    }

    public function withParsedBody($data): static
    {
        return $this;
    }

    public function getAttributes(): array
    {
        return [];
    }

    public function getAttribute(string $name, $default = null): mixed
    {
        return $default;
    }

    public function withAttribute(string $name, $value): static
    {
        return $this;
    }

    public function withoutAttribute(string $name): static
    {
        return $this;
    }

    public function getRequestTarget(): string
    {
        return $this->uri;
    }

    public function withRequestTarget(string $requestTarget): static
    {
        return $this;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function withMethod(string $method): static
    {
        return $this;
    }

    public function getUri(): UriInterface
    {
        return new Uri($this->uri);
    }

    public function withUri(UriInterface $uri, bool $preserveHost = false): static
    {
        return $this;
    }

    public function getProtocolVersion(): string
    {
        return '1.1';
    }

    public function withProtocolVersion(string $version): static
    {
        return $this;
    }

    public function getHeaders(): array
    {
        $result = [];
        foreach ($this->headers as $name => $values) {
            $lower = strtolower($name);
            $result[$lower] = is_array($values) ? $values : [$values];
        }

        return $result;
    }

    public function hasHeader(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->getHeaders());
    }

    public function getHeader(string $name): array
    {
        $headers = $this->getHeaders();
        $lower = strtolower($name);

        return $headers[$lower] ?? [];
    }

    public function getHeaderLine(string $name): string
    {
        return implode(',', $this->getHeader($name));
    }

    public function withHeader(string $name, $value): static
    {
        return $this;
    }

    public function withAddedHeader(string $name, $value): static
    {
        return $this;
    }

    public function withoutHeader(string $name): static
    {
        return $this;
    }

    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    public function withBody(StreamInterface $body): static
    {
        return $this;
    }
}

/**
 * Simple in-memory StreamInterface backed by a string buffer.
 *
 * @internal
 */
final class StubStream implements StreamInterface
{
    private int $cursor = 0;

    public function __construct(private readonly string $buffer) {}

    public function __toString(): string
    {
        return $this->buffer;
    }

    public function close(): void {}

    public function detach()
    {
        return null;
    }

    public function getSize(): ?int
    {
        return strlen($this->buffer);
    }

    public function tell(): int
    {
        return $this->cursor;
    }

    public function eof(): bool
    {
        return $this->cursor >= strlen($this->buffer);
    }

    public function isSeekable(): bool
    {
        return true;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        $this->cursor = $offset;
    }

    public function rewind(): void
    {
        $this->cursor = 0;
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        return 0;
    }

    public function isReadable(): bool
    {
        return true;
    }

    public function read(int $length): string
    {
        $remaining = substr($this->buffer, $this->cursor);
        if (false === $remaining || '' === $remaining) {
            return '';
        }
        $chunk = substr($remaining, 0, $length);
        $this->cursor += strlen((string) $chunk);

        return (string) $chunk;
    }

    public function getContents(): string
    {
        return substr($this->buffer, $this->cursor) ?: '';
    }

    public function getMetadata(?string $key = null)
    {
        return null;
    }
}

/**
 * @internal
 */
final class StubStreamFactory
{
    public function createStream(string $content = ''): StreamInterface
    {
        return new StubStream($content);
    }
}
