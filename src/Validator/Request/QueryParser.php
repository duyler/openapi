<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use Duyler\OpenApi\Validator\Request\Internal\BracketNotationParser;
use Duyler\OpenApi\Validator\Request\Internal\DeepObjectParser;
use Duyler\OpenApi\Validator\Request\Internal\QuerySplitter;

use function sprintf;

use function substr_count;

final readonly class QueryParser
{
    private const int MAX_QUERY_PAIRS = 1000;

    public function __construct(
        private readonly QuerySplitter $splitter = new QuerySplitter(),
        private readonly DeepObjectParser $deepObjectParser = new DeepObjectParser(),
        private readonly BracketNotationParser $bracketParser = new BracketNotationParser(),
    ) {}

    /**
     * Uses an explicit segment-based parser (insertNested + assignSegments) instead
     * of parse_str because parse_str converts dots in keys to underscores
     * (e.g. `user.name` becomes `user_name`) and keeps only the last value for
     * duplicate scalar keys. This parser preserves key names literally and
     * collects duplicate scalars into indexed arrays to match the structure
     * produced by RFC 6570 form-style explode expansion.
     *
     * @return array<array-key, mixed>
     */
    public function parse(string $queryString): array
    {
        if ('' === $queryString) {
            return [];
        }

        if (substr_count($queryString, '&') + 1 > self::MAX_QUERY_PAIRS) {
            throw new InvalidParameterException(
                'query',
                sprintf('Maximum query string pairs of %d exceeded', self::MAX_QUERY_PAIRS),
            );
        }

        /** @var array<string, non-empty-list<array{rawKey: string, rawValue: string, decodedKey: string}>> $groups */
        $groups = [];
        foreach (explode('&', $queryString) as $rawPair) {
            if ('' === $rawPair) {
                continue;
            }

            ['rawKey' => $rawKey, 'rawValue' => $rawValue] = $this->splitter->splitRawPair($rawPair);
            $decodedKey = urldecode($rawKey);
            $baseKey = $this->splitter->extractBaseKey($decodedKey);

            $groups[$baseKey][] = [
                'rawKey' => $rawKey,
                'rawValue' => $rawValue,
                'decodedKey' => $decodedKey,
            ];
        }

        /** @var array<array-key, mixed> $result */
        $result = [];
        foreach ($groups as $baseKey => $groupPairs) {
            $result[$baseKey] = $this->deepObjectParser->resolveGroup($baseKey, $groupPairs, $this->bracketParser);
        }

        return $result;
    }

    /**
     * @param array<int, scalar> $values
     *
     * @return array<int, scalar>|string
     */
    public function handleExplode(array $values, bool $explode): array|string
    {
        if (false === $explode) {
            return implode(',', $values);
        }

        return $values;
    }

    public function parseQueryString(string $rawQueryString, Parameter $parameter): mixed
    {
        if ('querystring' !== $parameter->in) {
            return null;
        }

        $content = $parameter->content;
        if (null === $content) {
            return null;
        }

        foreach ($content->mediaTypes as $mediaType => $_) {
            return $this->deepObjectParser->parseByMediaType($rawQueryString, $mediaType, $parameter->name ?? 'unknown');
        }

        return null;
    }
}
