# Contributing to Duyler Package

Thank you for your interest in contributing! This document provides guidelines for contributing to the project.

## Running Tests

```bash
# Run all tests
make tests

# Run specific test suite
php vendor/bin/phpunit tests/Unit

# Generate coverage report
make coverage
```

## Code Style

This project follows PER-CS coding standard.

```bash
# Fix code style automatically
make cs-fix
```

## Static Analysis

```bash
# Run Psalm static analyzer
make psalm
```

## Type Safety

- Always use `declare(strict_types=1);`
- All methods must have proper type hints
- No suppression of static analysis errors

## Writing Tests

- Use `#[Test]` attribute instead of `test_` prefix
- Follow snake_case for test method names
- Cover new code with tests
- Ensure all tests pass before submitting PR

## Submitting Changes

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add/update tests
5. Run `make tests`, `make psalm`, `make cs-fix`, `make rector`
6. Submit a pull request

## License

By contributing, you agree that your contributions will be licensed under the MIT License.
