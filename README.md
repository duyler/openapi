# Duyler OpenAPI Validator

[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=duyler_openapi&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=duyler_openapi)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=duyler_openapi&metric=coverage)](https://sonarcloud.io/summary/new_code?id=duyler_openapi)
[![type-coverage](https://shepherd.dev/github/duyler/openapi/coverage.svg)](https://shepherd.dev/github/duyler/openapi)
[![psalm-level](https://shepherd.dev/github/duyler/openapi/level.svg)](https://shepherd.dev/github/duyler/openapi)
![PHP Version](https://img.shields.io/packagist/dependency-v/duyler/openapi/php?version=dev-main)
[![Ask DeepWiki](https://deepwiki.com/badge.svg)](https://deepwiki.com/duyler/openapi)

OpenAPI 3.2 validator for PHP 8.4+

## Features

- **Full OpenAPI 3.2 Support** - Complete implementation of OpenAPI 3.2 specification
- **JSON Schema Validation** - Full JSON Schema draft 2020-12 validation with 25+ validators
- **PSR-7 Integration** - PSR-7 HTTP message validation (requires nyholm/psr7)
- **Request Validation** - Validate path parameters, query parameters, headers, cookies, and request body
- **Response Validation** - Validate status codes, headers, and response bodies
- **Multiple Content Types** - Support for JSON, form-data, multipart, text, and XML
- **Built-in Format Validators** - 15 built-in validators (email, UUID, date-time, URI, IPv4/IPv6, etc.)
- **Custom Format Validators** - Easily register custom format validators
- **Discriminator Support** - Full support for polymorphic schemas with discriminators
- **Type Coercion** - Optional automatic type conversion
- **PSR-6 Caching** - Cache parsed OpenAPI documents for better performance
- **PSR-14 Events** - Subscribe to validation lifecycle events
- **Error Formatting** - Multiple error formatters (simple, detailed, JSON)
- **Webhooks Support** - Validate incoming webhook requests
- **Streaming Validation** - Validate NDJSON, SSE, and JSON Text Sequences responses
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

### Using the Validator Interface

The builder returns an `OpenApiValidatorInterface` instance. Use this interface for type-hinting in your services:

```php
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;

class UserService
{
    public function __construct(
        private readonly OpenApiValidatorInterface $validator,
    ) {}

    public function handleRequest(ServerRequestInterface $request): void
    {
        $operation = $this->validator->validateRequest($request);
        // ...
    }
}
```

The interface exposes the following methods:

| Method | Description |
|--------|-------------|
| `validateRequest(ServerRequestInterface $request): Operation` | Validate and return matched operation |
| `validateResponse(ResponseInterface $response, Operation $operation): void` | Validate response against operation |
| `validateSchema(mixed $data, string $schemaRef): void` | Validate data against a schema reference |
| `getFormattedErrors(ValidationException $e): string` | Format validation errors as string |
| `validateWebhook(ServerRequestInterface $request, string $name): Operation` | Validate webhook request |
| `validateCallback(ServerRequestInterface $request, string $name): Operation` | Validate callback request |
| `resolveLink(string $linkName, array $responseData): ResolvedLink` | Resolve link parameters from response data (response body only) |
| `resolveLinkWithContext(string $linkName, LinkContext $context): ResolvedLink` | Resolve link parameters with full Runtime Expression support ($request.*, $response.body/header/query, $url, $method, $statusCode) |
| `reset(): void` | Reset validator state for reuse |

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

The validator uses `nyholm/psr7` as the PSR-7 implementation:

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

Enable PSR-6 caching to skip YAML/JSON parsing and schema construction on every build. See the [Caching](#caching-1) section under Performance for configuration details and compiled validator caching.

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

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withEventDispatcher($dispatcher)
    ->build();
```

### Webhooks

Validate webhook requests using the builder API:

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

$operation = $validator->validateWebhook($request, 'payment.webhook');
```

### Callbacks

Validate callback requests using the builder API:

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

$operation = $validator->validateCallback($request, 'myCallback');
```

> **Security caveat**: Callback runtime expressions like `{$request.body#/callback_url}`
> reference the original triggering request body and cannot be resolved by the
> validator, so path validation is bypassed (any URL is accepted as a wildcard).
> When the runtime template is attacker-controlled, declared security checks on
> the callback pathItem still pass against an arbitrary URL. Use
> `enableStrictCallbackRuntimeTemplate()` to fail-closed on unresolvable runtime
> expressions and throw `UnresolvableCallbackPathException` instead:

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->enableStrictCallbackRuntimeTemplate()
    ->build();
```

### Link Resolution

Resolve OpenAPI Link parameters from response data. Both methods return a
`ResolvedLink` DTO exposing resolved `parameters`, `requestBody`, and the
optional `server` override declared by the link.

```php
use Duyler\OpenApi\Validator\Link\LinkContext;

// Simple resolution (response body only)
$result = $validator->resolveLink('GetUserById', ['id' => 42, 'name' => 'John']);
$result->parameters;   // array<string, mixed>
$result->requestBody;  // mixed
$result->server;       // Server|null

// Full resolution with Runtime Expression support
$context = new LinkContext(
    body: ['id' => 42, 'name' => 'John'],
    headers: ['X-Request-Id' => 'abc123'],
    queryParams: ['page' => 1],
    url: 'https://api.example.com/users/42',
    method: 'GET',
    statusCode: 200,
    pathParams: ['userId' => 42],
    requestHeaders: ['X-Request-Id' => 'req-789'],
    requestBody: ['extra' => 'payload'],
);
$result = $validator->resolveLinkWithContext('GetUserById', $context);
```

`resolveLink()` populates only the response body context, so it can resolve
`$response.body` expressions. Use `resolveLinkWithContext()` to supply the
full request and response state and unlock all OpenAPI 3.2 §6.19.2 runtime
expressions:

| Expression | Resolves from LinkContext |
|------------|---------------------------|
| `$url` | `url` |
| `$method` | `method` |
| `$statusCode` | `statusCode` |
| `$request.path.{name}` | `pathParams[{name}]` |
| `$request.query.{name}` | `queryParams[{name}]` |
| `$request.header.{name}` | `requestHeaders[{name}]` (case-insensitive, RFC 9110) |
| `$request.body` | `requestBody` (whole value) |
| `$request.body#/{pointer}` | `requestBody` navigated by JSON Pointer |
| `$response.body` | `body` (whole value) |
| `$response.body#/{pointer}` | `body` navigated by JSON Pointer |
| `$response.header` | `headers` (whole map) |
| `$response.header[.{name}|#/{name}]` | `headers` by name or JSON Pointer |
| `$response.query` | `queryParams` (whole map) |
| `$response.query[.{name}|#/{name}]` | `queryParams` by name or JSON Pointer |

Unsupported expressions are returned as the literal string so callers can
distinguish them from values that legitimately resolve to null.

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
openapi: 3.2.0
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
use Duyler\OpenApi\Event\ValidationWarningEvent;
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
    ValidationWarningEvent::class => [
        function (ValidationWarningEvent $event) {
            error_log(sprintf(
                "Warning at %s (property: %s, schema: %s): %s",
                $event->propertyPath,
                $event->propertyName,
                $event->schemaRef ?? 'unknown',
                $event->message
            ));
        },
    ],
]);

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withEventDispatcher($dispatcher)
    ->build();
```

Available events:

| Event | Description |
|-------|-------------|
| `ValidationStartedEvent` | Dispatched before validation begins |
| `ValidationFinishedEvent` | Dispatched after validation completes |
| `ValidationErrorEvent` | Dispatched when validation fails |
| `ValidationWarningEvent` | Dispatched for non-fatal validation warnings |

### Schema Registry

Manage multiple API versions:

> **Warning:** `getDocument()` is a method of the concrete `OpenApiValidator` class, not the `OpenApiValidatorInterface`. If you type-hint the interface, this method is unavailable and will cause a runtime error. Type-hint the concrete class directly or perform an `instanceof` check before calling it.

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Validator\OpenApiValidator;
use Duyler\OpenApi\Registry\SchemaRegistry;
use LogicException;

// Load multiple versions
$validatorV1 = OpenApiValidatorBuilder::create()
    ->fromYamlFile('api-v1.yaml')
    ->build();
if (!($validatorV1 instanceof OpenApiValidator)) {
    throw new LogicException('Validator must be an instance of OpenApiValidator to access getDocument()');
}
$documentV1 = $validatorV1->getDocument();

$validatorV2 = OpenApiValidatorBuilder::create()
    ->fromYamlFile('api-v2.yaml')
    ->build();
if (!($validatorV2 instanceof OpenApiValidator)) {
    throw new LogicException('Validator must be an instance of OpenApiValidator to access getDocument()');
}
$documentV2 = $validatorV2->getDocument();

// Register schemas
$registry = new SchemaRegistry();
$registry = $registry
    ->register('api', '1.0.0', $documentV1)
    ->register('api', '2.0.0', $documentV2);

// Get specific version (returns null if missing)
$schema = $registry->get('api', '1.0.0');

// Get latest version (sorted by semver, returns null if no versions)
$schema = $registry->get('api');

// Get specific version with fail-fast semantics
// Throws VersionNotFoundException if the schema name or version is missing
use Duyler\OpenApi\Registry\Exception\VersionNotFoundException;
try {
    $schema = $registry->getOrFail('api', '1.0.0');
    $latest = $registry->getOrFail('api');
} catch (VersionNotFoundException $e) {
    // $e->getMessage() describes the missing name and version
}

// List all versions
$versions = $registry->getVersions('api');
// ['1.0.0', '2.0.0']

// Check if a schema exists
$registry->has('api', '1.0.0'); // true
$registry->has('api');          // true
$registry->has('unknown');      // false

// List all registered schema names
$names = $registry->getNames();
// ['api']

// Count schemas and versions
$total = $registry->count();                // 1
$apiVersions = $registry->countVersions('api'); // 2
```

The registry is immutable: `register()` returns a new instance with the added schema.

#### Design choices

- **Registering an existing name+version overwrites the document silently.** This is intentional and enables immutable update patterns (for example, hot-reloading a spec in development, or replacing a placeholder document with a final one). Guard with `has()` if silent overwrite is undesirable for your use case:

  ```php
  if ($registry->has('api', '1.0.0')) {
      throw new LogicException('Refusing to overwrite api 1.0.0');
  }
  $registry = $registry->register('api', '1.0.0', $document);
  ```

- **`get()` returns `null` for a missing schema or version** (mirrors the PSR-6 cache convention). Use `has()` to distinguish "missing" from "present" before calling `get()`, or use `getOrFail()` to fail fast with a `VersionNotFoundException` (extends `\RuntimeException`).

### Validator Pool

The validator pool uses an LRU (Least Recently Used) cache to reuse validator instances. The default capacity is 128 entries. When the pool is full, the least recently used validator is evicted.

By default the pool is **not thread-safe**. It is safe to share in prefork models where each worker has isolated state (PHP-FPM, RoadRunner, FrankenPHP non-threaded). In Swoole with coroutines or FrankenPHP with threaded workers, concurrent `getOrCreate()` calls race on the check-then-act sequence. Pass a lock object exposing `lock()`/`unlock()` methods to serialize access (for example `Swoole\Lock`). Without a lock the pool is racy under shared state.

The `$factory` passed to `getOrCreate()` must be non-blocking (no I/O) and non-recursive (no nested `getOrCreate()` calls); the lock is held for the entire duration of `$factory`, so suspending or recursing inside it deadlocks.

```php
use Duyler\OpenApi\Validator\ValidatorPool;

$pool = new ValidatorPool();          // default: 128 entries
$pool = new ValidatorPool(maxSize: 64); // custom capacity

// Swoole / threaded runtimes: pass a lock to serialize access
$pool = new ValidatorPool(maxSize: 128, lock: new \Swoole\Lock());

// Validators are automatically reused and evicted when capacity is exceeded
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

The compiler generates a standalone PHP class with hardcoded validation rules. It does not depend on the library at runtime.

#### Compilation with $ref Resolution

Use `compileWithRefResolution()` to inline `$ref` references from an OpenAPI document:

```php
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\OpenApiDocument;

$compiler = new ValidatorCompiler();

// Resolve $ref pointers against the document before compiling
$code = $compiler->compileWithRefResolution($schema, 'PetValidator', $document);
```

Circular references are detected and throw a `RuntimeException`.

#### Compilation with Caching

Use `compileWithCache()` to avoid recompiling the same schema:

```php
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Compiler\CompilationCache;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$cachePool = new FilesystemAdapter();
$compilationCache = new CompilationCache($cachePool);

$compiler = new ValidatorCompiler();

// First call compiles and caches, subsequent calls return cached code
$code = $compiler->compileWithCache($schema, 'UserValidator', $compilationCache);
```

`CompilationCache` uses a PSR-6 cache pool and generates a SHA-256 hash of the schema to use as the cache key. Cached entries expire after the configured TTL (default: 24 hours / 86400 seconds). Pass a custom TTL to the `CompilationCache` constructor to override:

```php
$compilationCache = new CompilationCache($pool, ttl: 3600); // 1-hour TTL
```

#### Compiler Limitations

The compiler does not support all JSON Schema keywords. If a schema uses unsupported keywords (`allOf`, `anyOf`, `oneOf`, `not`, `if`/`then`/`else`, `patternProperties`, `format`, `minProperties`, `maxProperties`, `prefixItems`, or `additionalProperties` as a Schema — the bool `true`/`false` form is supported), the compiler throws `UnsupportedKeywordException`. See the Limitations section below for details.

`prefixItems` is rejected with `UnsupportedKeywordException` during compilation — positional item validation is not generated. Use the runtime validator for `prefixItems` enforcement.

For supported keywords, the generated code is numerically equivalent to the runtime validator for the integer/multipleOf edge cases that previously diverged:

- `type: integer` accepts whole floats (`3.0`) per JSON Schema 2020-12 §4.2.3, and rejects non-whole floats (`3.14`, `Inf`, `NaN`).
- `multipleOf` uses the integer modulus path (`%`) when both operands are integers, and falls back to a quotient-plus-relative-epsilon check (`1e-9 * max(1.0, abs($quotient))`) for float operands — matching `NumericRangeValidator::isMultipleOf` so large dividends (e.g. `1e20 / 0.1`) do not lose precision the way `fmod` does.

Use the runtime validator when you need the typed error classes (`TypeMismatchError`, `MultipleOfKeywordError`, …); the compiler only emits generic `RuntimeException`.

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
| `withLogger(LoggerInterface $logger)` | Set PSR-3 logger | `null` |
| `withEmptyArrayStrategy(EmptyArrayStrategy $strategy)` | Set empty array validation strategy | `AllowBoth` |
| `enableCoercion()` | Enable type coercion | `false` |
| `enableNullableAsType()` | Enable nullable validation (default: true) | `true` |
| `disableNullableAsType()` | Disable nullable validation | `false` |
| `enableSecurityValidation()` | Enable security scheme validation for requests | `false` |
| `enableStrictFormats()` | Reject unknown format values instead of skipping | `false` |
| `enableReportDeprecated()` | Log deprecated schema elements via PSR-3 logger | `true` |
| `enableServerPathResolution()` | Strip server base path from request path before matching | `false` |
| `enableStrictCallbackRuntimeTemplate()` | Fail-closed on callback runtime expressions like `{$request.body#/callback_url}` instead of treating them as wildcards | `false` |

Deprecated reporting is enabled by default. Without a PSR-3 logger, deprecation warnings go to `NullLogger` and produce no output. There is no `disableReportDeprecated()` method; to suppress deprecation warnings, simply omit the logger (the default behavior).

### EmptyArrayStrategy

When an OpenAPI schema defines a property as `type: array` and the value is an empty array `[]`, JSON does not distinguish between an empty array and an empty object. This strategy controls how the validator treats empty arrays:

| Strategy | Behavior |
|----------|----------|
| `AllowBoth` (default) | Empty arrays pass validation for both `array` and `object` types |
| `PreferArray` | Empty arrays are treated as arrays, not objects |
| `PreferObject` | Empty arrays are treated as objects, not arrays |
| `Reject` | Empty arrays are rejected for both `array` and `object` types |

```php
use Duyler\OpenApi\Validator\EmptyArrayStrategy;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withEmptyArrayStrategy(EmptyArrayStrategy::PreferArray)
    ->build();
```

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

## PSR-15 Middleware

Wrap the validator in a PSR-15 middleware to validate incoming requests before they reach your handlers. On validation failure, the middleware returns a `400 Bad Request` response with error details.

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Nyholm\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

final class ValidationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly OpenApiValidatorInterface $validator,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $operation = $this->validator->validateRequest($request);
        } catch (ValidationException $e) {
            return new Response(
                status: 400,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode([
                    'error' => 'Validation failed',
                    'details' => array_map(fn ($error) => [
                        'path' => $error->dataPath(),
                        'message' => $error->message(),
                    ], $e->getErrors()),
                ], JSON_PRETTY_PRINT),
            );
        } catch (Throwable $e) {
            return new Response(
                status: 400,
                headers: ['Content-Type' => 'application/json'],
                body: json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT),
            );
        }

        return $handler->handle($request->withAttribute('operation', $operation));
    }
}
```

Register the middleware with your framework's middleware pipeline:

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

$middleware = new ValidationMiddleware($validator);

// Register with any PSR-15 compatible framework or dispatcher
// Example with Mezzio:
// $pipeline->pipe(new ValidationMiddleware($validator));
```

> Note: The PSR-15 interfaces require the `psr/http-server-middleware` package, typically provided by your framework.

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
            $error->message(),
            $error->getType()
        );
    }

    // Get formatted errors
    $formatted = $validator->getFormattedErrors($e);
    echo $formatted;
}
```

### Validation Error Reference

All errors implement `ValidationErrorInterface` and provide `dataPath()`, `schemaPath()`, `keyword()`, `message()`, `params()`, `suggestion()`, and `getType()` methods. The `getType()` method returns the validation keyword that triggered the error (e.g., `'type'`, `'minLength'`, `'format'`).

#### Type and Value Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `TypeMismatchError` | `type` | Data type doesn't match schema type |
| `EnumError` | `enum` | Value not in allowed enum |
| `ConstError` | `const` | Value doesn't match constant |
| `InvalidDataTypeException` | `invalid` | Invalid data type encountered |

#### Format Validation Errors

`InvalidFormatException` extends `RuntimeException` and implements `ValidationErrorInterface` directly (without extending `AbstractValidationError`). It is thrown by format validators rather than the schema validator.

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `InvalidFormatException` | `format` | Format validation failed (email, URI, etc.) |

#### String Validation Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `MinLengthError` | `minLength` | String length below minimum |
| `MaxLengthError` | `maxLength` | String length exceeds maximum |
| `PatternMismatchError` | `pattern` | Regular expression pattern violation |

#### Numeric Validation Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `MinimumError` | `minimum` / `exclusiveMinimum` | Value below minimum (inclusive/exclusive) |
| `MaximumError` | `maximum` / `exclusiveMaximum` | Value exceeds maximum (inclusive/exclusive) |
| `MultipleOfKeywordError` | `multipleOf` | Value is not a multiple of the specified number |

> Note: `MinimumError::keyword()` always returns `'minimum'` for both `minimum` and `exclusiveMinimum` violations. Similarly, `MaximumError::keyword()` always returns `'maximum'`. Use `schemaPath()` to distinguish between inclusive (`/minimum`, `/maximum`) and exclusive (`/exclusiveMinimum`, `/exclusiveMaximum`) constraints.

#### Array Validation Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `MinItemsError` | `minItems` | Array has fewer items than required |
| `MaxItemsError` | `maxItems` | Array has more items than allowed |
| `DuplicateItemsError` | `uniqueItems` | Array contains duplicate items |
| `ContainsMatchError` | `contains` | Array has no matching items for `contains` |
| `MinContainsError` | `minContains` | Too few items match `contains` |
| `MaxContainsError` | `maxContains` | Too many items match `contains` |

#### Object Validation Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `RequiredError` | `required` | Required property is missing |
| `MinPropertiesError` | `minProperties` | Object has fewer properties than required |
| `MaxPropertiesError` | `maxProperties` | Object has more properties than allowed |
| `AdditionalPropertyError` | `additionalProperties` | Additional property present despite additionalProperties: false |
| `UnevaluatedPropertyError` | `unevaluatedProperties` | Property not allowed and not evaluated by any keyword |
| `ReadOnlyPropertyError` | `readOnly` | Read-only property was sent in a request payload |
| `WriteOnlyPropertyError` | `writeOnly` | Write-only property was returned in a response payload |

#### Composition Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `OneOfError` | `oneOf` | Data matches multiple schemas (should match exactly one) |
| `AnyOfError` | `anyOf` | Data doesn't match any of the schemas |
| `NotValidationError` | `not` | Data matches the schema forbidden by `not` |
| `DiscriminatorDataError` | `oneOf` | Discriminator validation received non-object data |

#### Discriminator Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `DiscriminatorMismatchException` | `discriminator` | Discriminator type doesn't match expected |
| `InvalidDiscriminatorValueException` | `discriminator` | Discriminator property has wrong type |
| `UnknownDiscriminatorValueException` | `discriminator` | Discriminator value not in mapping |
| `MissingDiscriminatorPropertyException` | `discriminator` | Required discriminator property is missing |

#### Security Errors

| Error Type | Keyword | Description |
|------------|---------|-------------|
| `MissingSecurityCredentialsError` | `security` | Required security credentials missing from request |

#### HTTP, Request, and Schema Errors

These exceptions extend `RuntimeException`, `Exception`, or `InvalidArgumentException` directly and do not implement `ValidationErrorInterface`:

| Exception | Description |
|-----------|-------------|
| `MissingParameterException` | Required parameter is missing from request |
| `MissingRequestBodyException` | Request body is required but missing or empty |
| `UnsupportedMediaTypeException` | Content-Type not supported by the operation |
| `PathMismatchException` | Request path doesn't match any operation template |
| `InvalidParameterException` | Parameter value is malformed or invalid |
| `InvalidPatternException` | Invalid regex pattern in schema definition |
| `UndefinedResponseException` | Response status code not defined in spec |
| `RefResolutionException` | Failed to resolve `$ref` reference |
| `UnresolvableCallbackPathException` | Callback runtime template (e.g. `{$request.body#/callback_url}`) cannot be resolved in strict mode |
| `SchemaDepthExceededException` | Maximum schema nesting depth exceeded |
| `UnknownValidatorException` | Unknown validator type requested |
| `VersionNotFoundException` | Requested schema name or version is not registered (thrown by `SchemaRegistry::getOrFail()`) |

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

| Feature | league/openapi-psr7-validator | duyler/openapi             |
|---------|------------------------------|----------------------------|
| PHP Version | PHP 7.4+ | PHP 8.4+                   |
| OpenAPI Version | 3.0 | 3.0, 3.1, 3.2              |
| JSON Schema | Draft 7 | Draft 2020-12              |
| Builder Pattern | Fluent builder | Fluent builder (immutable) |
| Type Coercion | Enabled by default | Opt-in                     |
| Error Formatting | Basic | Multiple formatters        |

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

## Performance

### Benchmark Results

The following measurements come from the test suite benchmarks run on a standard development machine. Actual numbers vary depending on hardware, PHP version, and schema complexity.

| Scenario | Schema | Avg per validation | Memory per request |
|----------|--------|--------------------|--------------------|
| Simple (GET /ping) | 1 path, no body | < 5 ms | - |
| Medium (POST /users) | 4 properties, format validation, enum | < 10 ms | - |
| Complex (petstore.yaml) | Multiple paths, `$ref`, nested schemas | < 10 ms | - |
| Path scanning | 100 routes, 50 iterations | < 100 ms total | < 1 MB growth |
| Full request+response cycle | 2 properties, email format | - | < 50 KB |

These numbers represent upper bounds enforced by assertions in `tests/Benchmark/PerformanceBenchmarkTest.php`. Real-world performance is typically better.

### Caching

Enable PSR-6 caching when the OpenAPI specification does not change between requests. This skips YAML/JSON parsing and schema construction on every build:

```php
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$cachePool = new FilesystemAdapter();
$schemaCache = new SchemaCache($cachePool, 3600); // TTL: 1 hour

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withCache($schemaCache)
    ->build();
```

For compiled validators, use `CompilationCache` to avoid regenerating PHP code:

```php
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Compiler\CompilationCache;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

$compilationCache = new CompilationCache(new FilesystemAdapter());
$compiler = new ValidatorCompiler();

$code = $compiler->compileWithCache($schema, 'UserValidator', $compilationCache);
```

### When to Use Compilation

The `ValidatorCompiler` generates standalone PHP classes with hardcoded validation rules. This is faster than runtime schema traversal because the compiled code has no reflection, no `$ref` resolution, and no dynamic dispatch.

Use compilation when:
- The schema is stable and does not change at runtime
- You need maximum throughput for hot-path validation
- The schema uses only basic keywords (no `allOf`, `anyOf`, `oneOf`, `not`, `if`/`then`/`else`, `format`)

Stick with runtime validation when:
- The schema changes frequently or is user-defined
- You need composition keywords (`allOf`, `anyOf`, `oneOf`)
- You need format validation (`email`, `uuid`, `date-time`, etc.)
- You need `$ref` resolution against an OpenAPI document (use `compileWithRefResolution()` instead)

### Coercion Impact

Enabling coercion with `enableCoercion()` adds a type conversion pass before validation. For request parameters (query, path, headers), this converts string values to their declared types (e.g., `"123"` to `123`). The overhead is proportional to the number of parameters and properties in the request body. For most APIs, the cost is negligible compared to the validation itself.

### Memory Profiling

The validator creates a fixed set of objects during `build()`. Per-request memory usage stays under 50 KB for typical schemas. To profile memory in your application:

```php
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

gc_collect_cycles();
$before = memory_get_usage();

$operation = $validator->validateRequest($request);
$validator->validateResponse($response, $operation);

gc_collect_cycles();
$after = memory_get_usage();

printf("Memory delta: %d bytes\n", $after - $before);
```

### Long-Running Processes

The validator instance is safe to reuse across requests in long-running
processes that use the **prefork execution model**: PHP-FPM, RoadRunner, and
FrankenPHP in non-threaded mode. The internal `ValidatorPool` uses an LRU cache
to reuse validator instances without manual cleanup. The pool has a default
capacity of 128 entries and automatically evicts the least recently used entries
when full.

For **Swoole with coroutines** or **FrankenPHP threaded workers**, the validator
requires additional concurrency protection:

- Each coroutine or worker must use its own `ValidatorPool` instance, or you
  must inject a lock (any object with `lock()` and `unlock()` methods, such as
  `Swoole\Lock`) into the `ValidatorPool` constructor.
- libxml global state (`libxml_use_internal_errors`, external entity loader) is
  shared across coroutines. XML body parsing and `contentMediaType: application/xml`
  validation may race on these globals.
- `DateTime::getLastErrors()` and `json_last_error()` are also global. Prefer
  code paths that use `JSON_THROW_ON_ERROR` and do not rely on these globals.

The prefork model (one request per worker process, no shared mutable state) is
the safest option and requires no extra configuration.

```php
// Build once at worker startup
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withCache($schemaCache)
    ->build();

// Reuse across requests (prefork model only)
while ($request = $worker->waitRequest()) {
    $operation = $validator->validateRequest($request);
    // ...
}
```

If the OpenAPI specification changes at runtime, rebuild the validator. The old instances will be garbage-collected when no longer referenced.

## Streaming Response Validation

The validator supports three streaming response formats. Each item in the stream is validated individually against the schema defined in `itemSchema` (or `schema` as fallback).

### Supported Content Types

| Format | Content-Type | Specification |
|--------|-------------|---------------|
| JSON Lines / NDJSON | `application/jsonl` or `application/x-ndjson` | Newline-delimited JSON objects |
| Server-Sent Events | `text/event-stream` | W3C SSE specification |
| JSON Text Sequences | `application/json-seq` | RFC 7464 |

### OpenAPI Specification

Use the `itemSchema` keyword within the media type definition to declare the schema for each individual item in the stream:

```yaml
openapi: '3.2.0'
info:
  title: Streaming API
  version: '1.0.0'
paths:
  /logs:
    get:
      operationId: getLogs
      responses:
        '200':
          description: Log stream
          content:
            application/jsonl:
              itemSchema:
                type: object
                properties:
                  timestamp:
                    type: string
                    format: date-time
                  level:
                    type: string
                    enum: [debug, info, warn, error]
                  message:
                    type: string
                required:
                  - timestamp
                  - level
                  - message
  /events:
    get:
      operationId: getEvents
      responses:
        '200':
          description: Event stream
          content:
            text/event-stream:
              itemSchema:
                type: object
                properties:
                  event:
                    type: string
                  data:
                    type: object
                    properties:
                      message:
                        type: string
                      count:
                        type: integer
                required:
                  - event
                  - data
  /records:
    get:
      operationId: getRecords
      responses:
        '200':
          description: Record stream
          content:
            application/json-seq:
              itemSchema:
                type: object
                properties:
                  id:
                    type: string
                  value:
                    type: string
                required:
                  - id
```

### NDJSON / JSON Lines

Each line in the response body is a separate JSON object. Empty lines are skipped.

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$factory = new Psr17Factory();
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

$request = $factory->createServerRequest('GET', '/logs');
$operation = $validator->validateRequest($request);

$body = '{"timestamp":"2024-01-01T00:00:00Z","level":"info","message":"Started"}' . "\n"
    . '{"timestamp":"2024-01-01T00:00:01Z","level":"error","message":"Failed"}';

$response = $factory->createResponse(200)
    ->withHeader('Content-Type', 'application/jsonl')
    ->withBody($factory->createStream($body));

$validator->validateResponse($response, $operation);
```

### Server-Sent Events (SSE)

The parser handles the standard SSE format with `event`, `data`, `id`, and `retry` fields. Comments (lines starting with `:`) are ignored. The `data` field is automatically decoded from JSON when possible. The `retry` field is the W3C reconnection time in integer milliseconds; non-numeric values are ignored. When an SSE event has `data:` but no `event:` field, the parser assigns the W3C default event type `'message'`.

```php
$request = $factory->createServerRequest('GET', '/events');
$operation = $validator->validateRequest($request);

$body = "event: message\n"
    . "data: {\"message\":\"hello\",\"count\":1}\n\n"
    . "event: update\n"
    . "data: {\"message\":\"world\",\"count\":2}\n\n";

$response = $factory->createResponse(200)
    ->withHeader('Content-Type', 'text/event-stream')
    ->withBody($factory->createStream($body));

$validator->validateResponse($response, $operation);
```

### JSON Text Sequences (RFC 7464)

Each record is prefixed with a record separator byte (`0x1E`). This format avoids ambiguity with newlines inside JSON strings.

```php
$request = $factory->createServerRequest('GET', '/records');
$operation = $validator->validateRequest($request);

$body = "\x1E" . '{"id":"1","value":"first"}' . "\x1E" . '{"id":"2","value":"second"}';

$response = $factory->createResponse(200)
    ->withHeader('Content-Type', 'application/json-seq')
    ->withBody($factory->createStream($body));

$validator->validateResponse($response, $operation);
```

### Error Handling in Streams

When a stream item fails to parse (invalid JSON), the parser logs a warning and yields `null` for that item. The validator skips `null` items. When a parsed item fails schema validation, a `ValidationException` is thrown immediately.

```php
use Duyler\OpenApi\Validator\Response\StreamingContentParser;
use Psr\Log\LoggerInterface;

// Custom logger to track parse failures
$parser = new StreamingContentParser($logger);

// Returns [valid, null, valid] - second item is null due to invalid JSON
$items = $parser->parseJsonLines('{"ok":true}' . "\n" . 'bad json' . "\n" . '{"ok":false}');
```

## Limitations

### JSON Schema Coverage

The validator covers approximately 95% of JSON Schema draft 2020-12 keywords. The following are not fully supported:

- `$dynamicRef` / `$dynamicAnchor` - dynamic schema resolution
- `$recursiveRef` / `$recursiveAnchor` - recursive schema resolution
- `contentEncoding` / `contentMediaType` - content validation
- Custom vocabularies and keyword extensions

### Validator Compiler

The `ValidatorCompiler` is marked as `@experimental`. It supports a subset of JSON Schema keywords: `type`, `enum`, `const`, `minLength`, `maxLength`, `minimum`, `maximum`, `exclusiveMinimum`, `exclusiveMaximum`, `multipleOf`, `pattern`, `minItems`, `maxItems`, `uniqueItems`, `properties`, `required`, `additionalProperties`, `items`.

The compiler does not support composition keywords (`allOf`, `anyOf`, `oneOf`, `not`), conditional keywords (`if`/`then`/`else`), `patternProperties`, `format`, `minProperties`, `maxProperties`, `prefixItems`, or `additionalProperties` as a Schema (the bool `true`/`false` form is supported). If any of these are present in a schema, `compile()` throws `UnsupportedKeywordException`.

Generated validators throw generic `RuntimeException` on failure rather than the typed error classes used by the runtime validator.

### Content Negotiation

Request body validation honours RFC 7231 §3.1.1.1 wildcard patterns declared in the OpenAPI specification. The most specific declaration wins:

1. Exact match (e.g., `application/json`)
2. Subtype wildcard (e.g., `application/*`)
3. Universal wildcard (`*/*`)

When a wildcard declaration matches, the request body is parsed according to the concrete `Content-Type` sent by the client, so a spec `application/*` with a request `Content-Type: application/json` is decoded as JSON. A request whose `Content-Type` does not match any declared media type is rejected with `UnsupportedMediaTypeException` (fail-closed).

Response body validation does not expand wildcards: media type matching uses literal string comparison, and a response `Content-Type` that does not match a declared media type simply skips response body validation.

### Security Validation

Security scheme validation is basic. The validator checks that required credentials are present in the request (headers, query parameters, or cookies) but does not verify their correctness or format. Token validation, signature checking, and OAuth flow handling are outside the scope of this library.

> **Note:** Security scheme validation is invoked by `validateRequest()`, `validateWebhook()`, and `validateCallback()` when `enableSecurityValidation()` is enabled. If a security scheme is defined at the document or operation level, the validator checks that required credentials are present in the request. If `enableSecurityValidation()` is not called, security validation is skipped (default behavior).

The following security scheme types are supported:

- `http/bearer` - Checks for `Authorization: Bearer ...` header
- `apiKey` (query, header, cookie) - Checks for the named parameter in the specified location

The following scheme types are not supported and will produce an error when encountered:

- `http/basic` - Basic authentication
- `oauth2` - OAuth 2.0 flows
- `openIdConnect` - OpenID Connect Discovery

## Requirements

- **PHP 8.4 or higher** - Uses modern PHP features (readonly classes, match expressions, etc.)
- **PSR-7 HTTP message** - `psr/http-message ^2.0` (required: `nyholm/psr7 ^1.8`)
- **PSR-6 cache** - `psr/cache ^3.0` (e.g., `symfony/cache`, `cache/cache`)
- **PSR-14 events** - `psr/event-dispatcher ^1.0` (e.g., `symfony/event-dispatcher`)
- **PSR-3 logging** - `psr/log ^3.0` (included, optional to use via `withLogger()`)
- **YAML parser** - `symfony/yaml ^7.0 || ^8.0`

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

## License

MIT
