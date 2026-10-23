<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidContentEncodingException;
use Duyler\OpenApi\Validator\Util\Encoding\Base16SyntaxCheck;
use Duyler\OpenApi\Validator\Util\Encoding\Base32HexSyntaxCheck;
use Duyler\OpenApi\Validator\Util\Encoding\Base32SyntaxCheck;
use Duyler\OpenApi\Validator\Util\Encoding\Base64SyntaxCheck;
use Duyler\OpenApi\Validator\Util\Encoding\Base64UrlSyntaxCheck;
use Duyler\OpenApi\Validator\Util\Encoding\EncodingSyntaxCheck;
use Duyler\OpenApi\Validator\Util\Encoding\QuotedPrintableSyntaxCheck;
use Override;

use function array_key_exists;
use function is_string;
use function sprintf;

final readonly class ContentEncodingValidator implements KeywordApplicable
{
    private readonly ContentAssertionPipeline $pipeline;

    /** @var array<string, EncodingSyntaxCheck|null> */
    private readonly array $encodingChecks;

    public function __construct(
        private readonly SchemaValidatorDependencies $dependencies,
    ) {
        $this->pipeline = new ContentAssertionPipeline($dependencies);
        $this->encodingChecks = [
            'base16' => new Base16SyntaxCheck(),
            'base32' => new Base32SyntaxCheck(),
            'base32hex' => new Base32HexSyntaxCheck(),
            'base64' => new Base64SyntaxCheck($this->dependencies->contentDecoder),
            'base64url' => new Base64UrlSyntaxCheck(),
            'quoted-printable' => new QuotedPrintableSyntaxCheck(),
            '7bit' => null,
            '8bit' => null,
            'binary' => null,
        ];
    }

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->contentEncoding;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->contentEncoding || false === is_string($data)) {
            return;
        }

        if (null !== $context) {
            $context->annotate('contentEncoding', $schema->contentEncoding, $context->path());
        }

        if (false === array_key_exists($schema->contentEncoding, $this->encodingChecks)) {
            $this->warnUnknownEncoding($schema->contentEncoding, $schema, $context);
        }

        if (false === $this->dependencies->contentAssertionsEnabled) {
            if (null === $schema->contentMediaType && null !== $context) {
                $context->attachContentResult($this->pipeline->defer($data, $schema, $context));
            }

            return;
        }

        $check = $this->encodingChecks[$schema->contentEncoding] ?? null;

        if (null === $check) {
            return;
        }

        if (false === $check->isValid($data)) {
            throw new InvalidContentEncodingException($data, $schema->contentEncoding);
        }
    }

    private function warnUnknownEncoding(string $encoding, Schema $schema, ?ValidationContext $context): void
    {
        $propertyPath = null === $context ? '/' : $context->path();

        $this->dependencies->eventDispatcher?->dispatch(
            new ValidationWarningEvent(
                propertyPath: $propertyPath,
                propertyName: 'contentEncoding',
                message: sprintf('Unrecognized contentEncoding "%s" is collected as an annotation', $encoding),
                schemaRef: $schema->ref,
            ),
        );

        $this->dependencies->logger->warning(
            sprintf('Unrecognized contentEncoding "%s" at path "%s"', $encoding, $propertyPath),
        );
    }
}
