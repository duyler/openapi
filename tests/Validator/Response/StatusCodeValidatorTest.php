<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Response;

use Duyler\OpenApi\Schema\Model\Response;
use Duyler\OpenApi\Validator\Exception\UndefinedResponseException;
use Duyler\OpenApi\Validator\Response\StatusCodeValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class StatusCodeValidatorTest extends TestCase
{
    private StatusCodeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new StatusCodeValidator();
    }

    #[Test]
    public function validate_exact_code(): void
    {
        $responses = [
            '200' => new Response(),
        ];

        $this->validator->validate(200, $responses);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_range_code(): void
    {
        $responses = [
            '2XX' => new Response(),
        ];

        $this->validator->validate(201, $responses);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function use_default_response(): void
    {
        $responses = [
            'default' => new Response(),
        ];

        $this->validator->validate(500, $responses);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_undefined_code(): void
    {
        $responses = [
            '200' => new Response(),
            '404' => new Response(),
        ];

        $this->expectException(UndefinedResponseException::class);

        $this->validator->validate(500, $responses);
    }
}
