# Validation Guide

This guide explains how validation works in the Duyler OpenAPI Validator, including nullable support, validation contexts, and best practices.

## Nullable Validation

### Overview

In JSON Schema, the `nullable: true` keyword indicates that a property's value can be `null` in addition to the specified type. For example, a property defined as `{ type: 'string', nullable: true }` accepts both strings and `null` values.

### Behavior in This Library

By default, nullable validation is **enabled**. This means that when a schema has `nullable: true`, the validator allows `null` values.

You can control this behavior through two methods:

1. **Builder-level control** - Set the default behavior for all validations
2. **Context-level control** - Set the behavior for specific validations

### Builder Configuration

Control nullable behavior when building the validator:

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

// Nullable validation is enabled by default
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->enableNullableAsType()  // Optional: explicitly enable (default behavior)
    ->build();

// Disable nullable validation globally
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->disableNullableAsType()  // nullable: true will NOT allow null values
    ->build();
```

### Validation Context

When using schema validators directly, you can control nullable behavior through the `ValidationContext`:

```php
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Schema\Model\Schema;

$pool = new ValidatorPool();
$schema = new Schema(type: 'string', nullable: true);

// Create context with nullable support enabled (default)
$context = ValidationContext::create($pool, nullableAsType: true);

$validator = new SchemaValidator($pool);
$validator->validate(null, $schema, $context); // OK - null is allowed

// Create context with nullable support disabled
$context = ValidationContext::create($pool, nullableAsType: false);

$validator->validate(null, $schema, $context); // Error - null is not allowed
```

### Why Explicit Control?

This design provides explicit control over when `null` values are acceptable:

1. **Default strictness** - By enabling nullable by default, the library follows JSON Schema semantics
2. **Explicit disabling** - You can disable nullable support when you need stricter validation
3. **Contextual control** - Different validations can have different nullable behavior

### Best Practices

#### 1. Use Nullable for Optional Fields

Mark fields that can be `null` as nullable in your schema:

```yaml
components:
  schemas:
    User:
      type: object
      properties:
        name:
          type: string
        nickname:
          type: string
          nullable: true  # Can be null or string
      required:
        - name
        # nickname is optional and can be null
```

#### 2. Don't Confuse Nullable with Optional

These are different concepts:

- `nullable: true` - The value can be `null` when the property exists
- Absence from `required` - The property may be omitted entirely

```yaml
properties:
  # Property can be present with null value
  field1:
    type: string
    nullable: true
    required: true  # Property must exist, but can be null

  # Property can be omitted, but must be string if present
  field2:
    type: string
    required: false  # Property is optional

  # Property can be omitted OR present with null OR present with string
  field3:
    type: string
    nullable: true
    required: false  # Best of both worlds
```

#### 3. Control Nullable Behavior Globally

Set the nullable behavior once when building the validator:

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->enableNullableAsType()  // Set behavior globally
    ->build();
```

#### 4. Use Context for Specific Validations

When you need different behavior for specific validations:

```php
$defaultContext = ValidationContext::create($pool, nullableAsType: true);
$strictContext = ValidationContext::create($pool, nullableAsType: false);

// Default validation with nullable support
$validator->validate($data1, $schema1, $defaultContext);

// Strict validation without nullable support
$validator->validate($data2, $schema2, $strictContext);
```

### Common Pitfalls

#### 1. Disabling Nullable When Not Needed

```php
// BAD - Disabling nullable validation breaks JSON Schema semantics
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->disableNullableAsType()
    ->build();

// GOOD - Use default behavior (nullable enabled)
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();
```

#### 2. Confusing Nullable Types

```php
// BAD - Don't use nullable with type array
new Schema(type: ['string', 'null'], nullable: true)

// GOOD - Use nullable flag or type array, not both
new Schema(type: 'string', nullable: true)
// OR
new Schema(type: ['string', 'null'])
```

#### 3. Not Understanding Required vs Nullable

```yaml
# BAD - Makes field required but nullable
properties:
  field:
    type: string
    nullable: true
required:
  - field

# GOOD - Make field optional if it can be missing
properties:
  field:
    type: string
    nullable: true
# field not in required array
```

### Examples

