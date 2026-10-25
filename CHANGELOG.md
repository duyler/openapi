# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.9.0]

### Added

- Vendored the canonical draft 2020-12 metaschema (`https://json-schema.org/draft/2020-12/schema`
  and its seven `meta/*` documents) byte-for-byte under `tests/Fixture/JsonSchemaTestSuite/metaschema/`
  with source commit pin, per-file SHA-256 checksums and the upstream license notice. The JSON Schema
  Test Suite runner registers these documents by their canonical retrieval URIs, so suite cases that
  reference the metaschema (for example `ref.json` "remote ref, containing refs itself") now validate
  against the real 2020-12 metaschema instead of failing scheme allowlisting; product file-resolution
  allowlisting is unchanged.

- Streaming multipart request validation (3.2 §4.15.4.8): a `multipart/*` request is now
  processed part by part straight from the PSR-7 stream instead of being read into a single
  buffer. `RequestValidator` routes multipart requests through the new
  `RequestBodyValidatorInterface::validateStream()` entry point (implementors of the
  interface must add the method); the internal `StreamingMultipartDetector` decides the
  routing by media type (case-insensitive, matching content-type negotiation), and requests
  whose matched Media Type Object does not declare `prefixEncoding`/`itemEncoding` fall back
  to the previous buffered pipeline after a limit-checked read, so `multipart/form-data` and
  named-part validation keep their exact former behavior. Ordered multipart bodies are split
  incrementally by the CRLF-anchored boundary framing of RFC 2046: a delimiter candidate must
  sit at a line start and be followed by `--` (close) or transport padding plus CRLF per
  RFC 2046 §5.1.1 — a close candidate followed by any other byte (for example a fake
  `--boundary--garbage` sequence inside binary part content) is treated as part content, not
  as the end of the body; a bare close delimiter without the trailing CRLF is still accepted
  at the true end of the stream, and boundary-token prefixes inside part content no longer
  create phantom parts. Each completed part is encoded-asserted, coerced (when coercion is
  enabled, per part via `RequestBodyCoercer` + `TypeGuarantor`) and validated against
  `prefixItems[i] ?? itemSchema ?? schema.items` — the same schema also drives the part value
  shaping and part-level example warnings — item-level results are available as parts arrive,
  while whole-array keywords (for example `minItems`) are not validated on the streaming
  ordered multipart path. Existing limits are reused: more than 1000 parts raise
  `Validator\Exception\MultipartBodyTooLargeException`, a single part larger than
  `withMaxMultipartBodySize()` raises `Validator\Exception\BodyTooLargeException`, and the
  nested-multipart recursion applies the same `MultipartNestingDepthException` depth cap
  configured for the buffered resolver and applier. Known limitation: on the streaming
  ordered multipart path a whitespace-only body against `required: true` is not rejected
  (`MissingRequestBodyException` stays a buffered-path behavior), because rejecting it would
  require buffering; an empty (zero-byte) stream is still rejected.
- Byte-range parts (3.2 §4.15.4.9, RFC 9110 §14.5/§14.6): a part `Content-Range` header
  declared in `encoding.headers` is parsed strictly into the `ByteRange` value object
  (`first`/`last`/`complete`, nullable for `*`) by `ByteRangePartParser`. The parser accepts
  exactly `bytes <first>-<last>/<complete>`, `bytes <first>-<last>/*` and `bytes */<complete>`;
  a missing `/complete` part, a missing `bytes ` prefix, trailing garbage or a trailing line
  break, and a range whose last byte position precedes the first are rejected with
  `Validator\Exception\InvalidContentRangeException` (keyword `format`).
- XML Object semantics for XML request bodies (3.2 §4.26): an XML body validated against a schema
  is now mapped through the XML Object model instead of generic name-based parsing. Element and
  attribute nodes are matched by the XML Object `name`/`namespace`/`prefix` fields (namespace URI
  identity, per §4.26.4), `nodeType` (`element`/`attribute`/`text`/`cdata`/`none`) is honored
  together with the deprecated `attribute`/`wrapped` boolean fields, `nodeType: none` schemas
  inline their subschemas under the parent node, arrays map as unwrapped item sequences or
  `wrapped`/`nodeType: element` wrappers, and named `prefixItems` map positionally by node name.
  §4.26.5 null handling: an element carrying `xsi:nil="true"` (detected namespace-awarely against
  `http://www.w3.org/2001/XMLSchema-instance`) maps to `null`; an omitted attribute maps to `null`
  when the property schema allows `null` (type includes `"null"`) and the property is omitted
  otherwise; an absent element node always means an absent property (`null` is never inferred from
  a missing node). The mapping is schema-driven projection only — it is not XML Schema validation
  (ADR, Epoch C boundary). Schemas whose shape cannot determine a mapping (free-form objects,
  unnamed array items) keep the generic pass-through shape; a schema carrying `$ref`/`$dynamicRef`
  defaults to `nodeType: none` (§4.26.1), so a property whose `$ref` stays unresolved contributes
  no property to the mapped value. A declared wrapper that does not match the document is reported
  as a `ValidationWarningEvent` (carrying `propertyName` and `propertyPath`) and the node passes
  through unmodified instead of failing. The mapping applies to `application/xml`/
  `text/xml` request bodies (the media type schema is now passed into `BodyParser::parse()` and
  `XmlBodyParser::parse()` as an optional parameter — existing signatures stay source-compatible)
  and to multipart XML leaves (`MultipartInstanceResolver` feeds the part schema in). All
  simplexml operations keep running inside `LibxmlSecuredContext`, and the 1 MiB XML byte cap
  (`DEFAULT_MAX_XML_BYTES`) plus `BodyTooLargeException` apply unchanged, including multipart
  leaves.
- Built-in security scheme handlers for HTTP Basic and HTTP Digest authentication
  (RFC 7617/7616): `http/basic` and `http/digest` schemes are now dispatched to dedicated
  built-in handlers in the `Validator\Security` registry instead of raising
  `UnsupportedSecuritySchemeException`. Both handlers stay at the presence-and-format depth of
  the bearer handler: the `Authorization` header must carry the `Basic`/`Digest` prefix
  (case-insensitive) followed by a well-formed token (`Basic`, exactly one space and a
  base64/base64url token per RFC 7617) or a well-formed `key=value` parameter list (`Digest`,
  comma-separated with optional whitespace, values as RFC 7230 tokens or quoted strings with
  backslash escapes per RFC 7616). Credential values are never decoded, compared or reported:
  failures raise the generic missing-credentials error with scheme type `http/basic` or
  `http/digest` and location `Authorization header`, and the verbose log entry keeps the exact
  bearer handler shape (debug level, scheme name, scheme type and location only). The built-in
  registry order is basic, digest, bearer, api key, so a custom handler registered through
  `with()` still wins over every built-in one; `http/bearer` dispatch is unchanged.
- Built-in security scheme handlers for mutual TLS, OAuth2 and OpenID Connect security schemes
  (3.2 §4.27.5, §4.27.2, §4.27.3): `mutualTLS`, `oauth2` and `openIdConnect` schemes are now
  dispatched to dedicated built-in handlers in the `Validator\Security` registry instead of
  raising `UnsupportedSecuritySchemeException`. The mTLS handler is a presence heuristic over
  request headers only (`SSL_CLIENT_CERT`, `X-SSL-CERT`, `X-Client-Cert`, case-insensitive):
  any of them present means success plus a `warning`-level log entry on every triggering
  validation (client-supplied `X-SSL-*` headers are spoofable when a reverse proxy does not
  rewrite them — presence is trusted proxy configuration, never proof of authentication;
  `getServerParams()` is intentionally not consulted), and no candidate header means the
  generic missing-credentials error. The oauth2 and openIdConnect handlers are Bearer presence
  checks (case-insensitive `Authorization: Bearer <token>` pattern shared with the
  `http/bearer` validator): a missing or malformed header raises the generic
  missing-credentials error with scheme type `oauth2`/`openIdConnect`, and a valid one succeeds
  plus a debug log entry carrying the scheme's declared scopes — the union of the `scopes` maps
  of all five OAuth flows (`implicit`, `password`, `clientCredentials`, `authorizationCode`,
  `deviceAuthorization`, first flow wins on key collisions) merged with the flat scheme-level
  `scopes` map when present. No token introspection, no network calls, and no token or
  certificate values in logs — the depth stays at presence and format. The oauth2 handler
  format-checks `oauth2MetadataUrl` when present and the openIdConnect handler format-checks
  `openIdConnectUrl` when present (invalid absolute URI raises the generic
  missing-credentials error at request time; the URI predicate and message are shared with the
  parse-time validation in `SecuritySchemeBuilder`, which keeps raising
  `InvalidSchemaException` while parsing the document). The built-in registry order is basic,
  digest, bearer, api key, mutual TLS, oauth2, open ID connect, so a custom handler registered
  through `with()` still wins over every built-in one; the `http/bearer` fallback (bearer
  pattern applied to every `http` scheme without its own handler) remains the dispatch path
  for future `http` subschemes.
