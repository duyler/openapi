<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration;

use Duyler\OpenApi\Validator\LibxmlSecuredContext;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function libxml_clear_errors;
use function libxml_get_errors;
use function libxml_get_external_entity_loader;
use function libxml_set_external_entity_loader;
use function libxml_use_internal_errors;
use function simplexml_load_string;

use const LIBXML_NONET;
use const LIBXML_NOERROR;
use const LIBXML_NOWARNING;

/**
 * @internal
 */
final class LibxmlEdgeCasesTest extends TestCase
{
    private bool $previousInternalErrors;
    /** @var callable(string|null): mixed|null */
    private $previousLoader;

    #[Override]
    protected function setUp(): void
    {
        $this->previousInternalErrors = libxml_use_internal_errors(true);
        $this->previousLoader = libxml_get_external_entity_loader();
        libxml_clear_errors();
    }

    #[Override]
    protected function tearDown(): void
    {
        libxml_clear_errors();
        libxml_set_external_entity_loader($this->previousLoader);
        libxml_use_internal_errors($this->previousInternalErrors);
    }

    #[Test]
    public function entity_loader_restored_after_xml_parse_error(): void
    {
        $capturedLoader = null;

        try {
            LibxmlSecuredContext::run(static function (): void {
                simplexml_load_string('<not-closed', options: LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
                throw new RuntimeException('forced failure inside secured context');
            });
        } catch (RuntimeException) {
            $capturedLoader = libxml_get_external_entity_loader();
        }

        self::assertSame(
            $this->previousLoader,
            $capturedLoader,
            'External entity loader must be restored even when work throws after a parse failure.',
        );
    }

    #[Test]
    public function no_libxml_state_leak_after_repeated_validations(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root><name> repeated </name></root>
XML;

        for ($i = 0; $i < 5; ++$i) {
            LibxmlSecuredContext::run(static function () use ($xml): void {
                simplexml_load_string($xml, options: LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            });

            self::assertSame(
                [],
                libxml_get_errors(),
                'LibxmlSecuredContext must not leave libxml errors behind after each iteration.',
            );
            self::assertSame(
                $this->previousLoader,
                libxml_get_external_entity_loader(),
                'External entity loader must be restored to the caller-visible value after each iteration.',
            );
        }
    }

    #[Test]
    public function internal_errors_flag_restored_when_work_clears_it(): void
    {
        libxml_use_internal_errors(true);

        LibxmlSecuredContext::run(static function (): void {
            simplexml_load_string('<ok/>', options: LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        });

        self::assertTrue(
            libxml_use_internal_errors(null),
            'libxml_use_internal_errors flag must be restored to its caller-visible value after run().',
        );
    }

    #[Test]
    public function libxml_errors_buffered_inside_run_does_not_leak_to_caller(): void
    {
        libxml_clear_errors();
        self::assertSame([], libxml_get_errors());

        LibxmlSecuredContext::run(static function (): void {
            simplexml_load_string('<broken', options: LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        });

        self::assertSame(
            [],
            libxml_get_errors(),
            'Libxml errors raised inside the secured context must be cleared before returning to the caller.',
        );
    }
}
