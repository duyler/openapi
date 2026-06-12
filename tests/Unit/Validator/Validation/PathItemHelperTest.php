<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Validation;

use Duyler\OpenApi\Schema\Model\Operation as OperationModel;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Validator\Validation\PathItemHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PathItemHelper::class)]
final class PathItemHelperTest extends TestCase
{
    #[Test]
    public function returns_standard_get_operation(): void
    {
        $operation = new OperationModel(summary: 'Get users');
        $pathItem = new PathItem(get: $operation);

        $result = PathItemHelper::getOperation($pathItem, 'get');

        $this->assertSame($operation, $result);
    }

    #[Test]
    public function returns_standard_post_operation(): void
    {
        $operation = new OperationModel(summary: 'Create user');
        $pathItem = new PathItem(post: $operation);

        $result = PathItemHelper::getOperation($pathItem, 'post');

        $this->assertSame($operation, $result);
    }

    #[Test]
    public function returns_standard_put_operation(): void
    {
        $operation = new OperationModel(summary: 'Update user');
        $pathItem = new PathItem(put: $operation);

        $result = PathItemHelper::getOperation($pathItem, 'put');

        $this->assertSame($operation, $result);
    }

    #[Test]
    public function returns_standard_delete_operation(): void
    {
        $operation = new OperationModel(summary: 'Delete user');
        $pathItem = new PathItem(delete: $operation);

        $result = PathItemHelper::getOperation($pathItem, 'delete');

        $this->assertSame($operation, $result);
    }

    #[Test]
    public function returns_standard_patch_operation(): void
    {
        $operation = new OperationModel(summary: 'Patch user');
        $pathItem = new PathItem(patch: $operation);

        $result = PathItemHelper::getOperation($pathItem, 'patch');

        $this->assertSame($operation, $result);
    }

    #[Test]
    public function returns_operation_from_additional_operations(): void
    {
        $additionalOp = new OperationModel(summary: 'Custom method');
        $pathItem = new PathItem(additionalOperations: ['custom' => $additionalOp]);

        $result = PathItemHelper::getOperation($pathItem, 'custom');

        $this->assertSame($additionalOp, $result);
    }

    #[Test]
    public function returns_additional_operation_when_standard_not_found(): void
    {
        $additionalOp = new OperationModel(summary: 'Custom method');
        $pathItem = new PathItem(
            get: new OperationModel(summary: 'Get users'),
            additionalOperations: ['custom' => $additionalOp],
        );

        $result = PathItemHelper::getOperation($pathItem, 'custom');

        $this->assertSame($additionalOp, $result);
    }

    #[Test]
    public function returns_null_when_no_matching_operation(): void
    {
        $pathItem = new PathItem(
            get: new OperationModel(summary: 'Get users'),
        );

        $result = PathItemHelper::getOperation($pathItem, 'delete');

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_path_item_has_no_operations(): void
    {
        $pathItem = new PathItem();

        $result = PathItemHelper::getOperation($pathItem, 'get');

        $this->assertNull($result);
    }

    #[Test]
    public function returns_null_when_additional_operations_is_empty_array(): void
    {
        $pathItem = new PathItem(additionalOperations: []);

        $result = PathItemHelper::getOperation($pathItem, 'custom');

        $this->assertNull($result);
    }

    #[Test]
    public function matches_method_case_insensitively_for_standard_operation(): void
    {
        $operation = new OperationModel(summary: 'Get users');
        $pathItem = new PathItem(get: $operation);

        $this->assertSame($operation, PathItemHelper::getOperation($pathItem, 'GET'));
        $this->assertSame($operation, PathItemHelper::getOperation($pathItem, 'Get'));
        $this->assertSame($operation, PathItemHelper::getOperation($pathItem, 'get'));
    }

    #[Test]
    public function matches_method_case_insensitively_for_additional_operation(): void
    {
        $additionalOp = new OperationModel(summary: 'Custom method');
        $pathItem = new PathItem(additionalOperations: ['CUSTOM' => $additionalOp]);

        $this->assertSame($additionalOp, PathItemHelper::getOperation($pathItem, 'custom'));
        $this->assertSame($additionalOp, PathItemHelper::getOperation($pathItem, 'Custom'));
        $this->assertSame($additionalOp, PathItemHelper::getOperation($pathItem, 'CUSTOM'));
    }

    #[Test]
    public function prefers_standard_operation_over_additional(): void
    {
        $standardOp = new OperationModel(summary: 'Standard GET');
        $additionalOp = new OperationModel(summary: 'Additional GET');
        $pathItem = new PathItem(
            get: $standardOp,
            additionalOperations: ['get' => $additionalOp],
        );

        $result = PathItemHelper::getOperation($pathItem, 'get');

        $this->assertSame($standardOp, $result);
    }
}
