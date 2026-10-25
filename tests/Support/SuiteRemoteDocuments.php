<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Support;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_key_exists;
use function file_get_contents;
use function is_string;
use function json_decode;
use function json_encode;
use function sort;
use function str_ends_with;
use function strlen;
use function str_starts_with;
use function substr;

use const JSON_THROW_ON_ERROR;

final class SuiteRemoteDocuments
{
    private const string ENVELOPE_SCHEMA_NAME = 'Subject';

    private const string JSON_EXTENSION = '.json';

    private const string METASCHEMA_BASE_URI = 'https://json-schema.org/draft/2020-12/';

    /** @var array<string, string>|null */
    private static ?array $documents = null;

    public static function register(OpenApiValidatorBuilder $builder): OpenApiValidatorBuilder
    {
        foreach (self::all() as $retrievalUri => $content) {
            $builder = $builder->withDocument($retrievalUri, $content);
        }

        return $builder;
    }

    /** @return array<string, string> retrieval URI mapped to envelope JSON content */
    public static function all(): array
    {
        return self::$documents ??= array_merge(self::buildRemotes(), self::buildMetaschema());
    }

    /** @return array<string, string> */
    private static function buildMetaschema(): array
    {
        $metaschemaDirectory = JsonSchemaSuiteLocator::metaschemaDirectory();
        $documents = [];

        foreach (self::remoteFiles($metaschemaDirectory) as $remoteFile) {
            $path = (string) $remoteFile->getPathname();
            $relativePath = substr($path, strlen($metaschemaDirectory . '/'));
            $canonicalPath = self::dropJsonExtension($relativePath);
            $retrievalUri = self::METASCHEMA_BASE_URI . $canonicalPath;

            $documents[$retrievalUri] = self::envelope(
                $retrievalUri,
                self::decodeJson((string) file_get_contents($path)),
            );
        }

        return $documents;
    }

    private static function dropJsonExtension(string $path): string
    {
        if (false === str_ends_with($path, self::JSON_EXTENSION)) {
            return $path;
        }

        return substr($path, 0, -strlen(self::JSON_EXTENSION));
    }

    /** @return array<string, string> */
    private static function buildRemotes(): array
    {
        return self::build();
    }

    /** @return array<string, string> */
    private static function build(): array
    {
        $remotesDirectory = JsonSchemaSuiteLocator::remotesDirectory();
        $documents = [];
        foreach (self::remoteFiles($remotesDirectory) as $remoteFile) {
            $path = (string) $remoteFile->getPathname();
            $retrievalUri = JsonSchemaSuiteLocator::REMOTE_BASE_URI
                . substr($path, strlen($remotesDirectory . '/'));

            $documents[$retrievalUri] = self::envelope($retrievalUri, self::decodeJson((string) file_get_contents($path)));
        }

        foreach (self::remoteFiles($remotesDirectory) as $remoteFile) {
            $path = (string) $remoteFile->getPathname();
            $retrievalUri = JsonSchemaSuiteLocator::REMOTE_BASE_URI
                . substr($path, strlen($remotesDirectory . '/'));
            $remote = self::decodeJson((string) file_get_contents($path));
            $resourceId = $remote['$id'] ?? null;

            if (is_string($resourceId) && $resourceId !== $retrievalUri && self::isAbsoluteUri($resourceId)) {
                $documents[$resourceId] ??= self::envelope($retrievalUri, $remote);
            }
        }

        return $documents;
    }

    /**
     * @param array<array-key, mixed> $remote
     */
    private static function envelope(string $documentUri, array $remote): string
    {
        if (false === array_key_exists('$id', $remote)) {
            $remote['$id'] = $documentUri;
        }

        return json_encode([
            'openapi' => '3.1.0',
            'info' => [
                'title' => 'JSON Schema Test Suite remote document',
                'version' => '1.0.0',
            ],
            '$self' => $documentUri,
            'components' => [
                'schemas' => [
                    self::ENVELOPE_SCHEMA_NAME => $remote,
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function decodeJson(string $content): array
    {
        /** @var array<array-key, mixed> $decoded */
        $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private static function isAbsoluteUri(string $uri): bool
    {
        return str_starts_with($uri, 'http://')
            || str_starts_with($uri, 'https://')
            || str_starts_with($uri, 'urn:');
    }

    /** @return list<SplFileInfo> */
    private static function remoteFiles(string $remotesDirectory): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($remotesDirectory),
        );

        $files = [];

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile() && str_ends_with($file->getFilename(), self::JSON_EXTENSION)) {
                $files[] = $file;
            }
        }

        sort($files);

        return $files;
    }
}
