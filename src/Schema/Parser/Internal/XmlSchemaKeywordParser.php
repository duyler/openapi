<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Schema\Parser\DeprecationLogger;
use Duyler\OpenApi\Schema\Parser\TypeHelper;

use function implode;
use function sprintf;
use function version_compare;

/** @internal */
final readonly class XmlSchemaKeywordParser
{
    private const string DEPRECATION_VERSION = '3.2.0';

    public function __construct(
        private string $documentVersion = '',
        private DeprecationLogger $deprecationLogger = new DeprecationLogger(),
    ) {}

    /** @param array<string, mixed> $data */
    public function build(array $data): ?Xml
    {
        $this->warnDeprecations($data);

        $xml = new Xml(
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
            namespace: TypeHelper::asStringOrNull($data['namespace'] ?? null),
            prefix: TypeHelper::asStringOrNull($data['prefix'] ?? null),
            attribute: TypeHelper::asBoolOrNull($data['attribute'] ?? null),
            wrapped: TypeHelper::asBoolOrNull($data['wrapped'] ?? null),
            nodeType: TypeHelper::asStringOrNull($data['nodeType'] ?? null),
        );

        if (null !== $xml->nodeType && !Xml::isValidNodeType($xml->nodeType)) {
            throw new InvalidSchemaException(
                sprintf(
                    'Invalid XML nodeType "%s". Must be one of: %s',
                    $xml->nodeType,
                    implode(', ', Xml::VALID_NODE_TYPES),
                ),
            );
        }

        return $xml;
    }

    /** @param array<string, mixed> $data */
    private function warnDeprecations(array $data): void
    {
        if (false === $this->shouldWarnDeprecation()) {
            return;
        }

        if (isset($data['attribute'])) {
            $this->deprecationLogger->warn(
                'attribute',
                'XML Object',
                self::DEPRECATION_VERSION,
                'nodeType: "attribute"',
            );
        }

        if (isset($data['wrapped'])) {
            $this->deprecationLogger->warn(
                'wrapped',
                'XML Object',
                self::DEPRECATION_VERSION,
            );
        }
    }

    private function shouldWarnDeprecation(): bool
    {
        return version_compare($this->documentVersion, self::DEPRECATION_VERSION, '>=');
    }
}