- `OpenApiValidatorBuilder::withSecuritySchemeHandler(SecuritySchemeHandlerInterface $handler): self`
  (fluent, repeatable): registers a custom security scheme handler through the public builder
  surface, closing breaking#10 — user-defined security schemes (for example an introspection
  stub for `http/bearer` or a custom apiKey location) are now implementable without touching
  package internals. The handler is prepended ahead of the current handler list in
  `Validator\Security\SecuritySchemeHandlerRegistry`, and the first handler whose `supports()`
  returns true wins the scheme lookup, so a user handler overrides every built-in one for the
  schemes it claims (basic, digest, bearer, api key, mutual TLS, oauth2, openIdConnect);
  registering two competing handlers makes the last `with()` call win (LIFO order). The merged
  registry (user handlers first, built-in set behind them) is shared by request, webhook and
  callback security validation; the OR/AND security requirement semantics are unchanged.
- `SchemeCredentialReporter` is promoted from `Validator\Security\Internal` to the public
  `Validator\Security` namespace: the reporter type appears in the public
  `SecuritySchemeHandlerInterface::validate()` signature, so external handler implementations
  must be able to name it. The class keeps its shape (public `schemeName`,
  `reportMissing(schemeType, location)`), its caller-safe `MissingSecurityCredentialsError`
  output and the sanitized verbose logging; the `@internal` marker is removed.

### Changed

- Core instance semantics for schema validation (draft 2020-12 conformance; JSON Schema Test Suite
  sections `items`, `ref`, `optional/refOfUnknownKeyword`, `unevaluatedItems`, `unevaluatedProperties`
  — 318 cases — are now active and green):
  - JSON Pointer fragments now percent-decode before `~0`/`~1` unescaping per RFC 3986/RFC 6901
    (`%25` → `%`, `%22` → `"`), so definitions like `percent%field` referenced as
    `#/$defs/percent%25field` resolve; `$ref: "#"` inside a subschema resolves to the root of the
    enclosing schema resource.
  - Local pointer references (`#/...`) that miss the OpenAPI envelope now resolve against the root of
    the enclosing schema resource (`#/properties/foo`, `#/prefixItems/0`) and against unknown-keyword
    values kept verbatim in schema extras (`#/unknown-keyword`, `#/properties/foo/unknown-keyword`)
    and against list entries of the 2020-12 `examples` keyword (`#/examples/0`); references into
    documents registered via `withDocument()` resolve their local pointers against that document's
    resource. Envelope-rooted pointers (`#/components/...`) keep their previous fast path and do not
    touch the resource registry.
  - `items`/`prefixItems` apply to JSON arrays only: JSON objects (including JavaScript-style
    pseudo-arrays such as `{"0": ..., "length": 1}`) no longer feed their keys into item indexes, and
    `null` items validate against subschemas that do not restrict `type` (2020-12 semantics; OAS 3.0
    `nullableRequiresType` profiles keep their behavior).
  - The 2020-12 `examples` keyword accepts the JSON Schema list form alongside the OAS map form
    (parser, sibling merge, serializer round-trip and example collection).
  - `unevaluatedProperties`/`unevaluatedItems` now track evaluated names and indexes per instance
    location: an inner property or item does not satisfy an `unevaluated` keyword at another
    location; `unevaluatedProperties: true` / `unevaluatedItems: true` evaluate everything they
    cover so nested `allOf`-scoped guards compose; a schema-typed `unevaluated*` target marks the
    properties/items it applies to as evaluated; and sibling applicator keywords of a `$ref` schema
    (its `properties`) are applied as a separate sibling application whose annotations are invisible
    inside the `$ref` target (a `$ref` creates a new evaluation scope), while non-evaluation sibling
    keywords (`nullable`, bounds, `required`) keep merging into the resolved target as before.
- Suite runner: the five sections above are activated (1180 suite cases skipped overall, 1094 of
  them held by `FS-40` per the pool decomposition attribution).

- `UnsupportedSecuritySchemeException` message wording: with the full built-in handler set
  (apiKey, http/basic, http/bearer, http/digest, mutualTLS, oauth2, openIdConnect) the message
  no longer claims that the package "validates apiKey and http/bearer only" — it now states the
  fact that no built-in handler exists for the scheme type/subscheme and points to the public
  registration entry point: a custom handler must be registered via
  `OpenApiValidatorBuilder::withSecuritySchemeHandler()` to validate it. The exception is now
  raised only for types or `http` subschemes without a built-in handler (for example
  `http/negotiate` or an entirely unknown type); scheme name, scheme type and `httpScheme`
  properties are unchanged.
- XML empty-element parse semantics (3.2 §4.26.5): an empty element without `xsi:nil` — root
  (`<empty/>`), child (`<nickname/>`) or repeated (`<item/><item/>`) — now maps to an empty string
  `''` instead of `null` in generic XML parsing; `null` is now reserved for `xsi:nil="true"`
  elements only (detected against the XML Schema-instance namespace URI regardless of the bound
  prefix, in both generic and schema-driven paths). In the schema-driven mapping an empty element
  is typed by its schema: when the element type allows `object` or `array` it maps to an empty
  object/list (`[]`; for a union the composite branch wins over scalar branches), and to `''` for
  scalar types — the §4.26.6.8 `productNoNulls` fixture
  (`<product count="42"><description>Thing</description><related></related></product>` →
  `related: {}`) now validates without errors. Whitespace-only leaf text stays untrimmed
  (`<root>  </root>` → `'  '`), matching child leaf values, while `text`/`cdata` node mapping and
  mixed-content `#text` stay trimmed. Root arrays without named items now collect children across
  all namespaces, not only the default one. This is a deliberate breaking change of the previously
  pinned `empty element → null` behavior: per §4.26.5/§4.26.6.8 an empty element is a value
  (`''`/`{}`), not `null` — and only `<related xsi:nil="true"/>` denotes `null`.
- Multi-document OpenAPI descriptions (3.2 §4.1.2): `OpenApiValidatorBuilder::withDocument(string $retrievalUri,
  string $content)` registers user-supplied documents by intended retrieval URI. Content is size-capped with the
  same limit as `FileExternalRefResolver` (`withExternalRefMaxBytes()` and `DEFAULT_MAX_REF_BYTES` apply, re-checked
  against the final limit at `build()`) and is parsed in full at registration time; parse failures raise
  `DocumentParseException` carrying the retrieval URI without exposing document content. External `$ref` resolution
  order is now: document repository (exact match, then base-URI combination against the current document `$self`)
  → `file://` jail resolver → `UnresolvableRefException`. External `$ref` values inside the main spec and inside
  registered documents must either be satisfied by the repository or require `externalRefAllowedRoot`; the
  confinement verdict cache key includes the repository fingerprint.
- Schema resource identification fields (`$id`, `$anchor`, `$dynamicAnchor`, `$dynamicRef`, `$comment`,
  `$vocabulary`, `$defs`) and unknown schema keywords are preserved through parse/serialize round-trip; they
  were previously silently dropped on load. Unknown keywords keep their values verbatim (vendor extensions,
  typos, `$recursiveRef` included), so re-serializing a schema reproduces the source document.
- `DialectProfile` DTO (additive public API): the OpenAPI 3.0/3.1 dialect profile is derived once per document
  from the `openapi` version (`DialectProfile::fromOpenApiVersion()`, with `forOpenApi30()`/`forOpenApi31()`
  factories) and carried through ref resolution, validation context (including branch forks) and the schema
  parser build path (`RefResolver::dialectProfile()` exposes it). Behavior is unchanged in this release: no
  code path consumes the profile flags yet, and the stateless validation path keeps the 3.1 default.
