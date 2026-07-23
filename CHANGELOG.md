# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

Preparation for the 1.0.0 stable release. Tracking-only section; populate
each subsection as work lands on `dev`.

### Added
- _(nothing yet)_

### Changed
- _(nothing yet)_

### Deprecated
- _(nothing yet)_

### Removed
- _(nothing yet)_

### Fixed
- _(nothing yet)_

### Security
- _(nothing yet)_

## [0.6.0] - 2026-07-22

This release consolidates three production-readiness waves (R4, R3, and the
earlier SPEC/SEC series) into a single section. The R4 series closed 37
EI tickets across 18 tasks targeting correctness, security, spec compliance,
PSR-14, and regression coverage. The R3 series added missing format
validators, hardened the public exception surface, and closed another batch
of SPEC/SEC bugs. The older wave fixed JSON Schema 2020-12 §4.2.2 numeric
equality, `$ref` sibling merging, and JSON Pointer escaping.

### Added

- R4 regression test suites under `tests/Unit/Regression/R4/` covering
  nested `$ref`, discriminator, YAML billion-laughs, info-leak, coercion,
  `time`/format ordering, cache keys, PSR-14 stoppable events
  (R4-TEST-004, R4-TEST-005, R4-TEST-006, R4-TEST-007, R4-TEST-008,
  R4-TEST-009, R4-TEST-010, R4-TEST-011, R4-TEST-012).
- `EmailValidator` accepts RFC 5321 domain literals, quoted local parts, and
  SMTPUTF8 addresses per RFC 6531 (R3-SPEC-010, C-010).
- Registered 10 missing format validators in `BuiltinFormats` (`int32`,
  `int64`, plus 8 others); `enableStrictFormats()` works on typical
  OpenAPI 3.2 specs (R3-SPEC-013, C-013).
- `ValidatorPool::forCoroutineRuntime(object $lock, ?int $maxSize = null)`
  named-constructor for Swoole-coroutine and FrankenPHP-threaded contracts
  (R3-CONCURRENCY-001).
- Dedicated unit-test coverage for `WebhookValidator`,
  `Validation\CallbackValidator`, `ResponseValidationHandler`
  (R3-TEST-003, R3-TEST-006, R3-TEST-007).
- Property-based testing foundation under `tests/Property/` using
  `giorgiosironi/eris` (R3-TEST-021).
- CI matrix `.github/workflows/ci.yml` adds `swoole_tests` job in
  `phpswoole/swoole:php8.5` with `Verify Swoole extension loaded` step
  (R3-TEST-024, R3-TEST-025).

### Changed

- `CallbackValidator` (outer and inner) defaults
  `$strictCallbackRuntimeTemplate` to `true`, matching builder default
  (R3-SEC-009, R3-SEC-025, ASVS 4.0 V12.6.1, CWE-918, CWE-1188).
- `ValidatorPool`, `LibxmlSecuredContext`, `PregExecutor` carry explicit
  `@danger NOT_THREAD_SAFE` marker; README enumerates per-class mitigations
  (R3-CONCURRENCY-001, R3-CONCURRENCY-002, R3-CONCURRENCY-003).
- `TypeCoercer::coerce()` defaults to strict (`$strict = true`); legacy
  callers MUST pass `false` explicitly via `disableStrictCoercion()`
  (TYPECOERCER-DEFAULT-STRICT).
- Removed inline `//` comments from `src/` (72 lines across 15 files) per
  `php-best-practices.md` §12 (R3-BP-003, BP-006).

### Deprecated

- `Validator\SchemaValidator\SchemaValidator` (legacy stateless JSON Schema
  dispatcher), removal in 2.0. Migrate to
  `Validator\Schema\SchemaValidatorWithContext` (returned by builder).
- `Validator\SchemaValidator\ValidatorDependencies` (legacy constructor
  dependency bag), removal in 2.0. Migrate to
  `Validator\Dto\SchemaValidatorDependencies`.

### Removed

- `frankenphp_tests` job removed from `.github/workflows/ci.yml`. Extension
  is statically compiled into frankenphp binary, registered only in worker
  SAPI; CLI SAPI always skipped. Test class retained (R4-TEST-001).

### Fixed

R4 production-readiness series (37 EI tickets across 18 tasks). Highlights:

- `NumericRangeValidator::isMultipleOf` no longer throws for valid int64
  values when `bcmath` not loaded; pure-PHP decimal string-modulus added
  (R4-CORRECTNESS-008).
