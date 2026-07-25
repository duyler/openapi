<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\Internal\ArraySchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\CompositionSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\ObjectSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\ObjectTypeHelper;
use Duyler\OpenApi\Schema\Parser\Internal\ScalarSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\XmlSchemaKeywordParser;

use Closure;

use function is_array;
use function is_bool;

final readonly class SchemaFromArrayConverter
{
    private ScalarSchemaKeywordParser $scalarParser;
    private XmlSchemaKeywordParser $xmlParser;

    public function __construct(
        private string $documentVersion = '',
        private DeprecationLogger $deprecationLogger = new DeprecationLogger(),
        private ArraySchemaKeywordParser $arrayParser = new ArraySchemaKeywordParser(),
        private ObjectSchemaKeywordParser $objectParser = new ObjectSchemaKeywordParser(),
        private CompositionSchemaKeywordParser $compositionParser = new CompositionSchemaKeywordParser(),
    ) {
        $this->scalarParser = new ScalarSchemaKeywordParser($documentVersion, $deprecationLogger);
        $this->xmlParser = new XmlSchemaKeywordParser($documentVersion, $deprecationLogger);
    }

    public function fromArray(bool|array $data): Schema
    {
        if (is_bool($data)) {
            return $data ? new Schema() : new Schema(not: new Schema());
        }

        $recurse = $this->recurseClosure();
        $xml = isset($data['xml']) && is_array($data['xml'])
            ? $this->xmlParser->build(ObjectTypeHelper::asStringMixedMapOrNull($data['xml']) ?? [])
            : null;

        return new Schema(
            ...$this->scalarParser->build($data),
            ...$this->arrayParser->build($data, $recurse),
            ...$this->objectParser->build($data, $recurse),
            ...$this->compositionParser->build($data, $recurse),
            xml: $xml,
        );
    }

    /** @return Closure(bool|array): Schema */
    private function recurseClosure(): Closure
    {
        $converter = $this;

        return static fn(bool|array $data): Schema => $converter->fromArray($data);
    }
}
