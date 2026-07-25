<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Compiler\Exception\UnsupportedKeywordException;
use Duyler\OpenApi\Compiler\Internal\ArrayConstraints;
use Duyler\OpenApi\Compiler\Internal\EqualityHelpers;
use Duyler\OpenApi\Compiler\Internal\ObjectConstraints;
use Duyler\OpenApi\Compiler\Internal\PatternCheck;
use Duyler\OpenApi\Compiler\Internal\ScalarConstraints;
use Duyler\OpenApi\Compiler\Internal\UnsupportedKeywordDetector;
use Duyler\OpenApi\Compiler\Internal\Utf16Length;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use InvalidArgumentException;
use RuntimeException;

use function array_pop;
use function explode;
use function implode;
use function in_array;
use function preg_match;
use function sprintf;
use function str_starts_with;
use function substr;
use function var_export;

/**
 * @experimental
 */
final readonly class ValidatorCompiler
{
    private const int REF_COMPONENTS_SCHEMAS_PREFIX_LENGTH = 21;

    private const string CLASS_NAME_PATTERN = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/';

    public function __construct(
        private ScalarConstraints $scalarConstraints = new ScalarConstraints(
            new Utf16Length(),
            new PatternCheck(),
        ),
        private ArrayConstraints $arrayConstraints = new ArrayConstraints(),
        private ObjectConstraints $objectConstraints = new ObjectConstraints(),
        private EqualityHelpers $equalityHelpers = new EqualityHelpers(),
        private UnsupportedKeywordDetector $keywordDetector = new UnsupportedKeywordDetector(),
    ) {}

    public function compile(Schema $schema, string $className): string
    {
        $this->validateClassName($className);

        $unsupported = $this->keywordDetector->detect($schema);

        if ([] !== $unsupported) {
            throw new UnsupportedKeywordException($unsupported);
        }

        return $this->generateCode($schema, $className);
    }

    public function compileWithRefResolution(
        Schema $schema,
        string $className,
        OpenApiDocument $document,
    ): string {
        return $this->compile($this->resolveRefs($schema, $document), $className);
    }

    public function compileWithCache(
        Schema $schema,
        string $className,
        ?CompilationCacheInterface $cache = null,
        ?OpenApiDocument $document = null,
    ): string {
        if (null === $cache) {
            return $this->compile($schema, $className);
        }

        $schemaHash = $cache->generateKey($schema, $className, $document);

        if (null !== ($cached = $cache->get($schemaHash))) {
            return $cached;
        }

        $code = $this->compile($schema, $className);
        $cache->set($schemaHash, $code);

        return $code;
    }

    private function validateClassName(string $className): void
    {
        foreach (explode('\\', $className) as $part) {
            if (1 !== preg_match(self::CLASS_NAME_PATTERN, $part)) {
                throw new InvalidArgumentException(sprintf('Invalid class name part: "%s"', $part));
            }
        }
    }

    private function generateCode(Schema $schema, string $className): string
    {
        $parts = explode('\\', $className);
        $shortName = array_pop($parts);
        $namespace = implode('\\', $parts);

        $code = "<?php\n\ndeclare(strict_types=1);\n\n";

        if ('' !== $namespace) {
            $code .= sprintf("namespace %s;\n\n", $namespace);
        }

        $code .= "use Duyler\\OpenApi\\Validator\\TypeFormatter;\n\n";
        $code .= sprintf("readonly class %s\n{\n", $shortName);
        $code .= '    public function validate(mixed $data): void' . "\n    {\n";
        $code .= $this->generateConstraintsForSchema($schema, '$data', 0);
        $code .= "    }\n";

        if ($this->equalityHelpers->isRequiredFor($schema)) {
            $code .= $this->equalityHelpers->renderJsonEqualsInline();
            $code .= $this->equalityHelpers->renderCanonicalKeyInline();
        }

        $code .= "}\n";

        return $code;
    }

    private function generateConstraintsForSchema(Schema $schema, string $dataVar, int $itemDepth): string
    {
        $code = $this->scalarConstraints->generate($schema, $dataVar);
        $code .= $this->arrayConstraints->generateLengthConstraints($schema, $dataVar);
        $code .= $this->generateObjectConstraints($schema, $dataVar, $itemDepth);

        return $code;
    }

    private function generateObjectConstraints(Schema $schema, string $dataVar, int $itemDepth): string
    {
        $code = '';

        if (null !== $schema->required && [] !== $schema->required) {
            $code .= $this->objectConstraints->generateRequiredCheck($schema->required, $dataVar);
        }

        if (false === $schema->additionalProperties) {
            $code .= $this->objectConstraints->generateAdditionalPropertiesCheck($schema, $dataVar);
        }

        if (null !== $schema->properties) {
            $code .= $this->generatePropertiesConstraints($schema->properties, $dataVar, $itemDepth);
        }

        if ($schema->items instanceof Schema) {
            $code .= $this->generateItemsConstraints($schema->items, $dataVar, $itemDepth);
        }

        return $code;
    }

    /**
     * @param array<string, Schema> $properties
     */
    private function generatePropertiesConstraints(array $properties, string $dataVar, int $itemDepth): string
    {
        $code = '';

        foreach ($properties as $propertyName => $propertySchema) {
            $safePropertyName = var_export((string) $propertyName, true);
            $propertyVar = $dataVar . '[' . $safePropertyName . ']';
            $code .= sprintf("        if (isset(%s)) {\n", $propertyVar);
            $code .= $this->generateConstraintsForSchema($propertySchema, $propertyVar, $itemDepth);
            $code .= "        }\n\n";
        }

        return $code;
    }

    private function generateItemsConstraints(Schema $itemsSchema, string $dataVar, int $itemDepth): string
    {
        $itemVar = 0 === $itemDepth ? '$item' : '$__item_' . $itemDepth;
        $indexVar = 0 === $itemDepth ? '$index' : '$__index_' . $itemDepth;

        $code = sprintf("        foreach (%s as %s => %s) {\n", $dataVar, $indexVar, $itemVar);
        $code .= $this->generateConstraintsForSchema($itemsSchema, $itemVar, $itemDepth + 1);
        $code .= "        }\n\n";

        return $code;
    }

    private function resolveRefs(Schema $schema, OpenApiDocument $document, array $resolved = []): Schema
    {
        if (null !== $schema->ref) {
            if (in_array($schema->ref, $resolved, true)) {
                throw new RuntimeException(sprintf('Circular reference detected: %s', $schema->ref));
            }

            return $this->resolveRef($schema->ref, $document, [...$resolved, $schema->ref]);
        }

        $resolvedProperties = null;

        if (null !== $schema->properties) {
            $resolvedProperties = [];

            foreach ($schema->properties as $name => $property) {
                $resolvedProperties[$name] = $this->resolveRefs($property, $document, $resolved);
            }
        }

        $resolvedItems = $schema->items instanceof Schema
            ? $this->resolveRefs($schema->items, $document, $resolved)
            : null;

        return $schema->withOverrides(
            properties: $resolvedProperties,
            items: $resolvedItems,
        );
    }

    private function resolveRef(string $ref, OpenApiDocument $document, array $resolved): Schema
    {
        if (false === str_starts_with($ref, '#/components/schemas/')) {
            throw new RuntimeException(sprintf('Unsupported $ref: %s', $ref));
        }

        $schemaName = substr($ref, self::REF_COMPONENTS_SCHEMAS_PREFIX_LENGTH);
        $schemas = $document->components?->schemas ?? [];

        if (false === isset($schemas[$schemaName])) {
            throw new RuntimeException(sprintf('Schema not found: %s', $schemaName));
        }

        return $this->resolveRefs($schemas[$schemaName], $document, $resolved);
    }
}