- `dependentRequired` keyword with full cascade support and the legacy draft-07
  `dependencies` keyword translated at parse time (list form to dependentRequired,
  schema/boolean form to dependentSchemas, empty list as vacuum), registered in the known
  keyword set.
- Custom schema keyword validation (public part of the keyword-validator registry):
  `OpenApiValidatorBuilder::withKeywordValidator(string $keyword, KeywordValidatorInterface $validator)`
  registers a validator for a user-defined schema keyword (calls accumulate; re-registering
  a keyword replaces its validator while keeping its dispatch slot — fully for
  stateless-dispatched keywords only, since built-in `items`/`properties`/`oneOf`
  are additionally dispatched by the context path and cannot be fully replaced).
  Keyword values are read
  from the schema `extras` produced by the parser — custom keywords are never added to the
  schema model. Registered keywords dispatch after the whole built-in set (including
  `unevaluatedItems`/`unevaluatedProperties`), so applicator-style custom keywords cannot
  place themselves ahead of them; a keyword without a registration stays an extras
  annotation and does not affect the validation outcome.
- Format assertion mode switch: `OpenApiValidatorBuilder::withFormatAssertionMode(FormatAssertionMode $mode)`
  selects between `FormatAssertionMode::Assertion` (the default; format keywords are
  asserted, behavior unchanged) and `FormatAssertionMode::Annotation` (format keywords are
  only collected as validation annotations and never asserted, matching the JSON Schema
  2020-12 annotation default). In both modes the applied `format` keyword is recorded in
  the run annotations. `strictFormats` keeps its semantics: an unknown format token raises
  `InvalidFormatException` in assertion mode when strict formats are enabled; annotation
  mode never raises.
- Nested encoding application for multipart request bodies (3.2 §4.15.2): Encoding Objects
  reached through the Media Type Object's `prefixEncoding`/`itemEncoding`/`encoding` fields
  are now applied one level deep to nested parts. A nested part's resolved Encoding Object
  checks the part's `Content-Type` against `contentType` (string or list form, `type/*`
  wildcards included, parameters and case ignored), enforces required `headers` on the part
  (`Content-Type` inside `encoding.headers` is ignored per §4.15.1.1), and, when the part is
  itself a multipart body, wire-splits it once by its own boundary to apply the nested
  fields to the inner parts. Mismatches raise `Validator\Exception\PartContentTypeMismatchError`
  (keyword `prefixEncoding`, `itemEncoding` or `encoding`) and a missing required part header
  raises `Validator\Exception\MissingPartHeaderError`. Parts without a `Content-Type` header
  are not content-type asserted, deeper than one level of nesting is passed through untouched,
  and schema-level recursion into nested multipart values stays out of scope; the wire parser
  remains a boundary splitter with no knowledge of the Encoding model.
- Ordered (unnamed) multipart request bodies (3.2 §4.15.4.6, §4.15.4.7): a `multipart/*` Media
  Type Object carrying `prefixEncoding`/`itemEncoding` together with an array schema (or
  `itemSchema`) is validated positionally — parts map to `prefixItems`/`items` and to
  `prefixEncoding`/`itemEncoding` by index, `Content-Disposition` names do not participate,
  an instance shorter than `prefixEncoding` ignores the surplus Encoding Objects, and part
  values enter the JSON data model (JSON-decoded when the part's effective content type is
  JSON, raw text otherwise) so the schema validator applies per-position assertions.
  `encoding` combined with `prefixEncoding`/`itemEncoding` raises
  `Validator\Exception\InvalidEncodingConfigurationException`, as does positional encoding
  without an array schema or `itemSchema` (§4.14.1, §4.14.5.2). Part `headers` declared in
  Encoding Objects now carry full Header Object semantics: a referenced header
  (`$ref` into `#/components/headers/...`) is resolved from the document's components, a
  required header missing on its part raises `MissingPartHeaderError`, and a present header
  value is validated against the header's `schema` with the same coercion rules used for
  response headers. Media type keys with parameters (for example
  `multipart/related; type=text/html`) now match requests by media type proper. The
  effective content type of a part follows the §4.15.1.1 default table (header, then
  `encoding.contentType`, then the schema-type default) and is exposed for reuse by nested
  multipart handling.
- Nested `multipart/mixed` request bodies (3.2 §4.15.4.10): a part whose effective content
  type is `multipart/*` is now parsed recursively into a typed part tree, so nested parts
  enter the JSON data model one level deeper (nested array schemas map `prefixItems`/`items`
  by position, nested object schemas map `Content-Disposition` names to properties, and the
  §4.15.1.1 default content-type table applies to nested leaves the same way, including
  JSON decoding of JSON-typed leaves and XML parsing of `application/xml`/`text/xml`
  leaves). A declared `contentType` wildcard (`type/*`, `*/*`) on a part without a
  `Content-Type` header JSON-decodes the part only when its schema position expects an
  object or array. Recursion is capped (one nested level below the outer body); a payload
  that nests deeper raises `Validator\Exception\MultipartNestingDepthException` instead of
  recursing unbounded. Schema-less bodies keep the previous pass-through behavior and the
  wire parser remains a boundary splitter with no knowledge of the Encoding model.

### Changed

- Part headers declared in Encoding Objects with a content-based Header Object
  (`content.<media type>.schema`) are now value-validated with the same coercion rules used
  for `schema`-based headers; previously such headers were checked for presence only. As a
  consequence, a value that fails the media type's schema (for example a `format: uri`
  violation on `Content-Location`) is rejected, and a declared part `Content-Range` header
  is additionally parsed strictly per RFC 9110 (see Added).
- `multipart/form-data` requests are wire-parsed once per validation: the encoding applier
  now receives the already-split parts instead of re-splitting the raw body, removing the
  duplicate multipart parse from the named-part pipeline.
- Every `multipart/*` request now enters validation through the streaming entry point;
  requests without positional encodings behave exactly as before (the body is read within
  the existing body-size limits and validated by the unchanged buffered pipeline), while
  positional (ordered) multipart bodies are validated part by part without buffering the
  whole payload.

- `multipart/form-data` request bodies with an object schema declaring `properties` now
  populate the object from the parts' `Content-Disposition` names (3.2 §4.15.4.5, RFC7578
  §4.3): a part named like a property fills that property, several parts sharing one name
  are collected into the property's array (a single part of an array-typed property
  becomes a one-item array), and duplicate names against a scalar property produce a list
  that fails the property's type check instead of silently dropping parts. Requests that
  previously failed validation with a `required` error while sending a matching named part
  now validate. Object schemas without `properties` keep the previous raw part-list
  behavior, and non-form-data multipart subtypes are unaffected.
- `Encoding` Object `contentType` now accepts the OAS 3.2 §4.15.4.1 list form:
  the wire value may be a single media type string or an array of media type
  strings (for example `["text/css", "image/*"]`), `Schema\Model\Encoding::$contentType`
  carries the union `string|list<string>|null`, and serialization emits back the
  original wire shape, so round-tripping preserves string form and list form
  verbatim. An empty array, an array containing non-string members, or a
  non-string non-array value raises `Schema\Exception\InvalidContentTypeException`
  at parse time. Streaming consumers read the item parser media type from the
  first list element (`ExternalMediaType::primary()`) and use the full list only
  for media type matching, and `ExternalMediaType::isDefined()` applies any-of
  semantics to the list. Code that type-assumes `string` on
  `Encoding::$contentType` must handle the list form.
- Unknown schema keywords are rejected at build time by default (strict
  keywords): a keyword outside the legal set (the known JSON Schema 2020-12 and
  OAS base-vocabulary keywords, vendor `x-` extensions, the legacy
  `$recursiveRef`/`$recursiveAnchor` keys, keywords registered through
  `withKeywordValidator()`, and the explicit allow-list) raises
  `InvalidSchemaException` naming the keyword while the document is parsed, so
  typos such as `requird` fail fast instead of silently degrading to unused
  extras. `disableStrictKeywords()` restores the previous annotation mode
  (unknown keywords stay extras and produce annotations), and
  `withAllowedUnknownKeywords(string ...$keywords)` legalizes individual
  keywords point-wise; both compose with the rest of the builder. The check is
  a single traversal of the already-parsed document, applied to the main spec
  only (documents registered via `withDocument()` are not guarded).
