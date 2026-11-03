<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\DialectProfile;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StatelessValidatorDispatcherDialectProfileTest extends TestCase
{
    #[Test]
    public function dispatcher_created_context_defaults_to_openapi_31_profile(): void
    {
        $spy = new ProfileCapturingSpyValidator();
        $dispatcher = new StatelessValidatorDispatcher(validators: [$spy], pool: new ValidatorPool());

        $dispatcher->validate('value', new Schema(type: 'string'));

        self::assertNotNull($spy->observedContext);
        self::assertEquals(
            DialectProfile::forOpenApi31(),
            $spy->observedContext->dialectProfile,
        );
    }

    #[Test]
    public function dispatcher_passes_existing_context_profile_through(): void
    {
        $spy = new ProfileCapturingSpyValidator();
        $dispatcher = new StatelessValidatorDispatcher(validators: [$spy], pool: new ValidatorPool());
        $profile = DialectProfile::forOpenApi30();
        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: new ValidatorPool(),
            dialectProfile: $profile,
        ));

        $dispatcher->validate('value', new Schema(type: 'string'), $context);

        self::assertNotNull($spy->observedContext);
        self::assertSame($profile, $spy->observedContext->dialectProfile);
    }
}

final class ProfileCapturingSpyValidator implements SchemaValidatorInterface
{
    public ?ValidationContext $observedContext = null;

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        $this->observedContext = $context;
    }
}
