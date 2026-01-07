# Duyler OpenAPI Validator

OpenAPI 3.1 validator for PHP 8.4+

## Features

- Full OpenAPI 3.1 specification support
- PSR-7 HTTP message integration
- Request and response validation
- Schema validation with JSON Schema draft 2020-12
- Built-in format validators (email, UUID, date-time, etc.)
- Webhooks support
- PSR-6 caching support
- PSR-14 event system

## Installation

```bash
composer require duyler/openapi
```

## Quick Start

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();

// Validate request
$validator->validateRequest($request, '/users', 'POST');

// Validate response
$validator->validateResponse($response, '/users', 'POST');

// Validate schema
$validator->validateSchema($data, '#/components/schemas/User');
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

$validator->validateRequest($request, '/users', 'POST');
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

### Schema Registry

Manage multiple schema versions:

```php
use Duyler\OpenApi\Registry\SchemaRegistry;

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
```

## Configuration Options

```php
use Duyler\OpenApi\Builder\OpenApiValidatorBuilder;

$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->withValidatorPool($customPool)
    ->withCache($schemaCache)
    ->withLogger($psr3Logger)
    ->withErrorFormatter($customFormatter)
    ->withFormat('string', 'custom-format', $customValidator)
    ->enableCoercion()
    ->enableNullableAsType()
    ->build();
```

## Error Handling

```php
use Duyler\OpenApi\Validator\Exception\ValidationException;

try {
    $validator->validateRequest($request, '/users', 'POST');
} catch (ValidationException $e) {
    $errors = $e->getErrors();
    $formatted = $validator->getFormattedErrors($e);

    foreach ($errors as $error) {
        printf("Validation error: %s\n", $error->message);
    }
}
```

## Requirements

- PHP 8.4 or higher
- PSR-7 HTTP message implementation
- PSR-6 cache implementation (optional, for caching)
- PSR-14 event dispatcher (optional, for events)

## Testing

```bash
# Run tests
make test
```

## License

MIT

## Support

For documentation, see: https://duyler.org/en/docs/openapi/

## Migration from league/openapi-psr7-validator

This library provides a similar API to league/openapi-psr7-validator with key differences:

1. PHP 8.4+ required
2. OpenAPI 3.1 (3.0 support)
3. Different builder pattern approach

Basic migration:

```php
// Before (league/openapi-psr7-validator)
$psr7Validator = new \League\OpenAPIValidation\PSR7\ValidatorBuilder();
$psr7Validator->fromYamlFile('openapi.yaml');
$requestValidator = $psr7Validator->getRequestValidator();
$requestValidator->validate($request);

// After (duyler/openapi-validator)
$validator = OpenApiValidatorBuilder::create()
    ->fromYamlFile('openapi.yaml')
    ->build();
$validator->validateRequest($request, '/users', 'POST');
```
