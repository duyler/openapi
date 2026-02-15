# PHP Coding Rules for AI

Professional coding standards for this project

## Core Principles

Be careful and attentive - you are a professional software engineer!

## Code Style Rules

### Comments
- **NO comments in code** (strictly prohibited)
- Comments allowed ONLY in public (package API) interfaces using PHPDoc in English

### Type System
- Use **strict typing everywhere** (`declare(strict_types=1);` at top of every file)
- Use modern PHP 8.4+ features (first-class callables, typed properties, etc.)
- Fix Psalm errors properly - **NO `@psalm-suppress` allowed** include in psalm.xml config. The use annotations, `Template T`, and `assert` for fix psalm errors.
- Psalm error level 1 - must pass without suppressions

### Testing Requirements
- Always cover new code with tests
- Coverage must be **≥95%**
- Use PHPUnit with `#[Test]` attribute
- Test methods: `snake_case` without "test" prefix
- Example: `#[Test] public function some_test_method(): void`

### Code Style Patterns
- **NEVER use `@` operator** for error suppression
- **NEVER use `empty()` function**
- Use short array syntax `[]` not `array()`
- No unused imports (enforced by PHP-CS-Fixer)
- Ordered imports: class, function, const (alphabetically sorted)

### Boolean Checks

```php
// Bad
if (!$condition) {
    ...
}

// Good
if (false === $condition) {
    ...
}

// Bad
if (true === $condition) {
    ...
}

// Good
if ($condition) {
    ...
}
```

## Code Quality Standards

### Before Implementation
- Always check that functionality is **NOT duplicated**
- Ensure task is not already started or implemented
- Create a **clear work plan**
- Follow the plan strictly
- Cover classes with interfaces where appropriate
- Apply **SOLID principles**

### Low-level Abstractions
- If low-level functions have ambiguous types and are frequently used, wrap them in abstractions with clear type definitions
- Examples: socket operations, resources, etc.

## Task Completion Criteria

A task is considered **COMPLETE ONLY when**:

1. Code is covered by tests (≥95%)
2. All tests pass: `make tests`
3. Psalm static analysis passes: `make psalm`
4. PHP-CS-Fixer passes: `make cs-fix`
5. Rector passes: `make rector`

**IMPORTANT**: No partial solutions allowed. Complete the entire task.

## Build/Analyze Commands

All commands run via Docker through Makefile:

```bash
make build      # Build docker image and install dependencies
make tests      # Run all PHPUnit tests
make coverage   # Run tests with coverage report (outputs to coverage/)
make psalm      # Run Psalm static analysis
make cs-fix     # Run PHP-CS-Fixer to fix code style issues
make rector     # Run Rector for automated refactoring
make infection  # Run Infection mutation testing
make shell      # Open shell in docker container
make update     # Update dependencies
```

### Running Single Tests

```bash
# Run a specific test class
docker-compose run --rm php vendor/bin/phpunit --filter MyTestClassName

# Run a specific test method
docker-compose run --rm php vendor/bin/phpunit --filter testMethodName

# Run tests with verbose output
docker-compose run --rm php vendor/bin/phpunit --testdox
```

## Documentation

### README.md
- Update ONLY if necessary for library users
- Write in English
- Do not use emojis
- Maintain existing blocks/structure
- Schemes and tables are allowed

### Reports
- Write important reports in `.duyler/` directory
- Use Russian language
- Maintain consistent file naming style

## Code Review Rules

- Base review on phase tasks and coding rules
- If issues arise, write them to separate MD in `.duyler/tasks/review`
- Reference the task/phase file in the review
- Update phase status and checkpoints according to implementation

## Quality Tools

- **PHPUnit 11.x** - Testing framework
- **Psalm** - Static analysis (errorLevel 1)
- **PHP-CS-Fixer** (@PER-CS config)
- **Rector** - Automated refactoring
- **Infection** - Mutation testing (threshold: 80%)

### Project Structure

```
src/              # Source code
tests/            # Test files
coverage/         # Coverage report output directory
.duyler/          # Documentation and reports (Russian language)
composer.json     # PHP dependencies
Makefile          # Build commands (Docker-based)
phpunit.xml       # PHPUnit configuration
psalm.xml         # Psalm static analysis config
.php-cs-fixer.php # Code style rules
rector.php        # Rector refactoring config
infection.json    # Mutation testing config
```
