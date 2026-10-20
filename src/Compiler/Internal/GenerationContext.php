<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use function sprintf;
use function var_export;

/** @internal */
final readonly class GenerationContext
{
    public const string ROOT_PATH = 'data';

    private const string ROOT_DATA_VAR = '$data';

    private const string ITEMS_PATH_SUFFIX = '[*]';

    public function __construct(
        public string $dataVar = self::ROOT_DATA_VAR,
        public string $path = self::ROOT_PATH,
        public int $itemDepth = 0,
    ) {}

    public function forProperty(string $propertyName): self
    {
        return new self(
            dataVar: sprintf('%s[%s]', $this->dataVar, var_export($propertyName, true)),
            path: sprintf('%s.%s', $this->path, $propertyName),
            itemDepth: $this->itemDepth,
        );
    }

    public function forItems(string $itemVar): self
    {
        return new self(
            dataVar: $itemVar,
            path: $this->path . self::ITEMS_PATH_SUFFIX,
            itemDepth: $this->itemDepth + 1,
        );
    }
}