- `DiscriminatorValidator` forks parent `ValidationContext` and merges child
  annotations, so `unevaluatedProperties`/`unevaluatedItems` honour
  target-validated items (R4-CORRECTNESS-005, R4-SPEC-015).
- `TimeValidator` enforces RFC 3339 §5.6 offset (`Z`, `+HH:MM`, `-HH:MM`)
  for `format: time`; offset-less strings now rejected (R4-SPEC-006).
- `OneOfValidatorWithContext::hasNullableSchema` considers native nullable
  form `type: ["string", "null"]` (R4-CORRECTNESS-009).
- `http/bearer` scheme check uses end-anchored regex
  (`/^bearer\s+\S+\s*$/i`) to reject multi-challenge headers like
  `Bearer fake, Basic ...` (R4-SEC-016).
- `coerceToInteger`/`coerceToIntegerStrict` reject floats overflowing int64
  near `PHP_INT_MAX` via `floatExceedsInt64Range()` (R4-SEC-013).
- `NumberStringNormalizer::castStringToFloatOrFail` rejects INF overflow
  (`1e309`) with clear reason instead of misleading precision-loss message
  (R4-SEC-014).
- `coerceToIntegerStrict` accepts whole-valued floats (`3.0`) for
  `type: integer` per §4.2.3 (R4-SPEC-005).
- `oauth2`/`openIdConnect`/`http/basic`/`http/digest`/`mutualTLS`/unknown
  scheme types throw new `UnsupportedSecuritySchemeException` (extends
  `\RuntimeException`) instead of misclassifying as missing credentials
  (R4-SEC-010). NOT wrapped into `ValidationException`.
- OAuth2 scopes declared on security requirement forwarded to PSR-3 logger
  at `debug` level instead of discarded (R4-SPEC-003).
- AND/OR semantics for mixed supported/unsupported security requirements
  honoured: AND-list with one unsupported fails closed; OR-list tries
  alternatives.
- Route `preg_match` in `RegexValidator`, `PathParser`, `CallbackValidator`
  through `PregExecutor` so attacker patterns honour `maxRegexBacktracks`
  (R4-SEC-002, R4-SEC-003, R4-SEC-004).
- Resolve `$ref` inside all 13 schema-typed keywords before legacy-engine
  delegation; previously invalid data passed silently (R4-CORRECTNESS-001,
  R4-CORRECTNESS-016, R4-SEC-001, R4-SPEC-014, R4-ARCH-001).
- Discriminator validator enumerates all composition arrays (`oneOf`+
  `anyOf`+`allOf`), continues on unresolved nested candidates, and applies
  `defaultMapping` fallback (R4-CORRECTNESS-002, R4-CORRECTNESS-015,
  R4-SPEC-002, R4-SPEC-004).
- ValidatorCompiler emits all supported keywords for nested
  `properties`/`items`, inlines `JsonEquals` for `enum` in `items`;
  unsupported keywords rejected at every depth via
  `UnsupportedKeywordException` (R4-CORRECTNESS-004, R4-CORRECTNESS-013).
- `ArrayDispatcher::dispatch` respects PSR-14 `StoppableEventInterface`
  (R4-PSR-001).
- Rebuild `FormatRegistry` in `build()` so builtin format validators share
  configured `PregExecutor` regardless of `withFormat()` order (R4-SEC-009).
- `OpenApiValidatorBuilder` adds parse-affecting config (`maxSpecDepth`/
  `maxSpecSizeBytes`/`externalRefAllowedRoot`/`externalRefMaxBytes`) to
  `SchemaCache` cache-key hash to prevent cross-caller cache-poisoning
  (R4-SEC-008, R4-SEC-017).

R3 production-readiness series:

- `JsonParser` enforces configurable size cap (default 1 MB) before parsing,
  matching `YamlParser` (R3-SEC-014, CWE-400, CWE-770).
- `TypeCoercer::coerceToType` coerces non-string inputs (`bool`/`int`/`float`)
  through typed coercion path (R3-CORRECTNESS-005).
- `TypeCoercer::coerceUnionType` catches `TypeMismatchError` and continues
  to next type in union (R3-CORRECTNESS-006).
- `UriValidator` accepts all RFC 3986 valid URIs including scheme-less forms
  and non-allowlisted schemes (R3-SPEC-008, R3-SPEC-009).
- `UriValidator` error messages no longer disclose URI scheme/port
  (R3-SEC-021, CWE-209).
