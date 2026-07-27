<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\Exception\ExternalRefSecurityException;
use Duyler\OpenApi\Validator\Schema\Exception\UnresolvableRefException;
use Duyler\OpenApi\Validator\Schema\ExternalRefResolverInterface;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;
use Duyler\OpenApi\Validator\Schema\RefCache;
use WeakMap;

use function array_key_exists;
use function array_keys;
use function count;
use function implode;
use function is_array;
use function is_object;
use function str_replace;
use function str_starts_with;
use function substr;

/** @internal */
final readonly class DocumentNavigator
{
    private const int REF_ROOT_PREFIX_LENGTH = 2;

    public function __construct(
        private readonly FileExternalRefResolver $builtinFileResolver,
        private readonly ?ExternalRefResolverInterface $externalRefResolver = null,
    ) {}

    /**
     * @param array<string, bool> $visited
     * @param WeakMap<OpenApiDocument, RefCache> $cache
     *
     * @throws SchemaDepthExceededException
     * @throws UnresolvableRefException
     *
     * @return array{Schema|Parameter|Response, array<string, bool>}
     */
    public function resolveRef(
        string $ref,
        OpenApiDocument $document,
        array $visited,
        WeakMap $cache,
        int $depth = 0,
    ): array {
        if ($depth >= ValidationContext::MAX_DEPTH) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        if (false === str_starts_with($ref, '#/')) {
            return $this->resolveExternalRef($ref, $visited);
        }

        $this->assertNotCircular($ref, $visited);
        $visited[$ref] = true;

        $cached = $this->lookupCached($document, $ref, $cache);
        if (null !== $cached) {
            return [$cached, $visited];
        }

        $parts = explode('/', substr($ref, self::REF_ROOT_PREFIX_LENGTH));
        $result = $this->navigateThrowing($ref, $document, $parts);

        if (null !== $result->ref) {
            return $this->resolveRef($result->ref, $document, $visited, $cache, $depth + 1);
        }

        $this->storeCached($document, $ref, $result, $cache);

        return [$result, $visited];
    }

    /**
     * @param array<int, string> $parts
     *
     * @throws SchemaDepthExceededException
     * @throws UnresolvableRefException
     */
    public function navigate(
        object|array $current,
        array $parts,
        int $depth = 0,
        int $maxDepth = ValidationContext::MAX_DEPTH,
    ): Schema|Parameter|Response {
        $count = count($parts);

        for ($i = 0; $i < $count; ++$i) {
            if ($depth + $i >= $maxDepth) {
                throw new SchemaDepthExceededException($maxDepth);
            }

            $decodedSegment = $this->decodePointerSegment($parts[$i]);
            $current = $this->getProperty($current, $decodedSegment);
        }

        if (
            $current instanceof Schema
            || $current instanceof Parameter
            || $current instanceof Response
        ) {
            return $current;
        }

        throw new UnresolvableRefException(
            '',
            'Target is not a Schema, Parameter, or Response',
        );
    }

    public function getProperty(
        object|array $container,
        string $property,
    ): object|array {
        $value = match (true) {
            is_array($container) => array_key_exists($property, $container)
                ? $container[$property]
                : throw new UnresolvableRefException($property, 'Array key does not exist'),
            property_exists($container, $property) => $container->$property,
            default => throw new UnresolvableRefException($property, 'Property does not exist'),
        };

        if (null === $value) {
            throw new UnresolvableRefException($property, 'Value is null');
        }

        if (false === is_object($value) && false === is_array($value)) {
            throw new UnresolvableRefException(
                $property,
                'Value is not an object or array',
            );
        }

        return $value;
    }

    /** @param array<string, bool> $visited */
    public function formatCircularPath(array $visited, string $circularRef): string
    {
        $path = array_keys($visited);
        $path[] = $circularRef;

        return implode(' -> ', $path);
    }

    /** @param array<string, bool> $visited */
    private function assertNotCircular(string $ref, array $visited): void
    {
        if (isset($visited[$ref])) {
            throw new UnresolvableRefException(
                $ref,
                'Circular reference detected',
                internalTrace: $this->formatCircularPath($visited, $ref),
            );
        }
    }

    /** @param array<int, string> $parts */
    private function navigateThrowing(string $ref, OpenApiDocument $document, array $parts): Schema|Parameter|Response
    {
        try {
            return $this->navigate($document, $parts);
        } catch (UnresolvableRefException $e) {
            throw new UnresolvableRefException($ref, $e->reason(), previous: $e);
        }
    }

    /** @param WeakMap<OpenApiDocument, RefCache> $cache */
    private function lookupCached(OpenApiDocument $document, string $ref, WeakMap $cache): Schema|Parameter|Response|null
    {
        if (false === isset($cache[$document])) {
            return null;
        }

        /** @var RefCache $existing */
        $existing = $cache[$document];

        return $existing->map[$ref] ?? null;
    }

    /** @param WeakMap<OpenApiDocument, RefCache> $cache */
    private function storeCached(OpenApiDocument $document, string $ref, Schema|Parameter|Response $result, WeakMap $cache): void
    {
        /** @var RefCache $refCache */
        $refCache = $cache[$document] ?? new RefCache();
        $cache[$document] = $refCache;
        $refCache->map[$ref] = $result;
    }

    /**
     * @param array<string, bool> $visited
     *
     * @return array{Schema|Parameter|Response, array<string, bool>}
     */
    private function resolveExternalRef(string $ref, array $visited): array
    {
        try {
            $resolver = $this->externalRefResolver ?? $this->builtinFileResolver;

            return [$resolver->resolve($ref), $visited];
        } catch (ExternalRefSecurityException $e) {
            throw new UnresolvableRefException(
                $ref,
                'External ref not resolved. Builtin FileExternalRefResolver allows only '
                . 'file:// URIs and scheme-less relative paths; every other scheme '
                . '(http, https, ftp, php, phar, data, compress.zlib, zip, expect, '
                . 'ssh2, rar, ogg, glob, etc.) is rejected. Inject a custom '
                . 'ExternalRefResolverInterface implementation to enable other schemes.',
                previous: $e,
            );
        }
    }

    private function decodePointerSegment(string $segment): string
    {
        return str_replace(['~1', '~0'], ['/', '~'], $segment);
    }
}