#### Example 1: Simple Nullable Field

```php
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Schema\Model\Schema;

$schema = new Schema(
    type: 'object',
    properties: [
        'name' => new Schema(type: 'string'),
        'nickname' => new Schema(type: 'string', nullable: true),
    ],
    required: ['name'],
);

$pool = new ValidatorPool();
$context = ValidationContext::create($pool, nullableAsType: true);
$validator = new SchemaValidator($pool);

// Valid data
$validator->validate(['name' => 'John', 'nickname' => 'Johnny'], $schema, $context);
$validator->validate(['name' => 'John', 'nickname' => null], $schema, $context);
$validator->validate(['name' => 'John'], $schema, $context); // nickname omitted
```

#### Example 2: Nullable in Array Items

```php
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Schema\Model\Schema;

$schema = new Schema(
    type: 'array',
    items: new Schema(type: 'string', nullable: true),
);

$pool = new ValidatorPool();
$context = ValidationContext::create($pool, nullableAsType: true);
$validator = new SchemaValidator($pool);

$validator->validate(['a', null, 'b'], $schema, $context); // OK - null items allowed
```

#### Example 3: Nullable with Additional Constraints

```php
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Schema\Model\Schema;

$schema = new Schema(
    type: 'string',
    nullable: true,
    minLength: 5,
);

$pool = new ValidatorPool();
$context = ValidationContext::create($pool, nullableAsType: true);
$validator = new SchemaValidator($pool);

$validator->validate('Hello', $schema, $context);  // OK
$validator->validate(null, $schema, $context);     // OK - constraints don't apply to null
$validator->validate('Hi', $schema, $context);      // Error - minLength violation
```

#### Example 4: Strict Validation Mode

```php
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Schema\Model\Schema;

$pool = new ValidatorPool();
$schema = new Schema(type: 'string', nullable: true);

// Create context with nullable support disabled (strict mode)
$context = ValidationContext::create($pool, nullableAsType: false);
$validator = new SchemaValidator($pool);

$validator->validate('Hello', $schema, $context);  // OK
$validator->validate(null, $schema, $context);     // Error - null not allowed in strict mode
```

#### Example 5: Using Validation Context Directly

```php
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Schema\Model\Schema;

$pool = new ValidatorPool();
$schema = new Schema(type: 'string', nullable: true);

// Create contexts with different behavior
$permissiveContext = ValidationContext::create($pool, nullableAsType: true);
$strictContext = ValidationContext::create($pool, nullableAsType: false);

$validator = new SchemaValidator($pool);

$validator->validate('Hello', $schema, $permissiveContext);  // OK
$validator->validate(null, $schema, $permissiveContext);    // OK
$validator->validate('Hello', $schema, $strictContext);      // OK
$validator->validate(null, $schema, $strictContext);       // Error
```

## Error Messages

When nullable validation fails, you'll receive appropriate error messages:

```php
use Duyler\OpenApi\Validator\Exception\ValidationException;

try {
    $validator->validateSchema(null, $schema);
} catch (ValidationException $e) {
    $errors = $e->getErrors();
    foreach ($errors as $error) {
        echo sprintf("Path: %s\nMessage: %s\n", $error->dataPath(), $error->getMessage());
    }
}
```

## Advanced Topics

### Validation Context Navigation

The `ValidationContext` includes a breadcrumb manager that tracks the validation path:

```php
$context = ValidationContext::create($pool);

// Add breadcrumb for object property
$context = $context->withBreadcrumb('propertyName');

// Add breadcrumb for array index
$context = $context->withBreadcrumbIndex(0);

// Remove last breadcrumb
$context = $context->withoutBreadcrumb();
```

### Combining with Other Features

Nullable validation works seamlessly with other validation features:

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->enableCoercion()           // Auto-convert types
    ->enableNullableAsType()     // Allow null for nullable fields
    ->withCache($cache)          // Cache parsed specs
    ->withEventDispatcher($dispatcher)
    ->build();
```

## See Also

- [README.md](../README.md) - Main documentation
- [OpenAPI 3.1 Specification](https://spec.openapis.org/oas/v3.1.0) - Official spec
- [JSON Schema](https://json-schema.org/) - JSON Schema specification