- `PatternValidator::validate()` calls `RegexValidator::validate()` before
  match, closing pattern-length cap gap (R3-SEC-002, ASVS 4.0 V5.3.4,
  CWE-1333, CWE-400, CWE-770).
- `PregExecutor` sets `pcre.recursion_limit` (default 512) alongside
  `pcre.backtrack_limit` for every call; the Swoole-race caveat from
  R3-SEC-020 now applies symmetrically to both ini variables
  (R3-SEC-017, R3-SEC-020, CWE-1333, CWE-400, CWE-770).
- `SchemaSiblingMerger::merge()` honours §8.2.3 ALL OF semantics for ten
  non-bound fields previously using sibling-wins (R3-SPEC-006, C-006).
- `SchemaSiblingMerger::mergeSchemaOrBool` recursively merges two Schema
  instances instead of silent sibling overwrite (R3-SPEC-019, O-042).
- `Schema` accepts `Schema|bool|null` for every schema-typed keyword
  (`items`/`contains`/`propertyNames`/`if`/`then`/`else`/`not`/`unevaluatedItems`)
  (R3-SPEC-005, C-005).
- `UnevaluatedPropertiesValidator` consults every adjacent in-place
  applicator via `ValidationContext` annotation propagation (R3-SPEC-001).
- `UnevaluatedItemsValidator::getEvaluatedItemIndices` (renamed) treats
  `items` as evaluating every index `>= prefixItems count` (R3-SPEC-002).
- `ContainsValidator` registers matched indices in `ValidationContext` via
  `markItemEvaluated()` (R3-SPEC-003).
- `UnevaluatedItemsValidator` consumes composition annotations via
  `evaluatedItemIndices()` channel (R3-SPEC-004).
- `ValidatorCompiler::generateConstCheck` emits inline `jsonEquals` call
  instead of `!==` (R3-CORRECTNESS-001).
- `ValidatorCompiler::generateEnumCheck` emits linear-scan with inline
  `jsonEquals` instead of `in_array(..., true)` (R3-CORRECTNESS-002).
- `ValidatorCompiler::generateArrayCheck` for `uniqueItems` emits
  canonicalised isset lookup (O(n)) plus `100000` cap, wraps `json_encode`
  in try/catch (R3-CORRECTNESS-003, R3-CORRECTNESS-013).
- `ValidatorCompiler::generatePatternCheck` disambiguates PCRE error
  (`false`) from no-match (`0`) (R3-PERF-001).
- `CompilationCache::generateKey()` includes target PHP class name to close
  class-name collision; WeakMap widened to
  `WeakMap<Schema, array<string, string>>` (R3-ARCH-001).
- `ValidatorPool::acquireLock()`/`releaseLock()` no longer re-check
  `method_exists($this->lock, ...)` on hot path (R3-CONCURRENCY-004, O-034).
- `generateCacheKeyFromFile` incorporates SHA-256 of spec contents to
  prevent cache-poisoning via size/mtime-preserving tampering (R3-SEC-003,
  S-003, ASVS V8.1.3, CWE-349, CWE-1023).

Older SPEC/SEC series:

- `JsonEquals::equals()` rejects mixed int+float equality when int side
  outside IEEE 754 safe range via `SAFE_INT64_FLOAT_BOUNDARY = 2^53`
  (PHP_INT_MAX-FLOAT-BOUNDARY).
- `AbstractCoercer::coerceToInteger()` (non-strict) rejects floats in
  boundary `[PHP_INT_MAX - 1023, PHP_INT_MAX]` and symmetric negative range
  via `>= (float) PHP_INT_MAX` (WEAKPHP-INT-CHECK).
- JSON Pointer escape sequences (`~1` for `/`, `~0` for `~`) now decoded
  in `$ref` fragment resolution per RFC 6901 §3 (SPEC-01).
- `$ref` siblings evaluated alongside resolved schema per §8.2.3 via new
  `SchemaSiblingMerger` class (SPEC-02).
- `JsonEquals::equals()` performs order-independent comparison for objects
  per §4.2.2 (SPEC-03), direct `===` for int-to-int to preserve int64
  precision (SPEC-05), and handles bool vs int (SPEC-04).
- `minLength`/`maxLength` count UTF-16 code units per §4.2.1 via new
  `Utf16::length()` helper (SPEC-06); compiler inlines same logic.
