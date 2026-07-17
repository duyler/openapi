<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Contact;
use Duyler\OpenApi\Schema\Model\ExternalDocs;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\License;
use Duyler\OpenApi\Schema\Model\Tag;
use Duyler\OpenApi\Schema\Parser\InfoBuilder;
use Duyler\OpenApi\Schema\Parser\OpenApiBuildContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InfoBuilderTest extends TestCase
{
    private InfoBuilder $infoBuilder;

    protected function setUp(): void
    {
        $this->infoBuilder = (new OpenApiBuildContext())->infoBuilder;
    }

    #[Test]
    public function build_info_with_all_fields(): void
    {
        $info = $this->infoBuilder->buildInfo([
            'title' => 'Pet Store',
            'version' => '1.0.0',
            'description' => 'A sample API',
            'termsOfService' => 'https://example.com/tos',
            'contact' => ['name' => 'Support'],
            'license' => ['name' => 'MIT'],
        ]);

        self::assertInstanceOf(InfoObject::class, $info);
        self::assertSame('Pet Store', $info->title);
        self::assertSame('1.0.0', $info->version);
        self::assertSame('A sample API', $info->description);
        self::assertSame('https://example.com/tos', $info->termsOfService);
        self::assertInstanceOf(Contact::class, $info->contact);
        self::assertInstanceOf(License::class, $info->license);
    }

    #[Test]
    public function build_info_throws_when_title_missing(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Info object must have title and version');

        $this->infoBuilder->buildInfo(['title' => 'Missing version']);
    }

    #[Test]
    public function build_info_throws_when_version_missing(): void
    {
        $this->expectException(InvalidSchemaException::class);

        $this->infoBuilder->buildInfo(['version' => '1.0.0']);
    }

    #[Test]
    public function build_contact_with_all_fields(): void
    {
        $contact = $this->infoBuilder->buildContact([
            'name' => 'Team',
            'url' => 'https://team.example.com',
            'email' => 'team@example.com',
        ]);

        self::assertSame('Team', $contact->name);
        self::assertSame('https://team.example.com', $contact->url);
        self::assertSame('team@example.com', $contact->email);
    }

    #[Test]
    public function build_contact_with_empty_array(): void
    {
        $contact = $this->infoBuilder->buildContact([]);

        self::assertNull($contact->name);
        self::assertNull($contact->url);
        self::assertNull($contact->email);
    }

    #[Test]
    public function build_license_with_name_and_url(): void
    {
        $license = $this->infoBuilder->buildLicense([
            'name' => 'Apache 2.0',
            'url' => 'https://www.apache.org/licenses/LICENSE-2.0',
        ]);

        self::assertSame('Apache 2.0', $license->name);
        self::assertSame('https://www.apache.org/licenses/LICENSE-2.0', $license->url);
        self::assertNull($license->identifier);
    }

    #[Test]
    public function build_license_with_identifier(): void
    {
        $license = $this->infoBuilder->buildLicense([
            'name' => 'MIT',
            'identifier' => 'MIT',
        ]);

        self::assertSame('MIT', $license->name);
        self::assertSame('MIT', $license->identifier);
    }

    #[Test]
    public function build_external_docs_with_url(): void
    {
        $docs = $this->infoBuilder->buildExternalDocs([
            'url' => 'https://docs.example.com',
            'description' => 'More docs',
        ]);

        self::assertSame('https://docs.example.com', $docs->url);
        self::assertSame('More docs', $docs->description);
    }

    #[Test]
    public function build_external_docs_throws_when_url_missing(): void
    {
        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('External documentation must have url');

        $this->infoBuilder->buildExternalDocs(['description' => 'No url']);
    }

    #[Test]
    public function build_tag_with_external_docs(): void
    {
        $tag = $this->infoBuilder->buildTag([
            'name' => 'pet',
            'description' => 'Pet operations',
            'externalDocs' => ['url' => 'https://docs.example.com/pet'],
            'summary' => 'Pet tag',
            'parent' => 'animal',
            'kind' => 'group',
        ]);

        self::assertInstanceOf(Tag::class, $tag);
        self::assertSame('pet', $tag->name);
        self::assertSame('Pet operations', $tag->description);
        self::assertInstanceOf(ExternalDocs::class, $tag->externalDocs);
        self::assertSame('Pet tag', $tag->summary);
        self::assertSame('animal', $tag->parent);
        self::assertSame('group', $tag->kind);
    }

    #[Test]
    public function build_tags_returns_list(): void
    {
        $tags = $this->infoBuilder->buildTags([
            ['name' => 'pet'],
            ['name' => 'store'],
        ]);

        self::assertCount(2, $tags);
        self::assertSame('pet', $tags[0]->name);
        self::assertSame('store', $tags[1]->name);
    }

    #[Test]
    public function build_tags_with_empty_list_returns_empty_array(): void
    {
        self::assertSame([], $this->infoBuilder->buildTags([]));
    }
}