- Response bodies are now validated fail-closed against the declared
  Content-Type: a response media type matching none of the operation's
  declared response media types (exact, `type/*` or `*/*`) raises
  `UnsupportedMediaTypeException` on both the buffered and the streaming
  validation path instead of silently skipping body validation. The
  exception message lists only the declared media types and never echoes
  the offending Content-Type, matching the request-path contract.
  `OpenApiValidatorBuilder::withLenientResponses()` restores the previous
  permissive behavior and makes every skip observable: each skipped
  response body dispatches an `UnsupportedResponseMediaTypeWarningEvent` event
  (PSR-14) carrying the unmatched media type.
- `$dynamicRef` is now evaluated with the full JSON Schema 2020-12 dynamic
  reference semantics (core §8.2.3.2, Appendix C) instead of being ignored.
  Resolution happens at every encounter during validation, never at load time:
  a plain-name fragment bookended by a matching `$dynamicAnchor` in the same
  schema resource is replaced by the outermost matching `$dynamicAnchor` in the
  dynamic scope; otherwise the keyword behaves like `$ref` (with `$anchor`
  fallback). Schemas with `$dynamicRef` therefore validate by the recursive
  semantics, so results can differ from the previous silently-ignoring build.
  The dynamic scope tracks every `$id`-identified schema resource crossed by
  validation, including `$ref` chain resources, and cyclic dynamic references
  terminate instead of overflowing the stack: re-entering the same dynamic
  reference target at the same data path within one run is skipped as already
  evaluated, while depth-growing recursion (including branch forks that reset
  the per-branch frame, e.g. not/oneOf/if-then-else) is stopped by the depth
  limit (SchemaDepthExceededException, MAX_DEPTH=64). Verdicts on
  spec-undefined infinitely recursive schemas are implementation-defined and
  pinned by unit tests. `#` resolves to the root resource of its document,
  and a bare-URI reference to a registered document whose root carries a
  different `$id` resolves to that registered root.
- `$ref` chains now apply the sibling keywords of every intermediate chain resource at each hop instead of dropping
  them; entry resolution returns the entry schema with its `$ref` intact and the chain is followed at validation time.
- `RefResolverInterface` gained `resolveSchemaEntry()`,
  `resolveSchemaWithOverrideAndChain()`, `scopeBaseUri()`,
  `findDynamicAnchorInScope()`, `registeredDocuments()` and an optional
  `$baseUri` parameter on `resolveSchemaWithOverride()`. External
  implementations of the interface must add these members to stay compatible.
- `$ref` fragments are resolved by their JSON Schema 2020-12 kind. A fragment
  starting with `/` is treated as a JSON Pointer (RFC 6901 `~0`/`~1` decoding
  stays localized to pointer segments); any other fragment is a plain-name
  fragment resolved through the `$anchor`/`$dynamicAnchor` indexes only, with
  NCName validation and without tilde decoding. Pointer fragments rooted at
  `$defs`, URI references whose resource matches an in-document `$id`, and
  bare plain-name fragments resolve through the document resource registry
  first and fall back to raw document navigation afterwards;
  `UnresolvableRefException` is raised only after both are exhausted. Specs
  containing bare-fragment `$ref` values (for example `#myAnchor`) no longer
  require `externalRefAllowedRoot`, because a bare fragment never leaves the
  document. Relative URI references resolve against the `$id` of the schema
  carrying them, then against the document `$self`.
- `Validator\Error\ValidationContext` and `Validator\Validation\ValidationStack`
  are now named-constructor-only services: their constructors are private and
  the only creation entry points are `ValidationContext::fromInit()` and
  `ValidationStack::fromGroup()`. Schema model classes keep public
  constructors.
- Documents declaring `$vocabulary` (core §8.1.2) are now checked once per
  document at build time. A vocabulary URI outside the supported set
  (2020-12 `core`, `applicator`, `unevaluated`, `validation`, `meta-data`,
  `format-annotation`, `format-assertion`, `content`, and the OAS 3.1 base
  vocabulary) with the value `true` refuses the build with
  `Schema\Exception\VocabularyNotSupportedException` carrying the
  unsupported URI (the same document previously assembled without a
  diagnostic). The same URI with the value `false` builds and dispatches an
  `Event\VocabularyDisabledWarningEvent`; schemas keeping legacy
  `$recursiveRef`/`$recursiveAnchor` keywords in extras build and dispatch an
  `Event\LegacyKeywordWarningEvent` pointing at the `$dynamicRef`/
  `$dynamicAnchor` replacements. URI comparison is byte-wise, so differently
  cased or normalized URIs are treated as unsupported. Registered
  `withDocument()` resources stay outside the guard: cross-draft resources may
  legitimately declare another dialect's vocabularies.
- OpenAPI 3.0 documents now refuse schemas whose `type` keyword is an array
  (OAS 3.0.3 §4.7.24.1 requires a string): `build()` raises
  `Schema\Exception\TypeArrayNotSupportedException` where the same document
  previously assembled with 3.1 union semantics. The message carries no
  document content. OpenAPI 3.1+ documents keep accepting `type` arrays,
  including the `'null'` member, and `nullable` folding is unchanged.
- OpenAPI 3.0 documents now ignore schema keywords sitting next to `$ref`
  (JSON Reference draft-03 §3: any members other than `$ref` SHALL be
  ignored), at the referenced-property entry point and on every intermediate
  `$ref` chain carrier alike: the resolved target is validated as-is instead
  of being merged with the stub. A 3.0 `nullable: true` sibling of `$ref`
  therefore no longer widens the target, so `null` values for such
  properties are rejected where they previously passed. OpenAPI 3.1+
  documents keep applying `$ref` siblings per JSON Schema 2020-12
  §8.2.3.1, including per-hop merging along `$ref` chains.
- OpenAPI 3.0 documents now treat `nullable: true` as an extension of an
  explicit `type` in the same Schema Object (OAS 3.0.3 §4.7.24.2): a
  `nullable` Schema Object without a `type` no longer makes `null`
  acceptable, so `null` values for such properties are rejected where they
  previously passed. `nullable: true` beside an explicit `type` keeps
  accepting `null`, deferred decisions (`$ref`, `allOf`, `anyOf`, `oneOf`
  branches) keep deferring, and a `not` sub-schema is now matched against a
  `null` instance by the sub-schema's own keywords instead of rejecting the
  match up front — so `not: {}` (the boolean `false` schema model) rejects
  `null` as the suite requires. OpenAPI 3.1+ documents are unchanged,
  including `type` unions with the `'null'` member and parser-side
  `nullable` folding. The dialect decision is carried by the document's
  `DialectProfile`, and the root-schema validator cache entry created while
  resolving `$dynamicRef` without a validation context now derives the
  profile from the document version instead of silently defaulting to 3.1.
- `contentEncoding` and `contentMediaType` are annotation-only by default, matching JSON
  Schema validation §8.1: string instance contents are no longer decoded, parsed or
  validated, the keyword values are collected into validation annotations
  (`contentEncoding`/`contentMediaType` keys) instead, and an unrecognized
  `contentMediaType` emits a PSR-14 `ValidationWarningEvent` plus a log warning instead of
  throwing. Previously an invalid `base64` value or an unrecognized media type failed
  validation. Call `OpenApiValidatorBuilder::enableContentAssertions()` to restore the
  previous assertion behavior (strict base64 round-trip check and media type syntax
  validation) as a transitional mode until the full content assertion pipeline ships.
- With `enableContentAssertions()`, `contentEncoding` assertions now cover the full
  RFC 4648 family — `base16`, `base32`, `base32hex`, `base64`, `base64url` (padding
  optional, canonical pad counts enforced, mixed padding rejected) — plus RFC 2045 §6.7
  `quoted-printable` syntax validation (uppercase `=HH` escapes, `=CRLF` soft breaks,
  tolerated trailing whitespace, CRLF-only line breaks). `7bit`, `8bit` and `binary`
  remain identity encodings without data checks, and an unrecognized encoding token now
  emits a PSR-14 `ValidationWarningEvent` plus a log warning instead of being ignored
  silently. The strict canonical `base64` round-trip assertion is unchanged.