- `deepObject` parameter style handles bracket notation via `QueryParser`
  rather than JSON decoding (SPEC-07).
- `SchemaSiblingMerger::merge()` aligns scalar bounds, `nullable`,
  composition keywords with §8.2.3 ALL OF semantics (SPEC-02B).
- `ArrayLengthValidator::encodeArrayKey()` canonicalizes array keys before
  `json_encode` for `uniqueItems` per §4.2.2 (SPEC-03B).
- Documentation: `.ai/guides/process-violations-review-work.md` forbids
  working-tree mutation during review-work (RACE-CONDITION-PROCESS).

### Security

- All exception classes sanitize `__toString()` via new
  `SanitizableExceptionTrait`, returning only `getMessage()` (CWE-209,
  CWE-497, S-019, R3-SEC-INFO-LEAK-SYSTEMATIC).
- Category-B exception sensitive props moved to `protected readonly` with
  opt-in `$e->value(reveal: true)` (default `'<redacted>'`). Affects 5
  classes incl. `InvalidFormatException`, `InvalidParameterException`
  (S-005, S-008, S-016, S-032).
- `MalformedStreamRecordException::$record` truncated to 256 bytes via
  `LogContextSanitizer::truncate()` (S-007, CWE-209).
- `FileExternalRefResolver` passes `basename()` not absolute path in
  `ExternalRefSecurityException` (S-005).
- `UnresolvableRefException` truncates `$reason`/`$internalTrace` to 256
  bytes (S-008); `ExternalRefSecurityException::$ref` truncated similarly
  (S-005).
- `UriValidator` no longer interpolates attacker-supplied scheme/port into
  `InvalidFormatException` messages (R3-SEC-021, CWE-209).
- `ValidatorCompiler` escapes attacker-controlled property names in
  generated throw messages via `var_export($key, true)`, replaces
  `addslashes()` (R3-SEC-015, CWE-094, CWE-133).
- `YamlParser` billion-laughs defence: pre-parse caps `MAX_ANCHORS = 100`,
  `MAX_ALIASES = 1000`, `MAX_ALIAS_DEPTH = 10` run before Symfony parser
  (R4-SEC-012, CWE-400, CWE-770).
- Sanitize `getMessage()` in `PathMismatchException`/
  `OperationNotFoundException`/`UnsupportedMediaTypeException`/
  `InvalidParameterException` so attacker paths/methods/Content-Types
  cannot leak (R4-SEC-007a/b/c/d, CWE-209, CWE-532).
- Bounded streaming record count: NDJSON/SSE/JSON Text Sequences enforce
  configurable cap (default 100 000) via `TooManyRecordsException` (SEC-10,
  CWE-400, CWE-770). Configurable via `withMaxStreamingRecords()`.
- Hardened `FileExternalRefResolver` scheme policy: only `file://` URIs and
  scheme-less relative paths accepted (SEC-01, SEC-24).
- `fromYamlFile()`/`fromJsonFile()` auto-derive
  `allowedRoot = dirname(realpath($specPath))` so external `$ref` confined
  to spec dir (SEC-03). Override via `withExternalRefAllowedRoot()`.
- `assertPathWithinAllowedRoot()` rejects sibling-directory bypass attempts
  (SEC-02, CWE-22, CWE-178).
- `FileExternalRefResolver::resolve()` uses `fopen`+`fstat`+`fread`+`fclose`;
  non-regular files rejected, 10 MB cap via `ExternalRefTooLargeException`+
  `withExternalRefMaxBytes()` (SEC-04, SEC-05, SEC-25).
- `InvalidFormatException::params()` omits raw user values (SEC-06,
  CWE-532); opt-in via `withDetailedErrors(includeSensitive: true)`.
- `MissingSecurityCredentialsError` emits generic caller-safe message by
  default (SEC-07, CWE-209); details via opt-in
  `withSecurityVerboseLogging()`.
- `ExternalRefSecurityException` and `FileExternalRefResolver` exception
  messages no longer interpolate `$ref`/absolute paths (SEC-08).
- `UnresolvableRefException::getMessage()` no longer interpolates
  attacker-controlled `$ref` value (SEC-08-FULL, CWE-209).
- `build()` fails closed when string-loaded spec contains external `$ref`
  and `withExternalRefAllowedRoot()` not called (STRING-SPEC-FAILOPEN).
- Coercion strict by default: boolean rejects unknown strings (SEC-13);
  integer detects overflow (SEC-14); number rejects IEEE-754 precision loss
  (SEC-15). Opt-out via `disableStrictCoercion()`.
