<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Functional\Schema;

use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Schema\OneOfValidatorWithContext;

use function array_map;
use function array_unique;
use function count;

final class CompositionBranchErrorsTest extends TestCase
{
    private const string SPEC = <<<'YAML'
        openapi: 3.0.3
        info:
          title: composition error reporting
          version: 1.0.0
        components:
          schemas:
            FormIdentifier:
              type: object
              required: [type, id]
              properties:
                type:
                  type: string
                  enum: [forms]
                id:
                  type: string
            WidgetIdentifier:
              type: object
              required: [widget]
              properties:
                widget:
                  type: string
            AllOfWrapper:
              allOf:
                - $ref: '#/components/schemas/FormIdentifier'
            AnyOfWrapper:
              anyOf:
                - $ref: '#/components/schemas/FormIdentifier'
            OneOfWrapper:
              oneOf:
                - $ref: '#/components/schemas/FormIdentifier'
            AllOfTwoBranches:
              allOf:
                - $ref: '#/components/schemas/FormIdentifier'
                - $ref: '#/components/schemas/WidgetIdentifier'
        YAML;

    private OpenApiValidatorInterface $validator;

    protected function setUp(): void
    {
        $this->validator = OpenApiValidatorBuilder::create()
            ->fromYamlString(self::SPEC)
            ->build();
    }

    #[Test]
    public function all_of_reports_a_structured_error_when_null_is_rejected_by_every_branch(): void
    {
        $exception = $this->assertRejects('AllOfWrapper', null);

        $this->assertNotEmpty($exception->getErrors());
        $this->assertHasDataPath($exception);
        $this->assertSame('All of the schemas must match, but 1 failed', $exception->getMessage());
    }

    #[Test]
    public function any_of_reports_a_structured_error_when_null_is_rejected_by_every_branch(): void
    {
        $exception = $this->assertRejects('AnyOfWrapper', null);

        $this->assertNotEmpty($exception->getErrors());
        $this->assertHasDataPath($exception);
        $this->assertSame('At least one of the schemas must match, but none did', $exception->getMessage());
    }

    #[Test]
    public function one_of_reports_a_structured_error_when_null_is_rejected_by_every_branch(): void
    {
        $exception = $this->assertRejects('OneOfWrapper', null);

        $this->assertNotEmpty($exception->getErrors());
        $this->assertHasDataPath($exception);
        $this->assertSame('Exactly one of schemas must match, but none did', $exception->getMessage());
    }

    #[Test]
    public function all_of_counts_the_failing_branch_when_the_value_is_not_an_object(): void
    {
        $exception = $this->assertRejects('AllOfWrapper', 'not-an-object');

        $this->assertCount(1, $exception->getErrors());
        $this->assertSame('All of the schemas must match, but 1 failed', $exception->getMessage());
    }

    #[Test]
    public function all_of_reports_a_failing_branch_error_exactly_once(): void
    {
        $exception = $this->assertRejects('AllOfWrapper', ['type' => 'widgets']);

        $this->assertCount(1, $exception->getErrors());
        $this->assertSame('All of the schemas must match, but 1 failed', $exception->getMessage());
    }

    #[Test]
    public function all_of_counts_every_failing_branch(): void
    {
        $exception = $this->assertRejects('AllOfTwoBranches', ['type' => 'widgets']);

        $this->assertSame('All of the schemas must match, but 2 failed', $exception->getMessage());
        $this->assertCount(2, $exception->getErrors());
        $this->assertNoDuplicates($exception);
    }

    /**
     * `oneOf` is deliberately excluded: it is dispatched to
     * {@see OneOfValidatorWithContext}, which evaluates
     * keyword groups in a different order, so it surfaces a different — but equally
     * valid — violation of the same branch. See the "Related observation" in #52.
     */
    #[Test]
    public function all_of_and_any_of_report_the_same_errors_for_the_same_failing_branch(): void
    {
        $allOf = $this->identities($this->assertRejects('AllOfWrapper', ['type' => 'widgets']));
        $anyOf = $this->identities($this->assertRejects('AnyOfWrapper', ['type' => 'widgets']));

        $this->assertSame($allOf, $anyOf);
        $this->assertCount(1, $this->identities($this->assertRejects('OneOfWrapper', ['type' => 'widgets'])));
    }

    #[Test]
    public function formatted_errors_are_never_empty_and_never_repeat(): void
    {
        $cases = [
            ['AllOfWrapper', null],
            ['AnyOfWrapper', null],
            ['OneOfWrapper', null],
            ['AllOfWrapper', 'not-an-object'],
            ['AllOfWrapper', ['type' => 'widgets']],
            ['AnyOfWrapper', ['type' => 'widgets']],
            ['OneOfWrapper', ['type' => 'widgets']],
            ['AllOfTwoBranches', ['type' => 'widgets']],
        ];

        foreach ($cases as [$schema, $data]) {
            $exception = $this->assertRejects($schema, $data);

            $this->assertNotSame('', $this->validator->getFormattedErrors($exception), $schema);
            $this->assertNoDuplicates($exception, $schema);
        }
    }

    private function assertRejects(string $schema, mixed $data): ValidationException
    {
        try {
            $this->validator->validateSchema($data, '#/components/schemas/' . $schema);
        } catch (ValidationException $e) {
            return $e;
        }

        $this->fail('Expected ' . $schema . ' to reject the value');
    }

    private function assertHasDataPath(ValidationException $exception): void
    {
        foreach ($exception->getErrors() as $error) {
            if ('' !== $error->dataPath()) {
                $this->addToAssertionCount(1);
                return;
            }
        }

        $this->fail('Expected at least one error carrying a dataPath');
    }

    private function assertNoDuplicates(ValidationException $exception, string $message = ''): void
    {
        $identities = $this->identities($exception);

        $this->assertCount(count(array_unique($identities)), $identities, $message);
    }

    /**
     * @return list<string>
     */
    private function identities(ValidationException $exception): array
    {
        return array_map(
            static fn($error): string => $error->keyword() . '@' . $error->dataPath() . '@' . $error->schemaPath(),
            $exception->getErrors(),
        );
    }
}
