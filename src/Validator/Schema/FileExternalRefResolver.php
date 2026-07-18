<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\ExternalSchemaBuilder;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

use Override;

use function array_key_exists;
use function file_exists;
use function file_get_contents;
use function in_array;
use function is_array;
use function json_decode;
use function pathinfo;
use function preg_match;
use function realpath;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;

use function substr;

use const JSON_THROW_ON_ERROR;
use const PATHINFO_EXTENSION;

/**
 * Builtin external $ref resolver for file:// URIs and relative-path refs.
 *
 * Security policy:
 * - Allows only the file:// scheme and scheme-less relative paths. Every
 *   other scheme is rejected with ExternalRefSecurityException. PHP
 *   registers dozens of stream wrappers (php://, phar://, data://,
 *   compress.zlib://, zip://, expect://, ssh2://, rar://, ogg://, glob://
 *   and more) that would otherwise allow LFI, deserialization gadgets and
 *   inline schema injection. A whitelist is the only defence that does not
 *   lag behind newly registered wrappers.
 * - When an optional allowedRoot is configured, refuses to read files whose
 *   realpath escapes that root (defends against ../ traversal and symlinks).
 *
 * The resolver is a pure loader: it does not cache loaded schemas (that is the
 * responsibility of RefResolver::$cache). It parses YAML and JSON payloads and
 * supports an optional JSON Pointer suffix (e.g. 'user.yaml#/UserSchema').
 */
final readonly class FileExternalRefResolver implements ExternalRefResolverInterface
{
    /**
     * Schemes allowed for external $ref resolution. Anything outside this
     * list is rejected with ExternalRefSecurityException. The empty string
     * represents a relative path without a scheme.
     */
    private const array ALLOWED_SCHEMES = [
        '',
        'file',
    ];

    private const string FILE_SCHEME_PREFIX = 'file://';

    public function __construct(
        private ?string $allowedRoot = null,
        private ExternalSchemaBuilder $schemaBuilder = new ExternalSchemaBuilder(),
    ) {}

    #[Override]
    public function resolve(string $ref): Schema
    {
        $scheme = $this->extractScheme($ref);

        if (!in_array($scheme, self::ALLOWED_SCHEMES, true)) {
            throw new ExternalRefSecurityException(
                $ref,
                sprintf(
                    "External ref denied: scheme '%s' is not in allowlist. "
                    . 'Allowed: file://, relative path. Inject a custom '
                    . 'ExternalRefResolverInterface implementation for '
                    . 'network or other scheme access.',
                    $scheme,
                ),
            );
        }

        [$filePath, $pointer] = $this->splitFilePathAndPointer($ref);
        $absolutePath = $this->resolveFilePath($filePath);
        $this->assertPathWithinAllowedRoot($absolutePath);

        if (!file_exists($absolutePath)) {
            throw new RuntimeException(sprintf('External ref file not found: %s', $absolutePath));
        }

        $contents = file_get_contents($absolutePath);
        if (false === $contents) {
            throw new RuntimeException(sprintf('Failed to read external ref file: %s', $absolutePath));
        }

        $data = $this->parseContents($contents, $absolutePath);
        $target = $this->navigatePointer($data, $pointer);

        if (!is_array($target)) {
            throw new RuntimeException(sprintf(
                'External ref target is not a schema object in %s%s',
                $absolutePath,
                '' === $pointer ? '' : ' at #' . $pointer,
            ));
        }

        return $this->schemaBuilder->buildSchemaFromData($target);
    }

    private function extractScheme(string $ref): string
    {
        if (1 === preg_match('/^(?<scheme>[a-z][a-z0-9+.-]*):/i', $ref, $matches)) {
            return strtolower($matches['scheme']);
        }

        return '';
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitFilePathAndPointer(string $ref): array
    {
        $refWithoutScheme = str_starts_with($ref, self::FILE_SCHEME_PREFIX)
            ? substr($ref, strlen(self::FILE_SCHEME_PREFIX))
            : $ref;

        $fragmentPos = strpos($refWithoutScheme, '#');
        if (false === $fragmentPos) {
            return [$refWithoutScheme, ''];
        }

        $filePath = substr($refWithoutScheme, 0, $fragmentPos);
        $pointer = substr($refWithoutScheme, $fragmentPos + 1);

        if (str_starts_with($pointer, '/')) {
            $pointer = substr($pointer, 1);
        }

        return [$filePath, $pointer];
    }

    private function resolveFilePath(string $filePath): string
    {
        if ('' === $filePath) {
            throw new RuntimeException('External ref has empty file path');
        }

        $resolved = realpath($filePath);
        if (false !== $resolved) {
            return $resolved;
        }

        return $filePath;
    }

    private function assertPathWithinAllowedRoot(string $filePath): void
    {
        if (null === $this->allowedRoot || '' === $this->allowedRoot) {
            return;
        }

        $realRoot = realpath($this->allowedRoot);
        $realFile = realpath($filePath);

        if (false === $realFile || false === $realRoot) {
            throw new ExternalRefSecurityException(
                $filePath,
                'External ref path resolution failed (file or root does not exist)',
            );
        }

        if ($realFile === $realRoot) {
            return;
        }

        if (!str_starts_with($realFile, $realRoot . '/')) {
            throw new ExternalRefSecurityException(
                $filePath,
                'External ref path traversal detected: file outside allowed root',
            );
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function parseContents(string $contents, string $filePath): array
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        $data = match ($extension) {
            'json' => json_decode($contents, true, 512, JSON_THROW_ON_ERROR),
            'yaml', 'yml' => Yaml::parse($contents, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE),
            default => throw new RuntimeException(sprintf('Unsupported file extension: %s', $extension)),
        };

        if (!is_array($data)) {
            throw new RuntimeException(sprintf(
                'External ref file does not contain a mapping: %s',
                $filePath,
            ));
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return mixed
     */
    private function navigatePointer(array $data, string $pointer): mixed
    {
        if ('' === $pointer) {
            return $data;
        }

        /** @var mixed $current */
        $current = $data;
        $segments = explode('/', $pointer);

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                throw new RuntimeException(sprintf(
                    'External ref JSON Pointer segment "%s" not found',
                    $segment,
                ));
            }

            /** @var mixed $current */
            $current = $current[$segment];
        }

        return $current;
    }
}
