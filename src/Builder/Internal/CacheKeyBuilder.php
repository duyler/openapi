<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Builder\Internal;

use Duyler\OpenApi\Builder\Dto\BuilderConfig;
use Duyler\OpenApi\Schema\Parser\YamlParser;
use Duyler\OpenApi\Validator\Schema\FileExternalRefResolver;

use function hash;
use function realpath;
use function sprintf;

final readonly class CacheKeyBuilder
{
    public const string FILE_PREFIX = 'openapi_spec_file_';
    public const string CONTENT_PREFIX = 'openapi_spec_content_';

    public function __construct(
        private BuilderConfig $config,
    ) {}

    public function forFile(string $path, string $content): string
    {
        $realPath = realpath($path);
        $pathComponent = false === $realPath ? $path : $realPath;
        $contentHash = hash('sha256', $content);

        return self::FILE_PREFIX . hash('sha256', $pathComponent . '|' . $contentHash . '|' . $this->buildFingerprint());
    }

    public function forString(string $content): string
    {
        $contentHash = hash('sha256', $content);

        return self::CONTENT_PREFIX . hash('sha256', $contentHash . '|' . $this->buildFingerprint());
    }

    private function buildFingerprint(): string
    {
        return sprintf(
            'maxSpecDepth=%d|maxSpecSizeBytes=%d|externalRefAllowedRoot=%s|externalRefMaxBytes=%d',
            $this->config->maxSpecDepth ?? YamlParser::DEFAULT_MAX_SPEC_DEPTH,
            $this->config->maxSpecSizeBytes ?? YamlParser::DEFAULT_MAX_SPEC_BYTES,
            $this->config->externalRefAllowedRoot ?? '',
            $this->config->externalRefMaxBytes ?? FileExternalRefResolver::DEFAULT_MAX_REF_BYTES,
        );
    }
}
