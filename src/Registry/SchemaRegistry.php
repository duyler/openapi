<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Registry;

use Duyler\OpenApi\Registry\Exception\SchemaAlreadyRegisteredException;
use Duyler\OpenApi\Registry\Exception\VersionNotFoundException;
use Duyler\OpenApi\Schema\OpenApiDocument;

use RuntimeException;

use function count;
use function sprintf;
use function uksort;

final readonly class SchemaRegistry
{
    /** @var array<string, OpenApiDocument> */
    private readonly array $latestBySchema;

    /** @var array<string, list<string>> */
    private readonly array $sortedVersions;

    /**
     * @param array<string, array<string, OpenApiDocument>> $schemas
     */
    public function __construct(
        private readonly array $schemas = [],
    ) {
        /** @var array<string, OpenApiDocument> $latestBySchema */
        $latestBySchema = [];
        /** @var array<string, list<string>> $sortedVersions */
        $sortedVersions = [];

        foreach ($schemas as $name => $versions) {
            /** @var array<string, OpenApiDocument> $sorted */
            $sorted = $versions;
            uksort($sorted, self::compareVersions(...));

            /** @var list<string> $sortedVersionKeys */
            $sortedVersionKeys = array_keys($sorted);
            $sortedVersions[$name] = $sortedVersionKeys;

            $values = array_values($sorted);
            if ([] === $values) {
                throw new RuntimeException(sprintf(
                    'Schema "%s" must have at least one version registered',
                    $name,
                ));
            }
            $latestBySchema[$name] = $values[count($values) - 1];
        }

        $this->latestBySchema = $latestBySchema;
        $this->sortedVersions = $sortedVersions;
    }

    /**
     * Register a new schema under the given name and version.
     *
     * Fails fast by throwing {@see SchemaAlreadyRegisteredException} when the
     * name+version pair is already registered. Use {@see registerOrReplace()}
     * for explicit overwrite semantics (hot-reload, immutable replacement).
     *
     * @throws SchemaAlreadyRegisteredException When the name+version pair is already registered.
     */
    public function register(string $name, string $version, OpenApiDocument $document): self
    {
        if (isset($this->schemas[$name][$version])) {
            throw new SchemaAlreadyRegisteredException($name, $version);
        }

        return $this->registerOrReplace($name, $version, $document);
    }

    /**
     * Register a schema, replacing any existing entry for the same name+version.
     *
     * Intended for explicit overwrite use cases such as hot-reloading a spec
     * in development or replacing a placeholder document with a final one.
     */
    public function registerOrReplace(string $name, string $version, OpenApiDocument $document): self
    {
        $newSchemas = $this->schemas;
        $newSchemas[$name][$version] = $document;

        return new self($newSchemas);
    }

    public function get(string $name, ?string $version = null): ?OpenApiDocument
    {
        if (null === $version) {
            return $this->latestBySchema[$name] ?? null;
        }

        return $this->schemas[$name][$version] ?? null;
    }

    /**
     * Returns the document for the given schema and version, or throws if missing.
     *
     * Use this method when a missing schema or version is a programming error that
     * must fail fast. For graceful access (e.g. conditional presence checks), use
     * {@see has()} followed by {@see get()}.
     *
     * @throws VersionNotFoundException When the schema name or the requested version is not registered.
     */
    public function getOrFail(string $name, ?string $version = null): OpenApiDocument
    {
        $document = $this->get($name, $version);

        if (null === $document) {
            throw new VersionNotFoundException($name, $version);
        }

        return $document;
    }

    public function has(string $name, ?string $version = null): bool
    {
        if (null === $version) {
            return isset($this->schemas[$name]);
        }

        return isset($this->schemas[$name][$version]);
    }

    /**
     * @return array<string>
     */
    public function getVersions(string $name): array
    {
        return $this->sortedVersions[$name] ?? [];
    }

    /**
     * @return array<string>
     */
    public function getNames(): array
    {
        return array_keys($this->schemas);
    }

    /**
     * Returns the number of distinct schema names registered.
     */
    public function countNames(): int
    {
        return count($this->schemas);
    }

    /**
     * Returns the total number of name+version pairs registered across all schemas.
     */
    public function countSchemas(): int
    {
        $total = 0;
        foreach ($this->schemas as $versions) {
            $total += count($versions);
        }

        return $total;
    }

    public function countVersions(string $name): int
    {
        return count($this->schemas[$name] ?? []);
    }

    private static function compareVersions(string $a, string $b): int
    {
        return version_compare($a, $b);
    }
}
