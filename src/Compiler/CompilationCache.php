<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Compiler\Exception\CompilationCacheException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Serializer\SchemaToArrayConverter;
use InvalidArgumentException;
use JsonException;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use WeakMap;

use function is_string;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class CompilationCache implements CompilationCacheInterface
{
    public const int DEFAULT_TTL = 86400;

    /** @var WeakMap<Schema, string> */
    private WeakMap $hashCache;

    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly string $namespace = 'validator_compilation',
        private readonly int $ttl = self::DEFAULT_TTL,
    ) {
        if ($ttl < 1) {
            throw new InvalidArgumentException(
                sprintf('TTL must be a positive integer, got %d.', $ttl),
            );
        }

        /** @var WeakMap<Schema, string> */
        $this->hashCache = new WeakMap();
    }

    #[Override]
    public function get(string $schemaHash): ?string
    {
        $item = $this->pool->getItem($schemaHash);

        if (false === $item->isHit()) {
            return null;
        }

        $code = $item->get();

        if (false === is_string($code)) {
            return null;
        }

        return $code;
    }

    #[Override]
    public function set(string $schemaHash, string $compiledCode): void
    {
        $item = $this->pool->getItem($schemaHash);
        $item->set($compiledCode);
        $item->expiresAfter($this->ttl);

        $this->pool->save($item);
    }

    #[Override]
    public function generateKey(Schema $schema): string
    {
        $hash = $this->calculateSchemaHash($schema);
        return $this->namespace . '.' . $hash;
    }

    private function calculateSchemaHash(Schema $schema): string
    {
        if ($this->hashCache->offsetExists($schema)) {
            /** @var string */
            return $this->hashCache[$schema];
        }

        /** @var WeakMap<Schema, int> $visited */
        $visited = new WeakMap();
        $data = new SchemaToArrayConverter()->toSnapshotArray($schema, $visited);

        try {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new CompilationCacheException(
                sprintf('Failed to encode schema for hash: %s', $e->getMessage()),
                0,
                $e,
            );
        }

        $hash = hash('sha256', $json);
        $this->hashCache[$schema] = $hash;

        return $hash;
    }
}
