<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator;

use Duyler\OpenApi\Validator\LibxmlSecuredContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function file_put_contents;
use function json_encode;
use function sprintf;
use function sys_get_temp_dir;
use function tempnam;
use function uniqid;
use function unlink;

use const LIBXML_NOERROR;
use const LIBXML_NONET;
use const LIBXML_NOWARNING;

#[CoversClass(LibxmlSecuredContext::class)]
final class LibxmlSecuredContextTest extends TestCase
{
    #[Test]
    public function run_returns_work_result(): void
    {
        $result = LibxmlSecuredContext::run(static fn(): int => 42);

        self::assertSame(42, $result);
    }

    #[Test]
    public function run_restores_libxml_internal_errors_after_work(): void
    {
        libxml_use_internal_errors(false);

        LibxmlSecuredContext::run(static fn(): null => null);

        self::assertFalse(libxml_use_internal_errors());
    }

    #[Test]
    public function run_restores_libxml_internal_errors_when_work_throws(): void
    {
        libxml_use_internal_errors(false);

        try {
            LibxmlSecuredContext::run(static function (): never {
                throw new RuntimeException('boom');
            });
            self::fail('Expected exception was not thrown');
        } catch (RuntimeException $e) {
            self::assertSame('boom', $e->getMessage());
        }

        self::assertFalse(libxml_use_internal_errors());
    }

    /**
     * Integration: LibxmlSecuredContext + simplexml_load_string must block
     * XXE file:// resolution. The deny-all loader installed by the helper
     * is the invariant guard against external entity substitution.
     */
    #[Test]
    public function run_blocks_xxe_file_resolution(): void
    {
        $secretContent = 'XXE_SECRET_' . uniqid('', true);
        $tempFile = tempnam(sys_get_temp_dir(), 'libxml_ctx_xxe_');

        self::assertNotFalse($tempFile);
        file_put_contents($tempFile, $secretContent);

        try {
            $xxePayload = sprintf(
                '<!DOCTYPE foo [ <!ENTITY xxe SYSTEM "file://%s"> ]><root><name>&xxe;</name></root>',
                $tempFile,
            );

            $result = LibxmlSecuredContext::run(
                static function () use ($xxePayload): string {
                    $xml = simplexml_load_string(
                        $xxePayload,
                        options: LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
                    );

                    return false === $xml ? '' : (string) json_encode($xml);
                },
            );

            self::assertStringNotContainsString(
                $secretContent,
                $result,
                'LibxmlSecuredContext must prevent file:// entity resolution',
            );
        } finally {
            unlink($tempFile);
        }
    }

    #[Test]
    public function restores_external_entity_loader_after_run(): void
    {
        $myLoader = static fn(?string $publicId, ?string $systemId): ?string => '/resolved/' . $systemId;
        $previousLoader = libxml_get_external_entity_loader();

        libxml_set_external_entity_loader($myLoader);

        try {
            LibxmlSecuredContext::run(static fn(): ?object => simplexml_load_string('<a/>'));

            self::assertSame(
                $myLoader,
                libxml_get_external_entity_loader(),
                'LibxmlSecuredContext::run() must restore the external entity loader that was active before it was called',
            );
        } finally {
            libxml_set_external_entity_loader($previousLoader);
            libxml_clear_errors();
        }
    }

    #[Test]
    public function clears_internal_errors_but_not_external(): void
    {
        $myLoader = static fn(?string $publicId, ?string $systemId): ?string => '/resolved/' . $systemId;
        $previousLoader = libxml_get_external_entity_loader();
        $previousInternalErrors = libxml_use_internal_errors(true);

        libxml_set_external_entity_loader($myLoader);

        try {
            LibxmlSecuredContext::run(static function (): void {
                simplexml_load_string('<invalid');
            });

            self::assertSame(
                [],
                libxml_get_errors(),
                'Errors generated inside run() must be cleared by the finally block',
            );
            self::assertSame(
                $myLoader,
                libxml_get_external_entity_loader(),
                'External entity loader is external process state and must survive run()',
            );
        } finally {
            libxml_set_external_entity_loader($previousLoader);
            libxml_clear_errors();
            libxml_use_internal_errors($previousInternalErrors);
        }
    }
}