- `ResponseHeadersValidator::coerceToNumber` rejects numeric strings losing
  precision (RESPONSE-HEADERS-COERCION).
- `http/bearer` matching case-insensitive per RFC 6750 §2.1; trailing-
  space-only headers rejected (SEC-16).
- `pattern`/`patternProperties`/`propertyNames` capped at
  `RegexValidator::MAX_PATTERN_LENGTH = 1024` bytes (SEC-11, CWE-1333,
  CWE-770). `@` operator replaced with `set_error_handler` (SEC-12).
- `JsonBodyParser`/`JsonParser` reject invalid UTF-8 via
  `mb_check_encoding()` before `json_decode()` (SEC-17, RFC 8259 §8.1).
- `YamlParser::parseContent()` enforces size cap (default 1 MB) and depth
  cap (default 100) (SEC-18). Builder: `withMaxSpecSize()`/`withMaxSpecDepth()`.
- Leap second (`:60`) in `date-time`/`time` restricted to UTC end-of-day
  (SEC-20, SEC-21).

### BREAKING

- `SecurityValidator` throws new `UnsupportedSecuritySchemeException`
  (extends `\RuntimeException`, NOT `ValidationException`) for unsupported
  scheme types (`oauth2`/`openIdConnect`/`http/basic`/`http/digest`/
  `mutualTLS`/unknown) (R4-SEC-010, R4-SPEC-003).
- Strict callback runtime template default flipped to opt-out:
  `{$request.body#/callback_url}` throws
  `UnresolvableCallbackPathException` (SEC-09, CWE-918, CWE-1188). Opt
  back via `disableStrictCallbackRuntimeTemplate()`.
- Exception sanitisation: direct property access (`$e->value`, `$e->ref`,
  `$e->schemeName`, `$e->parameterName`, etc.) throws
  `Error: Cannot access protected property`. Use opt-in getter
  (`$e->value(reveal: true)`).
- R4-SEC-007a/b/c/d message changes break callers string-matching
  `getMessage()` of `PathMismatchException`/`OperationNotFoundException`/
  `UnsupportedMediaTypeException`/`InvalidParameterException`. Use public
  readonly properties or `$e->parameterName(reveal: true)` instead.
- `ValidatorCompiler::generatePatternCheck` emits inlined defensive
  `preg_match` wrapper for `pattern` (R3-SEC-001, ASVS 4.0 V5.3.4,
  CWE-1333, CWE-400). Operators MUST flush `CompilationCache` pool to
  retire cached validators with raw `preg_match`.
- `CompilationCache::generateKey()` adds document context for schemas with
  `$ref`, closing cross-document poisoning (R3-SEC-004). Schemas with `$ref`
  but no `$document` throw `CompilationCacheException` (fail-closed).
  Signature: `(Schema, string, ?OpenApiDocument = null)`.
- `SchemaCache` key incorporates parse-config fingerprint (R4-SEC-008,
  R4-SEC-017). Existing entries cache-miss on first `build()` after upgrade;
  pre-warm at deploy time if cold-start cost matters.
- `format: int32`/`int64` registered via `IntegerRangeValidator`;
  out-of-range values now throw `InvalidFormatException` from the format
  validator BEFORE `maximum`/`minimum` (R3-SPEC-013, C-013).

## [0.5.0] - 2026-07-18

This release focuses on defense-in-depth security hardening, spec-compliance
bug fixes, and internal decomposition of the two largest god classes. The
public API is preserved with two additions: `Operation` now carries resolved
path parameters and the optional `enableStrictCallbackRuntimeTemplate()` mode
fail-closes on unresolvable callback expressions.

### Security
- Closed CWE-94 code injection via type field in `ValidatorCompiler`; generated
  code now rejects class-name injection.
- Added `PregExecutor` wrapper enforcing a defensive `pcre.backtrack_limit`
  (configurable via `withMaxRegexBacktracks()`) to prevent ReDoS on
  attacker-controlled JSON-Schema `pattern` fields.
- Capped request body and streaming payload sizes via `withMaxJsonBodySize()`
  and `withMaxMultipartBodySize()`; streaming depth limit and opt-in
  `enableStrictStreaming()` mode raise `MalformedStreamRecordException` instead
  of silently skipping bad records.
