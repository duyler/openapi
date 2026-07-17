<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\OpenApiValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_use_internal_errors;
use function simplexml_load_string;

/**
 * Verifies P-064 contract: OpenApiValidator::reset() must not mutate PHP-global
 * libxml state. Consumer code that has accumulated libxml errors before calling
 * reset() must still be able to read them afterwards.
 *
 * @internal
 */
final class LibxmlStateLeakageTest extends TestCase
{
    #[Test]
    public function reset_does_not_clear_libxml_errors_outside_validator(): void
    {
        $previousInternalErrors = libxml_use_internal_errors(true);
        $previousLoader = libxml_get_external_entity_loader();
        libxml_clear_errors();

        try {
            simplexml_load_string('<invalid');

            self::assertNotSame([], libxml_get_errors());

            $validator = $this->buildValidator();
            $validator->reset();

            self::assertNotSame(
                [],
                libxml_get_errors(),
                'reset() must not clear libxml errors owned by surrounding application code',
            );
        } finally {
            libxml_clear_errors();
            libxml_set_external_entity_loader($previousLoader);
            libxml_use_internal_errors($previousInternalErrors);
        }
    }

    private function buildValidator(): OpenApiValidator
    {
        $yaml = <<<YAML
openapi: 3.2.0
info:
  title: Libxml State Leakage API
  version: 1.0.0
paths:
  /ping:
    get:
      responses:
        '200':
          description: OK
YAML;

        $built = OpenApiValidatorBuilder::create()
            ->fromYamlString($yaml)
            ->build();

        self::assertInstanceOf(OpenApiValidator::class, $built);

        return $built;
    }
}
