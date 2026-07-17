<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Packaging;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use const JSON_THROW_ON_ERROR;

final class ComposerJsonTest extends TestCase
{
    #[Test]
    public function composer_json_has_no_nyholm_psr7_in_require(): void
    {
        $composer = $this->loadComposerJson();

        /** @var array<string, string> $require */
        $require = $composer['require'] ?? [];

        $this->assertArrayNotHasKey(
            'nyholm/psr7',
            $require,
            'nyholm/psr7 must not be a hard dependency; it should live in require-dev.',
        );
    }

    #[Test]
    public function composer_json_has_nyholm_psr7_in_require_dev(): void
    {
        $composer = $this->loadComposerJson();

        /** @var array<string, string> $requireDev */
        $requireDev = $composer['require-dev'] ?? [];

        $this->assertArrayHasKey(
            'nyholm/psr7',
            $requireDev,
            'nyholm/psr7 should be available for tests via require-dev.',
        );
    }

    #[Test]
    public function minimum_stability_is_stable(): void
    {
        $composer = $this->loadComposerJson();

        $this->assertSame(
            'stable',
            $composer['minimum-stability'] ?? null,
            'minimum-stability must be "stable" for a 1.0 release.',
        );
    }

    #[Test]
    public function suggest_section_documents_psr15_middleware(): void
    {
        $composer = $this->loadComposerJson();

        /** @var array<string, string> $suggest */
        $suggest = $composer['suggest'] ?? [];

        $this->assertArrayHasKey(
            'psr/http-server-middleware',
            $suggest,
            'psr/http-server-middleware should be suggested for the PSR-15 middleware example.',
        );
    }

    #[Test]
    public function psr_http_message_is_required(): void
    {
        $composer = $this->loadComposerJson();

        /** @var array<string, string> $require */
        $require = $composer['require'] ?? [];

        $this->assertArrayHasKey(
            'psr/http-message',
            $require,
            'psr/http-message must remain a hard dependency of the library.',
        );
    }

    /** @return array<string, mixed> */
    private function loadComposerJson(): array
    {
        $contents = file_get_contents(__DIR__ . '/../../../composer.json');
        if ($contents === false) {
            $this->fail('Unable to read composer.json');
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
