# Duyler OpenAPI Validator

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=duyler_openapi&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=duyler_openapi)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=duyler_openapi&metric=coverage)](https://sonarcloud.io/summary/new_code?id=duyler_openapi)
[![type-coverage](https://shepherd.dev/github/duyler/openapi/coverage.svg)](https://shepherd.dev/github/duyler/openapi)
[![psalm-level](https://shepherd.dev/github/duyler/openapi/level.svg)](https://shepherd.dev/github/duyler/openapi)
![PHP Version](https://img.shields.io/packagist/dependency-v/duyler/openapi/php?version=dev-main)

OpenAPI 3.1 validator for PHP 8.4+

## Features

- **Full OpenAPI 3.1 Support** - Complete implementation of OpenAPI 3.1 specification
- **JSON Schema Validation** - Full JSON Schema draft 2020-12 validation with 25+ validators
- **PSR-7 Integration** - Works with any PSR-7 HTTP message implementation
- **Request Validation** - Validate path parameters, query parameters, headers, cookies, and request body
- **Response Validation** - Validate status codes, headers, and response bodies
- **Multiple Content Types** - Support for JSON, form-data, multipart, text, and XML
- **Built-in Format Validators** - 12+ built-in validators (email, UUID, date-time, URI, IPv4/IPv6, etc.)
- **Custom Format Validators** - Easily register custom format validators
- **Discriminator Support** - Full support for polymorphic schemas with discriminators
 - **Type Coercion** - Optional automatic type conversion
 - **PSR-6 Caching** - Cache parsed OpenAPI documents for better performance
 - **PSR-14 Events** - Subscribe to validation lifecycle events
 - **Error Formatting** - Multiple error formatters (simple, detailed, JSON)
- **Webhooks Support** - Validate incoming webhook requests
- **Schema Registry** - Manage multiple schema versions
- **Validator Compilation** - Generate optimized validator code

## Installation

```bash
composer require duyler/openapi
```

## Quick Start

### Basic Usage

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

// Validate request
$operation = $validator->validateRequest($request);

// Validate response
$validator->validateResponse($response, $operation);
```

## Usage

### Loading OpenAPI Specifications

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

// From YAML file
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

// From JSON file
$validator = OpenApiValidatorBuilder::create()
    ->fromJsonFile('openapi.json')
    ->build();

// From YAML string
$yaml = file_get_contents('openapi.yaml');
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlString($yaml)
    ->build();

// From JSON string
$json = file_get_contents('openapi.json');
$validator = OpenApiValidatorBuilder::create()
    ->fromJsonString($json)
    ->build();
```

### PSR-7 Integration

The validator works with any PSR-7 implementation:

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$factory = new Psr17Factory();
$request = $factory->createServerRequest('POST', '/users')
    ->withHeader('Content-Type', 'application/json')
    ->withBody($factory->createStream('{"name": "John"}'));

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

$operation = $validator->validateRequest($request);
// $operation contains the matched path and method
```

### Caching

Enable PSR-6 caching for improved performance:

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$cachePool = new FilesystemAdapter();
$schemaCache = new SchemaCache($cachePool, 3600);

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withCache($schemaCache)
    ->build();
```

### Events

Subscribe to validation events using PSR-14:

```php
use Duyler\OpenApi\Event\ArrayDispatcher;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$dispatcher = new ArrayDispatcher([
    ValidationStartedEvent::class => [
        function (ValidationStartedEvent $event) {
            printf("Validating: %s %s\n", $event->method, $event->path);
        },
    ],
]);

```

### Webhooks

Validate webhook requests:

```php
use Duyler\OpenApi\Validator\Webhook\WebhookValidator;
use Duyler\OpenApi\Validator\Request\RequestValidator;

$webhookValidator = new WebhookValidator($requestValidator);
$webhookValidator->validate($request, 'payment.webhook', $document);
```

## Advanced Usage

### Custom Format Validators

Register custom format validators for domain-specific validation:

```php
use Duyler\OpenApi\Validator\Format\FormatValidatorInterface;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;

// Create a custom validator
class PhoneNumberValidator implements FormatValidatorInterface
{
    public function validate(mixed $data): void
    {
        if (!is_string($data) || !preg_match('/^\+?[1-9]\d{1,14}$/', $data)) {
            throw new InvalidFormatException(
                'phone',
                $data,
                'Value must be a valid E.164 phone number'
            );
        }
    }
}

// Register with the builder
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withFormat('string', 'phone', new PhoneNumberValidator())
    ->build();
```

### Type Coercion

Enable automatic type conversion for query parameters and request body:

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->enableCoercion()  // Convert string "123" to integer 123
    ->build();
```

### Error Formatters

Choose from built-in error formatters or create your own:

```php
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;

// Detailed formatter with suggestions
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withErrorFormatter(new DetailedFormatter())
    ->build();

// JSON formatter for API responses
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withErrorFormatter(new JsonFormatter())
    ->build();

try {
    $operation = $validator->validateRequest($request);
} catch (ValidationException $e) {
    // Get formatted errors
    $formatted = $validator->getFormattedErrors($e);
    echo $formatted;
}
```

### Discriminator Validation

Validate polymorphic schemas with discriminators:

```php
$yaml = <<<YAML
openapi: 3.1.0
info:
  title: Pet Store API
  version: 1.0.0
components:
  schemas:
    Pet:
      type: object
      required:
        - petType
      discriminator:
        propertyName: petType
        mapping:
          cat: '#/components/schemas/Cat'
          dog: '#/components/schemas/Dog'
      oneOf:
        - $ref: '#/components/schemas/Cat'
        - $ref: '#/components/schemas/Dog'
    Cat:
      type: object
      required:
        - petType
        - name
      properties:
        petType:
          type: string
          enum: [cat]
        name:
          type: string
    Dog:
      type: object
      required:
        - petType
        - name
        - breed
      properties:
        petType:
          type: string
          enum: [dog]
        name:
          type: string
        breed:
          type: string
YAML;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlString($yaml)
    ->build();

// Validates against Cat schema
$data = ['petType' => 'cat', 'name' => 'Fluffy'];
$validator->validateSchema($data, '#/components/schemas/Pet');
```

### Event-Driven Validation

Subscribe to validation lifecycle events:

```php
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ArrayDispatcher;

$dispatcher = new ArrayDispatcher([
    ValidationStartedEvent::class => [
        function (ValidationStartedEvent $event) {
            error_log(sprintf(
                "Validation started: %s %s",
                $event->method,
                $event->path
            ));
        },
    ],
    ValidationFinishedEvent::class => [
        function (ValidationFinishedEvent $event) {
            if ($event->success) {
                error_log(sprintf(
                    "Validation completed in %.3f seconds",
                    $event->duration
                ));
            }
        },
    ],
    ValidationErrorEvent::class => [
        function (ValidationErrorEvent $event) {
            error_log(sprintf(
                "Validation failed for %s %s: %s",
                $event->method,
                $event->path,
                $event->exception->getMessage()
            ));
        },
    ],
]);

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withEventDispatcher($dispatcher)
    ->build();
```

### Schema Registry

Manage multiple API versions:

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Registry\SchemaRegistry;

// Load multiple versions
$documentV1 = OpenApiValidatorBuilder::create()
    ->fromYamlFile('api-v1.yaml')
    ->build()
    ->document;

$documentV2 = OpenApiValidatorBuilder::create()
    ->fromYamlFile('api-v2.yaml')
    ->build()
    ->document;

// Register schemas
$registry = new SchemaRegistry();
$registry = $registry
    ->register('api', '1.0.0', $documentV1)
    ->register('api', '2.0.0', $documentV2);

// Get specific version
$schema = $registry->get('api', '1.0.0');

// Get latest version
$schema = $registry->get('api');

// List all versions
$versions = $registry->getVersions('api');
// ['1.0.0', '2.0.0']
```

### Validator Pool

The validator pool uses WeakMap to reuse validator instances:

```php
use Duyler\OpenApi\Validator\ValidatorPool;

$pool = new ValidatorPool();

// Validators are automatically reused
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withValidatorPool($pool)
    ->build();
```

### Validator Compilation

Generate optimized validator code:

```php
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;

$schema = new Schema(
    type: 'object',
    properties: [
        'name' => new Schema(type: 'string'),
        'age' => new Schema(type: 'integer'),
    ],
    required: ['name', 'age'],
);

$compiler = new ValidatorCompiler();
$code = $compiler->compile($schema, 'UserValidator');

// Save generated validator
file_put_contents('UserValidator.php', $code);

// Use generated validator
require_once 'UserValidator.php';
$validator = new UserValidator();
$validator->validate(['name' => 'John', 'age' => 30]);
```

## Configuration Options

### Builder Methods

| Method | Description | Default |
|--------|-------------|---------|
| `fromYamlFile(string $path)` | Load spec from YAML file | - |
| `fromJsonFile(string $path)` | Load spec from JSON file | - |
| `fromYamlString(string $content)` | Load spec from YAML string | - |
| `fromJsonString(string $content)` | Load spec from JSON string | - |
| `withCache(SchemaCache $cache)` | Enable PSR-6 caching | `null` |
| `withEventDispatcher(EventDispatcherInterface $dispatcher)` | Set PSR-14 event dispatcher | `null` |
| `withErrorFormatter(ErrorFormatterInterface $formatter)` | Set error formatter | `SimpleFormatter` |
| `withFormat(string $type, string $format, FormatValidatorInterface $validator)` | Register custom format | - |
| `withValidatorPool(ValidatorPool $pool)` | Set custom validator pool | `new ValidatorPool()` |
| `withLogger(object $logger)` | Set PSR-3 logger | `null` |
| `withEmptyArrayStrategy(EmptyArrayStrategy $strategy)` | Set empty array validation strategy | `AllowBoth` |
| `enableCoercion()` | Enable type coercion | `false` |
| `enableNullableAsType()` | Enable nullable validation (default: true) | `true` |
| `disableNullableAsType()` | Disable nullable validation | `false` |

### Example Configuration

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;

$cachePool = new FilesystemAdapter();
$schemaCache = new SchemaCache($cachePool, 3600);

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withCache($schemaCache)           // Cache parsed specs
    ->withErrorFormatter(new DetailedFormatter())  // Detailed errors
    ->enableCoercion()                  // Auto type conversion
    ->build();
```

## Supported JSON Schema Keywords

The validator supports the following JSON Schema draft 2020-12 keywords:

### Type Validation
- `type` - String, number, integer, boolean, array, object, null
- `enum` - Enumerated values
- `const` - Constant value
- `nullable` - Allows null values (default: enabled)

### Nullable Validation

By default, the `nullable: true` schema keyword allows null values for a property:

```yaml
properties:
  username:
    type: string
    nullable: true  # Allows null values
```

This behavior is enabled by default. To disable nullable validation and treat `nullable: true` as not allowing null values:

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->disableNullableAsType()  // Optional: disable nullable validation
    ->build();
```

### String Validation
- `minLength` / `maxLength` - String length constraints
- `pattern` - Regular expression pattern
- `format` - Format validation (email, uri, uuid, date-time, etc.)

### Pattern Validation

All regular expressions in schemas are validated during schema parsing. If a pattern is invalid, an `InvalidPatternException` is thrown.

#### Supported Pattern Fields

- `pattern` - Regular expression for string validation
- `patternProperties` - Object with patterns for property keys
- `propertyNames` - Pattern for property name validation

#### Pattern Delimiters

The library automatically adds delimiters (`/`) to patterns without them. You can specify patterns with or without delimiters:

```php
// Without delimiters (recommended)
new Schema(pattern: '^test$')

// With delimiters
new Schema(pattern: '/^test$/')
```

Both variants work identically.

#### Pattern Validation Errors

Invalid patterns are detected early and throw descriptive errors:

```php
// This will throw InvalidPatternException:
// Invalid regex pattern "/[invalid/": preg_match(): No ending matching delimiter ']' found
new Schema(pattern: '[invalid')
```

### Numeric Validation
- `minimum` / `maximum` - Range constraints
- `exclusiveMinimum` / `exclusiveMaximum` - Exclusive ranges
- `multipleOf` - Numeric division

### Array Validation
- `items` / `prefixItems` - Array item validation
- `minItems` / `maxItems` - Array length constraints
- `uniqueItems` - Unique item requirement
- `contains` / `minContains` / `maxContains` - Item presence validation

### Object Validation
- `properties` - Property definitions
- `required` - Required properties
- `additionalProperties` - Additional property rules
- `minProperties` / `maxProperties` - Property count constraints
- `patternProperties` - Pattern-based property validation
- `propertyNames` - Property name validation
- `dependentSchemas` - Conditional schema application

### Composition Keywords
- `allOf` - Must match all schemas
- `anyOf` - Must match at least one schema
- `oneOf` - Must match exactly one schema
- `not` - Must not match schema
- `if` / `then` / `else` - Conditional validation

### Advanced Keywords
- `$ref` - Schema references
- `discriminator` - Polymorphic schemas
- `unevaluatedProperties` / `unevaluatedItems` - Dynamic evaluation

## Error Handling

### Validation Exceptions

All validation errors throw `ValidationException` which contains detailed error information:

```php
use Duyler\OpenApi\Validator\Exception\ValidationException;

try {
    $operation = $validator->validateRequest($request);
} catch (ValidationException $e) {
    // Get array of validation errors
    $errors = $e->getErrors();

    foreach ($errors as $error) {
        printf(
            "Path: %s\nMessage: %s\nType: %s\n\n",
            $error->dataPath(),
            $error->getMessage(),
            $error->getType()
        );
    }

    // Get formatted errors
    $formatted = $validator->getFormattedErrors($e);
    echo $formatted;
}
```

### Common Validation Errors

| Error Type | Description |
|------------|-------------|
| `TypeMismatchError` | Data type doesn't match schema type |
| `RequiredError` | Required property is missing |
| `MinLengthError` / `MaxLengthError` | String length constraint violation |
| `MinimumError` / `MaximumError` | Numeric range constraint violation |
| `PatternMismatchError` | Regular expression pattern violation |
| `InvalidFormatException` | Format validation failed (email, URI, etc.) |
| `OneOfError` / `AnyOfError` | Composition constraint violation |
| `EnumError` | Value not in allowed enum |
| `MissingParameterException` | Required parameter is missing |
| `UnsupportedMediaTypeException` | Content-Type not supported |

### Error Formatters

Choose the appropriate error formatter for your use case:

```php
// Simple formatter (default)
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;

// Detailed formatter with suggestions
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;

// JSON formatter for API responses
use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;
```

## Built-in Format Validators

The following format validators are included:

### String Formats

| Format | Description | Example                                |
|--------|-------------|----------------------------------------|
| `date-time` | ISO 8601 date-time | `2026-01-15T10:30:00Z`                 |
| `date` | ISO 8601 date | `2026-01-15`                           |
| `time` | ISO 8601 time | `10:30:00Z`                            |
| `email` | Email address | `user@example.com`                     |
| `uri` | URI | `https://example.com`                  |
| `uuid` | UUID | `550e8400-e29b-41d4-a716-446655440000` |
| `hostname` | Hostname | `example.com`                          |
| `ipv4` | IPv4 address | `192.168.1.1`                          |
| `ipv6` | IPv6 address | `2001:db8::1`                          |
| `byte` | Base64-encoded data | `SGVsbG8gd29ybGQ=`                     |
| `duration` | ISO 8601 duration | `P3Y6M4DT12H30M5S`                     |
| `json-pointer` | JSON Pointer | `/path/to/value`                       |
| `relative-json-pointer` | Relative JSON Pointer | `1/property`                           |

### Numeric Formats

| Format | Description | Example |
|--------|-------------|---------|
| `float` | Floating-point number | `3.14` |
| `double` | Double-precision number | `3.14159265359` |

### Overriding Built-in Validators

Replace built-in validators with custom implementations:

```php
$customEmailValidator = new class implements FormatValidatorInterface {
    public function validate(mixed $data): void
    {
        // Custom email validation logic
        if (!filter_var($data, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidFormatException('email', $data, 'Invalid email');
        }
    }
};

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withFormat('string', 'email', $customEmailValidator)
    ->build();
```

## Migration from league/openapi-psr7-validator

### Key Differences

| Feature | league/openapi-psr7-validator | duyler/openapi |
|---------|------------------------------|----------------|
| PHP Version | PHP 7.4+ | PHP 8.4+ |
| OpenAPI Version | 3.0 | 3.1 |
| JSON Schema | Draft 7 | Draft 2020-12 |
| Builder Pattern | Fluent builder | Fluent builder (immutable) |
| Type Coercion | Enabled by default | Opt-in |
| Error Formatting | Basic | Multiple formatters |

### Migration Examples

#### Before (league/openapi-psr7-validator)

```php
use League\OpenAPIValidation\PSR7\ValidatorBuilder;

$builder = new ValidatorBuilder();
$builder->fromYamlFile('openapi.yaml');
$requestValidator = $builder->getRequestValidator();
$responseValidator = $builder->getResponseValidator();

// Request validation
$requestValidator->validate($request);

// Response validation
$responseValidator->validate($operationAddress, $response);
```

#### After (duyler/openapi)

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->enableCoercion()
    ->build();

// Request validation - path and method are automatically detected
$operation = $validator->validateRequest($request);

// Response validation
$validator->validateResponse($response, $operation);

// Schema validation
$validator->validateSchema($data, '#/components/schemas/User');
```

## Requirements

- **PHP 8.4 or higher** - Uses modern PHP features (readonly classes, match expressions, etc.)
- **PSR-7 HTTP message** - `psr/http-message ^2.0` (e.g., `nyholm/psr7`)
- **PSR-6 cache** - `psr/cache ^3.0` (e.g., `symfony/cache`, `cache/cache`)
- **PSR-14 events** - `psr/event-dispatcher ^1.0` (e.g., `symfony/event-dispatcher`)

## Testing

```bash
# Run tests
make tests

# Run with coverage
make coverage

# Run static analysis
make psalm

# Fix code style
make cs-fix
```

## Empty Array Strategy

By default, empty arrays `[]` are valid for both `array` and `object` types. You can configure this behavior:

```php
use Duyler\OpenApi\Validator\EmptyArrayStrategy;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferObject)
    ->build();
```

Available strategies:

| Strategy | Empty array valid for array | Empty array valid for object |
|----------|----------------------------|------------------------------|
| `AllowBoth` (default) | Yes | Yes |
| `PreferArray` | Yes | No |
| `PreferObject` | No | Yes |
| `Reject` | No | No |

## Security Considerations

### XML External Entity (XXE) Protection

This library includes built-in protection against XML External Entity (XXE) attacks when parsing XML request bodies. The `XmlBodyParser` automatically disables external entity loading to prevent:

- **File disclosure attacks** - Prevents reading local files via `SYSTEM "file:///etc/passwd"`
- **SSRF attacks** - Blocks Server-Side Request Forgery via external entity references
- **Billion laughs attacks** - Mitigates denial of service through entity expansion

The protection is implemented by:

1. Disabling external entity loader via `libxml_set_external_entity_loader(null)`
2. Using internal error handling with `libxml_use_internal_errors(true)`
3. Clearing libxml errors after parsing

### Circular Reference Protection

The `RefResolver` detects and prevents circular references in OpenAPI specifications to avoid stack overflow attacks.

### PHP Configuration Recommendations

For enhanced security, ensure the following PHP settings are configured:

```ini
; Disable allow_url_fopen to prevent SSRF via XXE
allow_url_fopen = Off

; Disable allow_url_include for additional protection
allow_url_include = Off
```

### Content-Type Validation

The validator strictly validates Content-Type headers to ensure request bodies match the expected format. Unexpected content types are rejected with `UnsupportedMediaTypeException`.

### Input Validation

All input validation follows the OpenAPI 3.1 specification constraints. Schema validation prevents:

- Type confusion attacks
- Buffer overflow via length constraints
- Injection attacks via pattern validation

## License

MIT
