<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Validator\Request\MediaTypeMatcher;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MediaTypeMatcherTest extends TestCase
{
    #[Override]
    protected function setUp(): void {}

    #[Test]
    public function exact_match_returns_spec_type(): void
    {
        $matched = MediaTypeMatcher::findMatch('application/json', ['application/json']);

        $this->assertSame('application/json', $matched);
    }

    #[Test]
    public function exact_match_wins_over_subtype_wildcard(): void
    {
        $matched = MediaTypeMatcher::findMatch(
            'application/json',
            ['application/*', 'application/json'],
        );

        $this->assertSame('application/json', $matched);
    }

    #[Test]
    public function exact_match_wins_over_universal_wildcard(): void
    {
        $matched = MediaTypeMatcher::findMatch(
            'application/json',
            ['*/*', 'application/json'],
        );

        $this->assertSame('application/json', $matched);
    }

    #[Test]
    public function subtype_wildcard_matches_concrete_subtype(): void
    {
        $matched = MediaTypeMatcher::findMatch('application/json', ['application/*']);

        $this->assertSame('application/*', $matched);
    }

    #[Test]
    public function subtype_wildcard_matches_xml_subtype(): void
    {
        $matched = MediaTypeMatcher::findMatch('application/xml', ['application/*']);

        $this->assertSame('application/*', $matched);
    }

    #[Test]
    public function subtype_wildcard_does_not_match_other_type(): void
    {
        $matched = MediaTypeMatcher::findMatch('text/plain', ['application/*']);

        $this->assertNull($matched);
    }

    #[Test]
    public function subtype_wildcard_wins_over_universal_wildcard(): void
    {
        $matched = MediaTypeMatcher::findMatch(
            'application/json',
            ['*/*', 'application/*'],
        );

        $this->assertSame('application/*', $matched);
    }

    #[Test]
    public function universal_wildcard_matches_application_json(): void
    {
        $matched = MediaTypeMatcher::findMatch('application/json', ['*/*']);

        $this->assertSame('*/*', $matched);
    }

    #[Test]
    public function universal_wildcard_matches_text_plain(): void
    {
        $matched = MediaTypeMatcher::findMatch('text/plain', ['*/*']);

        $this->assertSame('*/*', $matched);
    }

    #[Test]
    public function no_match_returns_null_for_disjoint_type(): void
    {
        $matched = MediaTypeMatcher::findMatch('text/plain', ['application/json']);

        $this->assertNull($matched);
    }

    #[Test]
    public function no_match_returns_null_for_empty_spec(): void
    {
        $matched = MediaTypeMatcher::findMatch('application/json', []);

        $this->assertNull($matched);
    }

    #[Test]
    public function malformed_request_media_type_matches_universal_wildcard_only(): void
    {
        $matched = MediaTypeMatcher::findMatch('application', ['*/*']);

        $this->assertSame('*/*', $matched);
    }

    #[Test]
    public function malformed_request_media_type_does_not_match_subtype_wildcard(): void
    {
        $matched = MediaTypeMatcher::findMatch('application', ['application/*']);

        $this->assertNull($matched);
    }

    #[Test]
    public function text_subtype_wildcard_does_not_match_application_type(): void
    {
        $matched = MediaTypeMatcher::findMatch('application/json', ['text/*']);

        $this->assertNull($matched);
    }
}