- Replaced xxHash64 cache keys with SHA-256 and `spl_object_id` with `WeakMap`
  to resist hash-collision poisoning and object-id reuse.
- Closed auth fail-open in `CookieValidator` and added opt-in
  `enableStrictCallbackRuntimeTemplate()` to reject unresolvable callback
  runtime expressions (e.g. `{$request.body#/callback_url}`).
- `UnresolvableRefException::getMessage()` no longer discloses the internal
  circular-reference traversal path; the path is preserved in a readonly
  `$internalTrace` property for safe logging.
- `AbstractCompositionalValidator` caps accumulated errors at 20;
  `ContainsValidator` caps validations at 10 000;
  `ArrayLengthValidator` aborts `uniqueItems` after 100 000 entries.
- `XmlBodyParser` enforces a 1 MB default `maxXmlBytes` limit (configurable).
- Added `LogContextSanitizer` to truncate and escape untrusted strings before
  they enter PSR-3 context, mitigating log injection.
- Added builtin `FileExternalRefResolver` with SSRF protection: network
  schemes (`http://`, `https://`, `ftp://`, `ftps://`) are denied by default;
  optional `allowedRoot` defends against path traversal and symlink escapes.

### Added
- `Operation` DTO now exposes resolved `pathParameters`, `operationId`, and
  nullable `schemaOperation` reference; existing call sites keep working via
  defaults.
- `OpenApiValidatorInterface::getDocument()` promoted to the public interface.
- `resolveLinkWithContext()` and `LinkContext` for full OpenAPI 3.2 Runtime
  Expression support (`$url`, `$method`, `$statusCode`, `$request.*`,
  `$response.*`).
- Strict streaming and strict callback runtime template modes (see Security).
- Magic-value enums (`UriScheme`, `ContentMediaType`, `BooleanCoercionValue`,
  `XmlNodeType`) replace stringly-typed comparisons.

### Changed
- Decomposed `Schema` god class into focused constraint sub-DTOs
  (`ArrayConstraints`, `CompositionConstraints`, `NumericConstraints`,
  `ObjectConstraints`, `StringConstraints`).
- Decomposed `OpenApiBuilder` god class into focused builder modules
  (`ComponentsBuilder`, `InfoBuilder`, `PathItemBuilder`, `SchemaBuilder`,
  `SecuritySchemeBuilder`) resolved lazily through `OpenApiBuildContext`.
- Introduced `ValidatorDependencies` DTO and a lazy registry for
  `ValidatorFactory` (open-closed principle).
- `ValidationContext` is now per-request and renamed to avoid the name clash
  with `Error\ValidationContext`.
- Split overloaded boolean-flag builder methods into focused
  `enable*`/`disable*` pairs.
- Consolidated ~660 lines of redundant PHPDoc, dead defensive guards, and
  unreachable branches across `src/`. Public API signatures preserved.

### Performance
- Reuse `SchemaValidator` on the hot path; cached discriminator and `$ref`
  detection.
- `RefResolver` cache, `PathFinder` trie accumulator, and route sort
  precompilation.
- Coercion hot path, `EnumScalarCache` HashSet, and pattern normalization
  caches.
- `PathFinder` migrated from linear scan to a segment-based trie.

### Fixed
- Restored libxml external entity loader and removed global-state mutation
  from `ValidatorPool::reset()` (Swoole / FrankenPHP threaded workers).
- SSE parser now handles no-colon lines per WHATWG and supports dotted
  RFC 6570 variable names.
- Nine JSON Schema / OAS spec compliance bugs: whole-float acceptance for
  `type: integer`, `multipleOf` epsilon equivalence, `const`/`enum` numeric
  equality, `required` / `allowEmptyValue` orthogonality, and more.
- Five composition validator bugs in `allOf` / `anyOf` / `oneOf` / `not`:
  typed-error propagation, `$ref` resolution, and discriminator double
  validation.
- Replaced `@` operator in `PatternValidator` with explicit error handling;
  added `TypeFormatter` utility for typed error messages.
- `SchemaRegistry` semantics refined; `PathFinder` exception naming aligned
  with public API.
- README parity fixes: qualified performance claims, documented PHP runtime
  caveats, and aligned public API examples.

### Deprecated
- `ValidationErrorInterface::getType()` is deprecated in favor of `keyword()`.
  Both return the same value; `getType()` will be removed in 2.0.

### Tests
- Added concurrency tests for Swoole, FrankenPHP threaded, and RoadRunner
  prefork runtimes.
