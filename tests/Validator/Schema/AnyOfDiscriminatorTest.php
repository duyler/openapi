<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class AnyOfDiscriminatorTest extends TestCase
{
    private SchemaValidatorWithContext $validator;
    private RefResolverInterface $refResolver;
    private ValidatorPool $pool;

    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Resource API', '1.0.0'),
        );

        $this->validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);
    }

    #[Test]
    public function use_discriminator_for_anyof(): void
    {
        $adminSchema = new Schema(
            title: 'admin',
            type: 'object',
            properties: [
                'username' => new Schema(type: 'string'),
                'role' => new Schema(type: 'string'),
                'permissions' => new Schema(type: 'array'),
            ],
        );

        $userSchema = new Schema(
            title: 'user',
            type: 'object',
            properties: [
                'username' => new Schema(type: 'string'),
                'role' => new Schema(type: 'string'),
            ],
        );

        $accountSchema = new Schema(
            anyOf: [
                new Schema(ref: '#/components/schemas/Admin'),
                new Schema(ref: '#/components/schemas/User'),
            ],
            discriminator: new Discriminator(
                propertyName: 'role',
            ),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Account API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Account' => $accountSchema,
                    'Admin' => $adminSchema,
                    'User' => $userSchema,
                ],
            ),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $adminData = [
            'username' => 'admin',
            'role' => 'admin',
            'permissions' => ['read', 'write', 'delete'],
        ];

        $validator->validate($adminData, $accountSchema);

        $userData = [
            'username' => 'user',
            'role' => 'user',
        ];

        $validator->validate($userData, $accountSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function fallback_to_anyof_when_no_discriminator(): void
    {
        $stringSchema = new Schema(type: 'string');
        $numberSchema = new Schema(type: 'number');

        $valueSchema = new Schema(
            anyOf: [
                $stringSchema,
                $numberSchema,
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Value API', '1.0.0'),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $document);

        $validator->validate('test', $valueSchema);
        $validator->validate(42, $valueSchema);

        $this->assertTrue(true);
    }
}
