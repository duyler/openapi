<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder\Internal;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Schema\Parser\DeprecationLogger;
use Duyler\OpenApi\Schema\Parser\JsonParser;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Duyler\OpenApi\Schema\SchemaParserInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function in_array;
use function parse_url;
use function pathinfo;
use function sprintf;
use function strtolower;

use const PATHINFO_EXTENSION;
use const PHP_URL_PATH;

final readonly class SpecParserFactory
{
    private const array YAML_EXTENSIONS = ['yaml', 'yml'];

    public function __construct(
        private ?LoggerInterface $logger = null,
        private ?bool $reportDeprecated = null,
        private ?int $maxSpecSizeBytes = null,
        private ?int $maxSpecDepth = null,
        private ?bool $legacyExplodeDefaults = null,
    ) {}

    public function forSpecType(?string $specType, ?int $maxSpecBytes = null): SchemaParserInterface
    {
        $deprecationLogger = $this->deprecationLogger();
        $maxSpecDepth = $this->maxSpecDepth ?? YamlParser::DEFAULT_MAX_SPEC_DEPTH;

        return match ($specType) {
            'yaml' => new YamlParser(
                deprecationLogger: $deprecationLogger,
                legacyExplodeDefaults: $this->legacyExplodeDefaults,
                maxSpecBytes: $maxSpecBytes ?? $this->maxSpecSizeBytes ?? YamlParser::DEFAULT_MAX_SPEC_BYTES,
                maxSpecDepth: $maxSpecDepth,
            ),
            'json' => new JsonParser(
                deprecationLogger: $deprecationLogger,
                legacyExplodeDefaults: $this->legacyExplodeDefaults,
                maxSpecDepth: $maxSpecDepth,
                maxSpecBytes: $maxSpecBytes ?? $this->maxSpecSizeBytes ?? JsonParser::DEFAULT_MAX_SPEC_BYTES,
            ),
            default => throw new BuilderException(
                sprintf('Unsupported spec type: %s', $specType ?? 'none'),
            ),
        };
    }

    public function forRegisteredDocument(string $retrievalUri, int $maxSpecBytes): SchemaParserInterface
    {
        return $this->forSpecType(self::documentSpecType($retrievalUri), $maxSpecBytes);
    }

    public static function documentSpecType(string $retrievalUri): string
    {
        $urlPath = parse_url($retrievalUri, PHP_URL_PATH);
        $path = (false === $urlPath || null === $urlPath || '' === $urlPath) ? $retrievalUri : $urlPath;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, self::YAML_EXTENSIONS, true) ? 'yaml' : 'json';
    }

    private function deprecationLogger(): DeprecationLogger
    {
        return new DeprecationLogger(
            $this->logger ?? new NullLogger(),
            $this->reportDeprecated ?? true,
        );
    }
}