- Added ReDoS, billion-laughs, deep-nesting, and memory-exhaustion security
  regression tests.
- Added cache-invalidation and alternate PSR-7 implementation coverage
  (Guzzle, Laminas Diactoros).

## [0.4.1] - 2026-07-16

### Fixed
- OAS 3.1 type-array null handling: `SchemaValueNormalizer` now recognizes
  `type: [string, "null"]` as nullable in all validator call sites
  (bug A1 from PR #25 analysis).
- `FormatValidator` no longer crashes when `null` is the first element in a
  type-array; it uses the `firstStringType()` helper (bug A2).
- `BodyParser` now parses `application/vnd.api+json` (JSON:API) as JSON
  instead of treating it as a raw string (bug B1).

### Added
- `SchemaValueNormalizer::typeIncludesNull()` helper for OAS 3.1 type-array
  null detection.
- TypeValidator regression tests for `nullableAsType` + type-array interaction.

## [0.4.0] - 2026-07-15

### Security
- Hardened XML parsing against XXE: deny-all libxml entity loader, internal
  error state save/restore, and non-network loading.
- Removed global-state races in date/time and JSON parsing by using
  `DateTimeImmutable`/regex and `JSON_THROW_ON_ERROR` with capped depth,
  eliminating reliance on shared error globals.
- Hardened request and stream parsing: case-insensitive `Content-Type`,
  rejection of `q-value=0`, multipart boundary extraction from the header,
  UTF-8 BOM stripping, line/record length limits, query/cookie pair-count
  limits, and untrusted JSON depth limits.
- Added optional lock support to `ValidatorPool` for Swoole/coroutine runtimes,
  and converted static regex and path caches to instance-bound LRU caches with
  `reset()` clearing.
- Prevented injection and traversal: `preg_quote` in path-template regex,
  `var_export`/class-name validation in compiled validator codegen, correct
  RFC 6901 escaping in JSON Pointer resolution, and discriminator mapping by
  schema name.
- Hardened schema coercion and numeric handling: strict integer/number/boolean
  coercion, whole-float acceptance for `type: integer`, numeric equality in
  `const`/`enum`, `multipleOf` greater-than-zero and zero guards, and
  orthogonal `required`/`allowEmptyValue` handling.
- Bounded memory and error output: capped additional-property errors, O(n)
  hash-map deduplication for `uniqueItems`, and bounded streaming buffers.
- Replaced MD5 with xxHash64 for cache key generation.

### Added
- Opt-in security validation for `http/bearer` and `apiKey` (header/query/cookie)
  schemes via `enableSecurityValidation()`.
- Opt-in server path resolution via `enableServerPathResolution()`; supports
  server variables, relative URLs, and longest-prefix matching.
- Full Runtime Expression support in links: `resolveLinkWithContext()` with
  `LinkContext` for `$response.header`, `$response.query`, `$url`, `$method`,
  and `$statusCode`.
- `validateWebhook()` and `validateCallback()` public methods for webhook and
  callback request validation.
- `ContentEncodingValidator` and `ContentMediaTypeValidator` for base64 and
  JSON/XML/text media types.
- `ExampleValidator` for optional, non-blocking validation of `example`/`examples`.
- `ValidatorCompiler` guards against unsupported keywords and supports
  `readOnly`/`writeOnly` validation.
- OpenAPI 3.1+ `nullable` conversion to `type: [..., "null"]` while preserving
  OpenAPI 3.0 boolean behavior.
- Server URL validation and strict format mode (`enableStrictFormats()`) with
  PSR-3 warnings.
- Runtime deprecation warnings and version-aware `exclusiveMinimum`/
  `exclusiveMaximum` handling.
- Validation lifecycle events (`ValidationStartedEvent`,
  `ValidationFinishedEvent`, `ValidationErrorEvent`) and PSR-3 logger
  integration.
- `PathItem::getOperation()` for consolidated HTTP method routing.
- `ValidatorPool` LRU cache with configurable size and `clear()` for hot reload.
- `ExternalRefResolverInterface` for custom external `$ref` resolution.
- `SchemaRegistry::getOrFail()` for fail-fast schema lookups.
- Boolean schema support (`type: boolean`).

### Changed
- Refactored `OpenApiValidator` into a facade backed by specialized handlers;
  public interface preserved.
- `OpenApiValidatorBuilder` now returns `OpenApiValidatorInterface` and delegates
  to a shared internal `with()` method.
- `BuilderException` now extends `RuntimeException`.
- Added `Schema::withOverrides()` for non-destructive schema overrides.
- Extracted `CompilationCacheInterface` and `RequestValidatorInterface`.
- Coercion and security validation now use internal context DTOs.
- Event dispatching extracted into a shared trait.
- Added `final` to concrete classes and updated PHPDoc/version annotations.

### Performance
- Cached regex patterns in `PathRegexCache` and `RegexValidator` with
  instance-bound LRU eviction.
- Shared `ValidatorRegistry` and `StatelessValidatorRegistry`; cached enum checks
  and error merging.
- Optimized `ValidatorPool::touch()` to O(1) and rewrote `RefResolver::navigate()`
  iteratively.
- `PathFinder` uses a segment-based trie instead of linear scan;
  `PathParser::tryMatchPath()` avoids exception-based control flow.
- Made `BreadcrumbManager` and `ValidationContext` mutable for the hot
  validation path.
- Moved stateless validators and body parsers to constructors for reuse.
- Streaming parsers read PSR-7 streams in bounded chunks.
- Cache schema hashes, placeholder counts, and compiled validator results once
  per call.

### Fixed
- `SchemaValidatorAdapter` now passes `ErrorValidationContext` so
  `disableNullableAsType()` and `withEmptyArrayStrategy()` are honored for plain
  schemas.
- Parameter validators now propagate `nullableAsType` and `emptyArrayStrategy`
  through `ParameterValidationConfig`.
- `PrefixItemsValidator` no longer re-validates remaining items; `ContainsValidator`
  was merged with range handling.
- `ResponseTypeCoercer` preserves null properties and additional properties;
  `coerceToArray` returns non-arrays as-is.
- `NotValidator`, `OneOfValidator`, and `PropertiesValidator` propagate typed
  errors into `ValidationException`.
- `AdditionalPropertiesValidator` emits `AdditionalPropertyError` instead of
  `UnevaluatedPropertyError`, and caps error count.
- `JsonBodyParser` returns `null` for empty bodies; `CookieValidator` preserves
  flag-style cookies; `QueryParser` handles RFC 6570 form/explode semantics,
  literal key names, and nesting limits.
- `ParameterDeserializer` adds deepObject style support and `FormBodyParser`
  delegates to `QueryParser`.
- `EmailValidator` requires a TLD, `HostnameValidator` supports IDN and label
  rules, `ByteValidator` accepts URL-safe base64, `UriValidator` uses RFC 3986
  scheme/port checks, and `UuidValidator` supports RFC 9562 v6/v7/v8 and nil
  UUID.
- `XmlBodyParser` preserves attributes, repeated elements, empty elements, and
  mixed content.
- `ResponseHeadersValidator` fails closed on unknown boolean values and coerces
  empty header values to objects when requested.
- `ResponseValidationHandler` falls back to webhooks for webhook response cycles.
- Streaming: SSE handles CRLF/CR/LF line endings, default event name, multi-line
  data, and retry; NDJSON supports CRLF; BOM stripped from all JSON/XML streams.
- `ValidatorCompiler` now matches runtime behavior for `type: integer` and
  `multipleOf`, caches schema hashes once, guards TTL, and wraps JSON exceptions.
- `RefResolver` enforces recursion depth limits, resolves parameters/responses,
  and handles external refs via optional `ExternalRefResolverInterface`.
- `SchemaValidatorAdapter` routes discriminator and `$ref` schemas through
  `SchemaValidatorWithContext`.
- `$ref` resolution in `allOf`/`oneOf` compositions and callback runtime
  expressions fixed.
- Wildcard media types expanded for request bodies; Content-Type matching is
  case-insensitive.
- Query parameters treat `required` and `allowEmptyValue` as orthogonal.
- Added required request body check and eliminated discriminator double
  validation.
- `LinkResolver` handles out-of-bounds list indices in link resolution.

## [0.3.3] - 2026-05-16

### Changed
- Set `symfony/yaml` requirement to `^7.0`.

[Unreleased]: https://github.com/duyler/openapi/compare/0.6.0...HEAD
[0.6.0]: https://github.com/duyler/openapi/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/duyler/openapi/compare/0.4.1...0.5.0
[0.4.1]: https://github.com/duyler/openapi/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/duyler/openapi/compare/0.3.3...0.4.0
[0.3.3]: https://github.com/duyler/openapi/compare/0.3.2...0.3.3
