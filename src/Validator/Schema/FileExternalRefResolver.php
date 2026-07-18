<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\ExternalSchemaBuilder;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefTooLargeException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

use Override;

use function array_key_exists;
use function fclose;
use function feof;
use function fopen;
use function fread;
use function fstat;
use function in_array;
use function is_array;
use function json_decode;
use function min;
use function pathinfo;
use function preg_match;
use function realpath;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;

use function substr;

use const E_WARNING;
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
 * SEC-08 / CWE-209: exception messages omit absolute paths and the
 * configured allowedRoot so a Throwable surfaced by a PSR-15 middleware
 * cannot leak the server filesystem layout. Concrete paths are forwarded
 * to the optional PSR-3 logger at debug level instead.
 *
 * The resolver is a pure loader: it does not cache loaded schemas (that is the
 * responsibility of RefResolver::$cache). It parses YAML and JSON payloads and
 * supports an optional JSON Pointer suffix (e.g. 'user.yaml#/UserSchema').
 */
final readonly class FileExternalRefResolver implements ExternalRefResolverInterface
{
    /**
     * Default cap for external ref file size, defends against DoS via large
     * attacker-controlled files (file:///dev/zero, multi-gigabyte payloads).
     * Override via the $maxBytes constructor argument or the builder method
     * withExternalRefMaxBytes(). Exposed as public so the builder can
     * reference the canonical default without duplicating the literal.
     */
    public const int DEFAULT_MAX_REF_BYTES = 10_485_760;
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

    /**
     * Chunk size used by readWithLimit() when draining the file handle.
     * Balances syscall overhead against memory pressure.
     */
    private const int READ_CHUNK_BYTES = 8192;

    /**
     * POSIX stat mode bits selecting the file type, ANDed with
     * stat['mode'] to obtain the file-type discriminator compared
     * against self::S_IFREG.
     */
    private const int S_IFMT = 0xF000;

    /**
     * POSIX regular-file mode bits. Anything else (character device,
     * directory, FIFO, socket, symlink) is rejected at fstat time to
     * defend against special files like /dev/zero or /dev/null that
     * would otherwise pass scheme/path checks but cause DoS or hang
     * the reader.
     */
    private const int S_IFREG = 0x8000;

    public function __construct(
        private ?string $allowedRoot = null,
        private ExternalSchemaBuilder $schemaBuilder = new ExternalSchemaBuilder(),
        private int $maxBytes = self::DEFAULT_MAX_REF_BYTES,
        private LoggerInterface $logger = new NullLogger(),
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

        $contents = $this->readFileWithLimit($absolutePath);

        $data = $this->parseContents($contents, $absolutePath);
        $target = $this->navigatePointer($data, $pointer, $absolutePath);

        if (!is_array($target)) {
            $this->logger->debug('External ref target is not a schema object', [
                'path' => $absolutePath,
                'pointer' => $pointer,
            ]);

            throw new RuntimeException(
                '' === $pointer
                    ? 'External ref target is not a schema object'
                    : 'External ref target is not a schema object at JSON Pointer',
            );
        }

        return $this->schemaBuilder->buildSchemaFromData($target);
    }

    /**
     * Open the resolved path with fopen() inside a temporary error handler
     * (per project §3 categorical ban on the @ operator), fstat the handle
     * to verify the file is a regular file (rejects /dev/zero, /dev/null,
     * directories, sockets, FIFOs and any symlink survived past realpath),
     * drain the contents through a size-capped read loop, and unconditionally
     * close the handle in a finally block to prevent FD leaks.
     *
     * The error handler swallows only E_WARNING (the level fopen emits on
     * open failures); other levels propagate. We do NOT use filesize(): it
     * is its own syscall and opens a second TOCTOU window between the size
     * check and fread.
     *
     * @return string File contents, never larger than $this->maxBytes
     */
    private function readFileWithLimit(string $absolutePath): string
    {
        set_error_handler(static fn(int $errno) => E_WARNING === $errno);

        try {
            $handle = fopen($absolutePath, 'r');
        } finally {
            restore_error_handler();
        }

        if (false === $handle) {
            $this->logger->debug('Failed to open external ref file', [
                'path' => $absolutePath,
            ]);

            throw new RuntimeException('External ref file not found');
        }

        try {
            $stat = fstat($handle);
            if (false === $stat) {
                $this->logger->debug('Failed to stat external ref file', [
                    'path' => $absolutePath,
                ]);

                throw new RuntimeException('Failed to read external ref file');
            }

            $fileType = $stat['mode'] & self::S_IFMT;
            if (self::S_IFREG !== $fileType) {
                $this->logger->debug('External ref is not a regular file', [
                    'path' => $absolutePath,
                ]);

                throw new ExternalRefSecurityException(
                    $absolutePath,
                    'External ref is not a regular file',
                );
            }

            return $this->readWithLimit($handle, $this->maxBytes);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Drain $handle in bounded chunks until EOF or $maxBytes is reached.
     * If EOF is not reached when the budget is exhausted, the file is
     * larger than the configured cap and we throw ExternalRefTooLargeException
     * to prevent memory exhaustion from attacker-controlled payloads.
     *
     * fread signals EOF lazily: feof() returns true only after an fread
     * that returned empty. When the file size matches maxBytes exactly,
     * fread consumes the last byte without raising the EOF flag, so the
     * main loop exits on the $remaining budget with feof still false.
     * Read one extra byte after the budget is spent to distinguish
     * "exactly at limit" (empty probe, accept) from "strictly over"
     * (non-empty probe, reject).
     *
     * @param resource $handle Opened file resource
     */
    private function readWithLimit($handle, int $maxBytes): string
    {
        $contents = '';
        $remaining = $maxBytes;

        while (!feof($handle) && $remaining > 0) {
            $chunk = fread($handle, min(self::READ_CHUNK_BYTES, $remaining));
            if (false === $chunk) {
                throw new RuntimeException('Failed to read external ref file');
            }
            $contents .= $chunk;
            $remaining -= strlen($chunk);
        }

        if (0 === $remaining) {
            $probe = fread($handle, 1);
            if (false !== $probe && '' !== $probe) {
                throw new ExternalRefTooLargeException(max: $maxBytes);
            }
        }

        return $contents;
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
            $this->logger->debug('External ref path resolution failed', [
                'path' => $filePath,
                'allowedRoot' => $this->allowedRoot,
            ]);

            throw new ExternalRefSecurityException(
                $filePath,
                'External ref path resolution failed',
            );
        }

        if ($realFile === $realRoot) {
            return;
        }

        if (!str_starts_with($realFile, $realRoot . '/')) {
            $this->logger->debug('External ref path traversal detected', [
                'path' => $filePath,
                'allowedRoot' => $this->allowedRoot,
            ]);

            throw new ExternalRefSecurityException(
                $filePath,
                'External ref path traversal detected',
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
            $this->logger->debug('External ref file does not contain a mapping', [
                'path' => $filePath,
            ]);

            throw new RuntimeException('External ref file does not contain a mapping');
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return mixed
     */
    private function navigatePointer(array $data, string $pointer, string $absolutePath): mixed
    {
        if ('' === $pointer) {
            return $data;
        }

        /** @var mixed $current */
        $current = $data;
        $segments = explode('/', $pointer);

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                $this->logger->debug('External ref JSON Pointer segment not found', [
                    'path' => $absolutePath,
                    'segment' => $segment,
                ]);

                throw new RuntimeException(
                    'External ref JSON Pointer segment not found',
                );
            }

            /** @var mixed $current */
            $current = $current[$segment];
        }

        return $current;
    }
}