- With `enableContentAssertions()`, content assertions now run the full pipeline: the string instance is decoded
  according to `contentEncoding` (`base64` and `base64url`), the decoded content is checked against
  `contentMediaType` (`application/json` is parsed, the other recognized media types keep their syntax checks), and
  a declared `contentSchema` validates the decoded value through the regular schema validator, so sub-schema
  violations reject the main validation. Previously `contentSchema` was ignored and only encoding/media syntax was
  asserted. Decoded content exceeding the configured `maxJsonBodyBytes` cap raises
  `DecodedContentTooLargeException` before parsing — the cap applies to every media type, not only JSON — and
  unknown media types keep emitting the warning event. The pipeline outcome is exposed as a
  `ContentValidationResult` (decoded value, media type validity, collected
  sub-schema errors) through `ValidationContext::contentResult()`, and the pipeline accepts a skip-media flag for
  callers that learn the media type from the message (content map/Encoding Object) instead of the schema.
  Sub-schema validation runs only when `contentMediaType` is declared: per JSON Schema 2020-12 the `contentSchema`
  keyword is bound to `contentMediaType`, so `contentSchema` without a media type stays an annotation in
  assertions mode as well.
- Without content assertions (annotation mode) the pipeline never runs during validation: `contentEncoding` and
  `contentMediaType` remain annotations, the outcome is untouched, and the same `ContentValidationResult` is
  attached to the validation context lazily — it computes on the first consumer read, so undecoded payloads stay
  untouched unless the result is inspected.
- The official JSON Schema test suite section `content.json` is re-bound to the active
  sections: it was previously parked pending the content assertion pipeline work; all 18
  cases assert annotation-only content semantics and pass on the current validator,
  following the rebind precedent of the earlier suite triage.
- Formats on schemas without an explicit `type` are no longer ignored: the format is
  resolved against the instance domain (string/number/integer) and asserted for matching
  instances in assertion mode.
- The format sections of the official JSON Schema test suite are re-classified after the
  format assertion mode work: the format-assertion, date, ipv4, ipv6, json-pointer, regex
  and unknown-format sections (242 cases) join the active sections and pass in the default
  assertion profile; `optional/format/ecmascript-regex.json` joins the excluded sections
  (PCRE is not ECMA-262); `format.json` and the remaining `optional/format/*` sections stay
  pending on the suite residue backlog until the builtin format validators reach full suite
  conformance and the runner gains per-section validator profiles. `format.json` declares
  the annotation-default profile and is fully green under
  `withFormatAssertionMode(Annotation)` — a dedicated replay test pins all 133 cases. The
  suite runner now also treats the raw `InvalidFormatException` (the package's fail-fast
  format error convention) as an invalid verdict.
- Security Scheme Objects now model the OAS 3.2 security surface (§4.27–4.29).
  **BC break for direct integrators.** The OAuth Flows Object wire key
  `deviceCode` is renamed to `deviceAuthorization` (§4.28.1), so the device flow
  no longer disappears when a 3.2 document is parsed; documents still using the
  pre-release `deviceCode` spelling build with the flow silently absent — the old
  key is not accepted (no deprecation cycle applies). `SecurityScheme` gains the
  `deprecated` boolean field (§4.27): it is parsed and serialized, so a
  `deprecated: true` scheme survives round-trip instead of being dropped, and the
  flow-level `deprecated` field of OAuth Flow Objects keeps its separate meaning.
  The `scheme` field is normalized to lowercase at parse time (RFC 9110 §11.1
  case-insensitivity): `Basic` and `basic` produce identical models, and
  serialization emits the normalized value. `oauth2MetadataUrl` is now validated
  as an absolute URI at parse time; a malformed value raises
  `Schema\Exception\InvalidSchemaException` without echoing the offending value.
- Security scheme dispatch is now extensible through a handler registry instead
  of the hardcoded `http`/`apiKey` match chain inside `SecurityValidator`.
  **BC break for direct integrators.** The `SecurityValidator` constructor drops
  its unused `ApiKeyValidator` parameter; handlers are resolved through the new
  `Validator\Security\SecuritySchemeHandlerRegistry` (built with the built-in
  bearer and api key handlers in one place), and custom scheme support is
  declared by implementing the new `Validator\Security\SecuritySchemeHandlerInterface`
  (`supports()` + `validate()`), with the registry picking the first handler
  whose `supports()` returns true. `with()` prepends a handler ahead of the
  built-in set so a user handler overrides a built-in one. Request-visible
  behavior is unchanged: the OR/AND requirement semantics, the
  `UnsupportedSecuritySchemeException` for scheme types without a handler, and
  the `httpScheme` parameter reported for non-bearer `http` subschemes all stay
  exactly as before; `oauth2`, `openIdConnect` and `mutualTLS` keep raising the
  unsupported-scheme error until dedicated built-in handlers land (`http/basic` and
  `http/digest` gain theirs in this release, see Added above).

### Fixed

- Server-Sent Events records now concatenate a value-only `data` field onto a
  preceding empty `data` line per the WHATWG event-stream algorithm: an event
  stream of `data:` followed by `data: hello` yields the event data `"\nhello"`
  (leading newline preserved) instead of replacing the accumulated buffer with
  `"hello"`. Concatenation of non-empty `data` lines and the empty-string result
  of a lone `data:` line are unchanged.

### Removed

- `Schema\Model\Schema::withOverrides()` is removed. Build a schema from
  constraint groups with `Schema::fromConstraintGroups()` (static factory) and
  apply partial updates with `Schema::withOverrideGroups()`, which takes the
  four group DTOs `Schema\Model\Internal\ScalarFields`,
  `ArrayFields`, `ObjectFields` and `CompositionFields` and replaces only the
  fields that are non-null in the supplied groups (`nullable` is a separate
  parameter, so it can be flipped back to `false`). The `Schema` constructor
  is no longer deprecated: instantiating the model directly with named
  arguments is the canonical API.
- `Validator\Error\ValidationContext::create()` and the public
  `ValidationContext` constructor are removed. Create contexts exclusively
  through the named constructor
  `ValidationContext::fromInit(Validator\Error\Internal\ValidationContextInit)`;
  the DTO carries `pool`, `errorFormatter`, `nullableAsType`,
  `emptyArrayStrategy` and `mode`, and the context always owns a fresh
  `BreadcrumbManager`, so per-request isolation is guaranteed by construction.
  Branch contexts keep using `forkForBranch()`.
- The public `Validator\Validation\ValidationStack` constructor is removed.
  Build a stack through `ValidationStack::fromGroup()` with a
  `Validator\Validation\Internal\ValidationGroup` (`root`: `RootServices`
  DTO, `options`: `ValidatorOptions` DTO, optional `bodyLimits`).
- `Validator\Exception\ValidationErrorInterface::getType()` and all of its
  implementations are removed. Use `keyword()`, which returns the same
  validation keyword (`'type'`, `'minLength'`, `'format'`, ...).
- `Builder\OpenApiValidatorInterface::getFormattedErrors()` is removed.
  Format a `ValidationException` through
  `Validator\Error\Formatter\ErrorFormatterInterface::formatException()`.
  The output is byte-identical to the removed method when the validator was
  built with the default formatter; otherwise call the configured formatter's
  `formatException()` directly.
- `Builder\OpenApiValidatorBuilder::enableStrictCallbackRuntimeTemplate()` is
  removed. Strict callback runtime template resolution is the default; use
  `disableStrictCallbackRuntimeTemplate()` to opt out (see the security
  warning in the README).
- `Validator\Exception\InvalidMultipleOfSchemaException::forLargeIntegerWithoutBcmath()`
  is removed. `multipleOf` checks fall back to pure-PHP decimal string
  modulus when the BCMath extension is unavailable, so the exception is never
  thrown; `forNonPositiveValue()` remains.

## [0.8.0]

### Added

- `StreamingContentParser::parserFor()` resolves the format parser
  responsible for a content type through the same detection logic as
  `StreamingMediaTypeDetector::detect()`, and the new `StreamingFormat`
  enum (`NdJson`, `Sse`, `JsonSeq`) is the single source of the routing
  decision. Detection semantics are unified on `str_contains` with the
  full patterns (`application/jsonl`, `application/x-ndjson`,
  `text/event-stream`, `application/json-seq`) — the broader of the two
  semantics previously split between the dispatcher and the detector — and
  the boundary is pinned by detection vectors, including the explicit
  rejection of vendor-suffixed types such as `application/vnd.foo+json-seq`.
