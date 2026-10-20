<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Compiler\Exception\UnsupportedKeywordException;
use Duyler\OpenApi\Compiler\Internal\ArrayConstraints;
use Duyler\OpenApi\Compiler\Internal\ComponentRefResolver;
use Duyler\OpenApi\Compiler\Internal\EqualityHelpers;
use Duyler\OpenApi\Compiler\Internal\GenerationContext;
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

/**
 * @experimental
 */
final readonly class ValidatorCompiler
{
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
        private ComponentRefResolver $componentRefResolver = new ComponentRefResolver(),
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

        $cached = $cache->get($schemaHash);

        if (null !== $cached) {
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
        $code .= $this->generateConstraintsForSchema($schema, new GenerationContext());
        $code .= "    }\n";

        if ($this->equalityHelpers->isRequiredFor($schema)) {
            $code .= $this->equalityHelpers->renderJsonEqualsInline();
            $code .= $this->equalityHelpers->renderCanonicalKeyInline();
        }

        $code .= "}\n";

        return $code;
    }

    private function generateConstraintsForSchema(Schema $schema, GenerationContext $context): string
    {
        $code = $this->scalarConstraints->generate($schema, $context->dataVar, $context->path);
        $code .= $this->arrayConstraints->generateLengthConstraints($schema, $context->dataVar, $context->path);
        $code .= $this->generateObjectConstraints($schema, $context);

        return $code;
    }

    private function generateObjectConstraints(Schema $schema, GenerationContext $context): string
    {
        $code = '';

        if (null !== $schema->required && [] !== $schema->required) {
            $code .= $this->objectConstraints->generateRequiredCheck($schema->required, $context->dataVar);
        }

        if (false === $schema->additionalProperties) {
            $code .= $this->objectConstraints->generateAdditionalPropertiesCheck($schema, $context->dataVar);
        }

        if (null !== $schema->properties) {
            $code .= $this->generatePropertiesConstraints($schema->properties, $context);
        }

        if ($schema->items instanceof Schema) {
            $code .= $this->generateItemsConstraints($schema->items, $context);
        }

        return $code;
    }

    /**
     * @param array<string, Schema> $properties
     */
    private function generatePropertiesConstraints(array $properties, GenerationContext $context): string
    {
        $code = '';

        foreach ($properties as $propertyName => $propertySchema) {
            $propertyContext = $context->forProperty($propertyName);
            $code .= sprintf("        if (isset(%s)) {\n", $propertyContext->dataVar);
            $code .= $this->generateConstraintsForSchema($propertySchema, $propertyContext);
            $code .= "        }\n\n";
        }

        return $code;
    }

    private function generateItemsConstraints(Schema $itemsSchema, GenerationContext $context): string
    {
        $itemVar = 0 === $context->itemDepth ? '$item' : '$__item_' . $context->itemDepth;
        $indexVar = 0 === $context->itemDepth ? '$index' : '$__index_' . $context->itemDepth;

        $code = sprintf("        foreach (%s as %s => %s) {\n", $context->dataVar, $indexVar, $itemVar);
        $code .= $this->generateConstraintsForSchema($itemsSchema, $context->forItems($itemVar));
        $code .= "        }\n\n";

        return $code;
    }

    /**
     * @param list<string> $resolved
     */
    private function resolveRefs(Schema $schema, OpenApiDocument $document, array $resolved = []): Schema
    {
        if (null !== $schema->ref) {
            if (in_array($schema->ref, $resolved, true)) {
                throw new RuntimeException(sprintf('Circular reference detected: %s', $schema->ref));
            }

            return $this->resolveRefs(
                $this->componentRefResolver->resolve($schema->ref, $document),
                $document,
                [...$resolved, $schema->ref],
            );
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
}
