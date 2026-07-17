<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Registry;

use Duyler\OpenApi\Registry\Exception\VersionNotFoundException;
use Duyler\OpenApi\Schema\OpenApiDocument;

use function count;

final readonly class SchemaRegistry
{
    /**
     * @param array<string, array<string, OpenApiDocument>> $schemas
     */
    public function __construct(
        private readonly array $schemas = [],
    ) {}

    public function register(string $name, string $version, OpenApiDocument $document): self
    {
        $newSchemas = $this->schemas;
        $newSchemas[$name][$version] = $document;

        return new self($newSchemas);
    }

    public function get(string $name, ?string $version = null): ?OpenApiDocument
    {
        if (null === $version) {
            $versions = $this->schemas[$name] ?? [];

            if ([] === $versions) {
                return null;
            }

            uksort($versions, function (string $a, string $b): int {
                return version_compare($a, $b);
            });

            $values = array_values($versions);

            return $values[count($values) - 1];
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
        /** @var array<string> $versions */
        $versions = array_keys($this->schemas[$name] ?? []);

        usort($versions, function (string $a, string $b): int {
            return version_compare($a, $b);
        });

        return $versions;
    }

    /**
     * @return array<string>
     */
    public function getNames(): array
    {
        return array_keys($this->schemas);
    }

    public function count(): int
    {
        return count($this->schemas);
    }

    public function countVersions(string $name): int
    {
        return count($this->schemas[$name] ?? []);
    }
}