- New domain exceptions `Validator\Exception\UnknownLinkException`
  (extends `InvalidArgumentException`) and
  `Validator\Exception\MultipartBodyTooLargeException` (extends
  `RuntimeException`) replace bare SPL throws for undefined component
  links and the multipart part-cap violation.

### Changed

- Streaming parsers now share one line/chunk reader, one record collector
  and one media-type detector. `NdJsonParser::parseStream()` and
  `JsonSeqParser::parseStream()` delegate to `StreamLineReader`
  (`readLines()` / `readChunks()`) instead of re-implementing the
  chunk-buffer loop, and the by-reference `readChunk(&$bomStripped)`
  helpers are gone: the reader is stateless and the UTF-8 BOM flag lives
  inside each read pass, so BOM stripping works per pass no matter how many
  sequential streams are parsed through one `StreamingContentParser`.
  Record counting, the 100 000-record cap, the JSON depth limit and the
  malformed-record policy (strict throw vs log-and-skip) moved into a new
  internal `RecordCollector`, keeping each format's existing degradation
  behaviour (NDJSON/JSON-seq append `null`, SSE keeps the raw value).
  Exceeding a line or record length limit now throws the domain
  `StreamRecordTooLargeException` (extends `RuntimeException`, message
  unchanged) instead of a bare `RuntimeException`, and scalar JSON values
  (`42`, `"text"`, `true`) remain collected as records per the JSON Lines
  and RFC 7464 specs. Limit values are unchanged.
- String-loaded specs (`fromYamlString()` / `fromJsonString()`) no longer pay a
  second, cache-bypassing parse on every `build()` for the external-`$ref`
  confinement guard. The clean confinement verdict is now cached under the
  document cache key with a `_confinement` suffix (SHA-256 of the spec content
  plus the size/depth limits fingerprint), sharing the pool and TTL of the
  cached document, so hot-path builds perform no spec re-parse. A verdict that
  finds an external `$ref` is never cached — the `BuilderException` reproduces
  on every build. Specs loaded from files and configurations without
  `withCache()` are unaffected. (#68)
- All internal LRU caches now share one generic internal class,
  `Validator\Internal\LruCache` (`@template K of string`, `@template V`):
  `ValidatorPool`, `PathRegexCache` and `RegexValidator` delegate to it
  instead of each maintaining their own cache/order/touch/evict arrays.
  Eviction order, touch semantics and the per-class default capacities
  (128 / 256 / 512) are unchanged, and the shared class owns the
  `Max size must be at least 1` constructor guard and a capacity-preserving
  no-op `touch()` for keys that are not stored. The TTL default and its
  validation now live in a single place: `TypedCacheDecorator::DEFAULT_TTL`
  (3600) with `TypedCacheDecorator::validateTtl()`, which `SchemaCache`
  uses for its constructor default and validation.
- The optional `DeprecationLogger` constructor parameter `enabled` is
  renamed to `isEnabled` (boolean-naming convention). Callers passing the
  flag by name (`new DeprecationLogger(enabled: false)`) must switch to
  `isEnabled:`; positional calls and the parameter's position and default
  (`true`) are unchanged.
- `ValidationFinishedEvent` property names follow the naming conventions:
  `success` is renamed to `isSuccess` and `duration` (seconds) to
  `durationInSeconds`. Readers of the public readonly properties
  (`$event->success`, `$event->duration`) must switch to the new names;
  constructor argument names change with them. Types, defaults and
  dispatch semantics are unchanged.
- The two `contentMediaType`/`contentEncoding` validation-error classes
  move into the shared `Validator\Exception` namespace:
  `Duyler\OpenApi\Validator\SchemaValidator\InvalidContentMediaTypeException`
  and `...\InvalidContentEncodingException` are now
  `Duyler\OpenApi\Validator\Exception\InvalidContentMediaTypeException` and
  `...\InvalidContentEncodingException`. Catch-sites referencing the old
  FQCNs must update the import; class names, hierarchy
  (`AbstractValidationError`) and messages are unchanged.
- `OpenApiValidator::resolveLink()` / `resolveLinkWithContext()` throw the
  new `UnknownLinkException` instead of a bare `InvalidArgumentException`
  for undefined component links. The new class extends
  `InvalidArgumentException` and keeps the exact message
  (`Unknown link: %s`), so existing catch blocks continue to work.
- Exceeding the multipart part cap throws the domain
  `MultipartBodyTooLargeException` (extends `RuntimeException`, message
  unchanged) instead of a bare `RuntimeException`.
- The bearer Authorization check inside security validation now runs on the
  same `PregExecutor` instance as every other regex in the validator: the
  `maxRegexBacktracks` limit configured through
  `OpenApiValidatorBuilder::withMaxRegexBacktracks()` governs the
  `http`/`bearer` scheme match too, instead of it silently using a second
  default-capped executor. The `SecurityValidator` constructor no longer
  accepts a `bearerValidator` argument; the bearer validator is wired to
  the injected (or default) `PregExecutor`.

### Fixed

- `uniqueItems` and scalar `enum` no longer produce false duplicates and
  false acceptances around the IEEE-754 double representation. Both keyword
  paths now encode scalar comparison keys through one internal
  `Validator\SchemaValidator\Internal\ScalarKeyCodec` whose float keys are
  an injective per-double-value hex encoding of the big-endian IEEE-754
  bytes (`pack('E', ...)`), fixing the string-cast collapse where the old
  `(string)`-based keys were truncated to the `precision` ini (14 digits by
  default — a different ini from `serialize_precision`), so, for example,
  `enum: [9007199254740990.0]` incorrectly accepted `9007199254740991.0` and
  `uniqueItems: true` rejected `[9007199254740990.0, 9007199254740991.0]` as
  duplicates. `-0.0` and `0.0` now encode to the same key, so the two zeros
  are equivalent per JSON Schema 2020-12 §4.2.2. `NaN` values remain
  never-equal to anything (each NaN occurrence gets a unique key), and mixed
  int/float comparisons above the 2^53 safe-integer boundary stay rejected.
- OpenAPI 3.0 `nullable: true` is no longer discarded when a schema is
  reached through `$ref`. `ScalarSiblingMerger::merge()` combined the `$ref`
  stub's `nullable` flag with the resolved target's using a logical AND, and
  because `Schema::$nullable` defaults to `false` a bare `{$ref: ...}` stub
  always evaluated `false && true` — erasing the target's nullability, so a
  legitimate `null` was rejected by `TypeValidator`. The flag now merges with
  OR: per OpenAPI 3.0 a `nullable` sibling next to `$ref` can only widen the
  target, and there is no spelling for "narrow this to non-nullable" (#64).
- A non-string type nested inside a parameter schema is no longer
  unsatisfiable. `TypeCoercer` coerced only the top-level schema type,
  so `object` and `array` parameters handed their un-coerced members to
  the schema validator: `?page[limit]=3` against `page: {type: object,
  properties: {limit: {type: integer}}}` could not pass for any value a
  client could send, since query, path, header and cookie values arrive
  as strings and coercion is the only path to a non-string leaf. Type
  dispatch is now `Schema`-oriented and recurses through `properties`
  and `items`, so `?page[limit]=3`, `?filter[enabled]=true` and
  `?ids=1,2,3` coerce to `['limit' => 3]`, `['enabled' => true]` and
  `[1, 2, 3]`. The traversal is shared with `RequestBodyCoercer` — which
  already recursed for request bodies — through
  `AbstractCoercer::coerceDeclaredProperties()` and
  `AbstractCoercer::coerceDeclaredItems()` rather than duplicated. (#60)
- Path Item level `parameters` are now enforced during request validation.
  `PathItem.parameters` were parsed into the schema model but never reached
  `RequestValidator`, so `required` and `schema` constraints on parameters
  hoisted to the path item — the place the specification encourages for
  parameters shared by every operation under a path — were silently ignored
  for every location (`path`, `query`, `header`, `cookie`). Path item
  parameters are now merged into the operation's parameter set when the
  operation is resolved, with an operation level parameter overriding the
  path level one on matching `name` + `in`. The same merge is applied to
  webhooks and callbacks, which are Path Item objects as well. (#61)
- An array-typed `form` parameter no longer rejects a single value.
  `ParameterDeserializer::deserializeForm()` decided whether a
  non-exploded value was an array by looking for a comma instead of at
  the declared schema type, so `?include=author,comments` deserialized
  to a list while `?include=author` stayed a string and failed the
  `type: array` check with `TypeMismatchError`. Form now routes on the
  schema type through `splitBySeparator()` — as `simple`, `matrix`,
  `label` and `cookie` already did — yielding a one item list for a
  lone value and an empty list for `?include=`. The comma heuristic
  remains for parameters that declare no array type, and `explode`
  handling is unchanged. (#58)
- A `requestBody` that is a Reference Object is now resolved instead of
  silently discarded, so the referenced body is actually validated. The
  parser dropped `$ref` — it read only `description`, `content` and
  `required` — which produced an empty `RequestBody`, and
  `RequestBodyValidatorWithContext` then returned at its
  `null === $requestBody->content` early exit. Every request body behind a
  `$ref` was therefore unvalidated: malformed payloads passed and
  `required: true` was not enforced, with no exception or warning. Reference
  support is now wired through all five layers that `Parameter` and
  `Response` already used — `RequestBody` gains `ref`/`refSummary`/
  `refDescription`, `ComponentTreeBuilder::buildRequestBody()` branches on
  `$ref`, `DocumentNavigator` accepts `#/components/requestBodies/*` targets,
  and `RefResolverInterface` gains `resolveRequestBody()` and
  `resolveRequestBodyWithOverride()`. Chained and dangling references behave
  as they do for responses: chains follow through, and an unresolvable or
  wrongly-typed pointer throws `UnresolvableRefException` rather than
  failing open. Webhooks and callbacks are covered too, since both validate
  through `RequestValidator`. Per OAS 3.1+, a `description` sibling of
  `$ref` overrides the referenced description (#56).
- `anyOf`/`oneOf`/`allOf` evaluate every branch again once the composition error cap is reached. A merge had restored the early `return` in `validateSchemas()`, so a matching branch declared after the cap was never counted. Only error collection stops at the cap; `allOf` now reports the true number of failed branches (#54)

### Removed

- **BC break for direct integrators.** The experimental `ValidatorCompiler`
  subsystem is removed — `src/Compiler/` (`ValidatorCompiler`,
  `CompilationCache`, `CompilationCacheInterface`, internal code generators
  and their exceptions), `tests/Unit/Compiler/` and the compiled-validator
  benchmark. Runtime validation, `SchemaCache` and the spec-loading cache
  are unaffected. The package is pre-release; no deprecation cycle applies.
- **BC break for direct integrators.** The `ConfinementVerdict` singleton
  class is replaced by the enum
  `ConfinementVerdict { case Clean }`: type hints through
  `ConfinementVerdict::class`, `instanceof` checks and stored cache values
  keep working, but code calling `ConfinementVerdict::clean()` must switch to
  `ConfinementVerdict::Clean`. No consumer inside the monorepo referenced
  the removed symbol (grep = 0).
- **BC break for direct integrators.** The deprecated schema-validation stack
  (`@deprecated since 0.6.0`) is deleted: `SchemaValidator\SchemaValidator`,
  `SchemaValidator\ValidatorDependencies` (the legacy constructor-dependency
  bag), `SchemaValidator\ValidatorFactory`, `Registry\DefaultValidatorRegistry`,
  `Registry\ValidatorRegistryInterface` and `Exception\UnknownValidatorException`.
  Custom validator/registry injection through these classes is removed with no
  replacement; the 29 builtin validators are the complete set.
  All five internal consumption points had already migrated to
  `SchemaValidatorWithContext` / `StatelessValidatorDispatcher` / the
  `SchemaValidatorDependencies` DTO; nothing in `src/` referenced the legacy
  classes. Integrate through `OpenApiValidatorBuilder` or construct validators
  against `SchemaValidatorDependencies` — validators now take that DTO in their
  constructors instead of the removed bag. The one shared keyword-applicability
  filter now lives in `Schema\Internal\ApplicableValidators`, and the single
  `items: true|false` implementation lives in
  `SchemaValidator\Internal\BooleanItemsValidator`.
- `Validation\ValidatorDependencies` is renamed to `Validation\ValidationStack`
  (and `Validation\Internal\ValidatorDependenciesGroup` to
  `Validation\Internal\ValidationGroup`) — one short `ValidatorDependencies` name
  remains, the `Dto\ValidatorDependencies` service container. The class was never
  README-documented; the only caller is `OpenApiValidatorBuilder`, which is
  updated. The 21-parameter constructor keeps its PHPDoc-only `@deprecated`
  (1.x window, `fromGroup()` replacement) — no `#[Deprecated]` attribute, so no
  runtime `E_DEPRECATED` cascade.
- The optional `regexValidator` / `pregExecutor` constructor parameters of
  `Schema\StatelessValidatorRegistry` are removed — the canonical defaults live
  on `SchemaValidatorDependencies`. With custom instances injected through
  `ValidationStack`/`Builder` (`maxRegexBacktracks`, shared `PregExecutor`), the
  stateless registry's schema-level validators now use the default instances;
  request/response/context paths keep the injected ones.
- The optional `?PregExecutor` constructor parameters of
  `ResponseValidatorWithContext`, `ResponseBodyValidatorWithContext` and
  `RequestBodyValidatorWithContext` are removed: header and body validation now
  resolve their regex executor from a single source,
  `SchemaValidatorDependencies::pregExecutor`, so direct construction can no
  longer mix two instances.

## [0.7.3]

### Fixed

- `anyOf`/`oneOf` no longer depend on branch declaration order. The
  `MAX_COMPOSITION_ERRORS` cap in `AbstractCompositionalValidator` used
  `return` to stop collecting errors, which also abandoned the remaining
  branches — so a branch that would match went unevaluated whenever
  earlier branches produced 20+ errors, and `anyOf` reported "At least
  one of the schemas must match, but none did". The cap now bounds error
  collection only; every branch is still evaluated. Error output is
  unchanged (20 errors plus one `TooManyErrorsError`). (#54)

## [0.7.2]

### Fixed

- `nullable: true` is now honoured when it sits beside a composition keyword
  instead of only on the branch schemas, so the standard OAS 3.0 workaround
  for a nullable `$ref` — `{allOf: [$ref], nullable: true}` — accepts `null`.
  `AllOfValidator`, `AnyOfValidator`, `OneOfValidator`,
  `OneOfValidatorWithContext` and `IfThenElseValidator` short-circuit when the
  schema carrying the keyword is nullable, rather than dispatching `null` into
  branches that reject it. `anyOf`/`oneOf` treat this as the keyword being
  satisfied, not as a matching branch, so `oneOf` still enforces exactly-one
  for non-null data. A `null` member of an OAS 3.1 `type` union is ordinary
  JSON Schema and keeps composing — only `nullable: true` waives branches, and
  only while `nullableAsType` is enabled (#50).

## [0.7.1]

### Fixed

- A `null` permitted by a branch of an `allOf`/`anyOf`/`oneOf` is no longer
  rejected before any branch runs. The null pre-check that guards property
  and item validation computed `$allowNull` from the immediate schema node,
  but a composition node carries no `type` and no `nullable` — both live in
  its branches — so `InvalidDataTypeException` fired before a single branch
  was evaluated. This made every nullable attribute unrepresentable in
  JSON:API-style documents, where resources are modelled as `allOf`
  compositions. Composition and `$ref` nodes now defer the decision to the
  branch or resolved target, which already evaluates `nullable` correctly.
  Nulls no branch permits are still rejected, and now carry the failing
  branch's data path instead of a pathless error (#66).
- The same rule was implemented nine times and the copies disagreed — only
  two consulted `$ref`, so a `$ref` property that accepted `null` in one code
  path was rejected in another. All nine now delegate to a single
  `SchemaValueNormalizer::allowsNull()` helper, closing the divergence across
  `properties`, `items`, `prefixItems`, `dependentSchemas`, `if`/`then`/
  `else`, `not`, and composition branches (#66).

## [0.7.0]

Preparation for the 1.0.0 stable release. This section tracks work that
landed on `dev` after the 0.6.0 release cut. All entries below are
internal-only unless explicitly marked as public API.

### Added

- `Schema::fromConstraintGroups()` named constructor — an alternative to
  the 56-parameter `withOverrides()` constructor. The legacy constructor
  is retained (PHPDoc-only `@deprecated`, no runtime attribute) so the 5
  internal callers do not trigger an `E_DEPRECATED` cascade; it will be
  removed in 2.0. See `.ai/reports/adr-schema-constructor.md`.
- Post-refactor behavioral snapshot tests for 3 risk zones
  (`tests/Integration/PostRefactorBehavioralSnapshotTest.php`) covering
  discriminator annotation propagation, streaming-content parser edge
  cases, and `oneOf` nullable handling.
- CI: PHP 8.4 matrix job and Infection mutation-testing job with
  MSI thresholds aligned to the current mutation score.
- Community-health files: `SECURITY.md`, issue/PR templates,
  `.github/dependabot.yml`, `.editorconfig`.
- `// §6 exemption:` marker on `FileExternalRefResolver` documenting the
  ADR-gated decision to keep the class as a single 317-LOC unit (tightly
  coupled with `RefResolver` via `ExternalRefResolverInterface`).

### Changed

- **Internal decomposition (§6)** — 16 oversized classes refactored into
  cohesive collaborators. Public API signatures are unchanged; the splits
  extract private/internal helpers and strategy objects:
  - `OpenApiValidatorBuilder` → 3 internal collaborators.
  - `ValidatorCompiler` → scalar+pattern+utf16 and array+equality+object+
    keyword internal collaborators.
  - `SchemaSiblingMerger` → 3 strategy collaborators
    (`ScalarSiblingMerger`, `BoundSiblingMerger`, `CompositionSiblingMerger`).
  - `StreamingContentParser` → 3 format-specific parsers
    (`NdJsonParser`, `SseParser`, `JsonSeqParser`) + `StreamLineReader`.
  - `RefResolver` → `UriResolver` + `DocumentNavigator` +
    `DiscriminatorDetector`.
  - 4 `SchemaParser`/`SchemaSerializer` oversized classes decomposed.
  - 6 oversized `Validator` classes: long methods extracted via
    extract-method.
- **Validator signatures + DTO grouping (§10)** — `EventDispatchingTrait`
  refactored; validator constructor signatures and DTO grouping aligned
  with `SchemaValidatorDependencies` as the canonical DTO.
- **Codebase-wide code-style cleanup** (386 violations from the
  `.ai/research/code-style-violations.md` audit, closed across 22
  partitioned tasks):
  - §3 Yoda canonical form applied across 28 sites in 17 files.
  - §5 magic values extracted into named constants.
  - §7 redundancy: `&$` reference accumulators, duplicated code,
    `sprintf` consolidation, loop-invariant hoists, regex normalization.
  - §9 nesting flattened via guard clauses; §4 naming aligned with
    domain concepts; §10 exception `$code`/`$previous` convention
    standardised.
  - §12 forbidden PHPDoc removed across all modules (167 stale
    restating-the-code comments). `ValidatorPool`, `PregExecutor`, and
    `LibxmlSecuredContext` shrunk as a side effect; `TypeFormatter`
    deleted (consolidated into its single remaining caller).
  - §11 silent catches now emit PSR-3 log entries at the boundary.
- **AI-slop removal pass** — dead `UriScheme` enum and `AnyOfError`
  exception class deleted; `TypeCoercer` 4-predicate OR replaced with
  `is_scalar()`; `JsonEquals` and `EnumScalarCache` boolean expressions
  extracted into `isNumeric()` / `isScalarOrNull()` helpers; nested
  ternaries in `ScalarSchemaKeywordParser` and `PathItemBuilder`
  flattened to `match(true)` / early-return helpers.
- Rector + PHP-CS-Fixer PHP 8.4 modernisation sweep applied.

### Deprecated

- `Schema::withOverrides()` (56-param constructor) — PHPDoc-only
  `@deprecated since 1.x, will be removed in 2.0`. Use
  `Schema::fromConstraintGroups()` or `Schema::withOverrideGroups()`.
  The `#[Deprecated]` attribute was replaced with a `@deprecated` PHPDoc
  so the 5 internal callers do not trigger a runtime `E_DEPRECATED`
  cascade; the attribute will be reinstated when all internal callers
  migrate, or in 2.0 — whichever comes first.

### Removed

- `Duyler\OpenApi\Validator\Schema\UriScheme` enum — speculative code,
  zero references in `src/` or `tests/`.
- `Duyler\OpenApi\Validator\Exception\AnyOfError` exception class —
  `AnyOfValidator` throws plain `ValidationException` (not `AnyOfError`),
  so the typed exception was never reachable from production code.
  Stale `AnyOfError` row removed from the README Composition Errors
  table; `DetailedFormatterTest` fixture and dataset entry cleaned up.
- `Duyler\OpenApi\Validator\TypeFormatter` — consolidated into its
  single remaining caller during §12 PHPDoc cleanup.

### Fixed

- `null` is now accepted for `$ref` properties/items when the resolved
  target schema allows `null` via `type: [..., 'null']` (previously only
  an explicit `nullable: true` sibling on the stub was honoured).
- `NdJsonParser` memory regression on empty streams — the generator
  frame allocated by `StreamLineReader::readLines()` indirection added
  ~1.8 KB to the empty-stream parse path and broke the
  `parse_stream_empty_ndjson_has_near_zero_memory_growth` budget. The
  parser now reads chunks directly from the PSR-7 stream; the string
  path still uses `StreamLineReader::stripBom()` for BOM handling.
- `StreamingContentParser::parseStream()` short-circuits empty streams
  via `getSize()` before delegating, eliminating PCOV overhead on the
  empty-body path.
- `NotValidator` — removed redundant `$schema->not` truthy check after
  `is_bool($schema->not)` narrowing (Psalm `RedundantCondition`).
- Composition branch errors are no longer lost, duplicated, or
  inconsistent between keywords (#52). A `ValidationException` thrown by
  `allOf`/`anyOf`/`oneOf` now always carries a structured error list:
  a value rejected during branch normalization (typically `null` against
  a non-nullable branch) synthesises a `TypeMismatchError` with the
  branch `dataPath`/`schemaPath` instead of an empty list; `allOf` no
  longer reports each branch error twice; and the `allOf` message counts
  the branches that did not match rather than one of two error buckets.
  `BranchOutcome` and `ValidationResult` carry a single canonical error
  list (`ValidationResult::$abstractErrors` merged into `$errors`, new
  `$failedCount`), so no caller can double-count.
- Infection CI job no longer OOMs; MSI thresholds realigned with the
  current mutation score so the gate is neither green-by-default nor
  unreachable.

### Security
- _(nothing since 0.6.0)_

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

- `SchemaValidator` and `ValidatorDependencies` (the legacy stateless
  dispatcher and its dependency bag) are deprecated since 0.6.0.
  They are retained through the **entire 1.x release line** for backward
  compatibility with internal composition validators. Removal is
  scheduled for **2.0** as part of the parameter-validator migration.
  New code should use `SchemaValidatorWithContext`, the canonical
  validator wired internally by `OpenApiValidatorBuilder::build()`;
  direct callers can obtain it via
  `SchemaValidatorDependencies::rootSchemaValidator()`. Direct
  construction of `ValidatorDependencies` should migrate to
  `SchemaValidatorDependencies` (the canonical DTO).

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

[Unreleased]: https://github.com/duyler/openapi/compare/0.7.0...HEAD
[0.7.0]: https://github.com/duyler/openapi/compare/0.6.0...0.7.0
[0.6.0]: https://github.com/duyler/openapi/compare/0.5.0...0.6.0
[0.5.0]: https://github.com/duyler/openapi/compare/0.4.1...0.5.0
[0.4.1]: https://github.com/duyler/openapi/compare/0.4.0...0.4.1
[0.4.0]: https://github.com/duyler/openapi/compare/0.3.3...0.4.0
[0.3.3]: https://github.com/duyler/openapi/compare/0.3.2...0.3.3
