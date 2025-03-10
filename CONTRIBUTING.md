# Contributing to Laravel AI Bridge

Thank you for considering contributing to Laravel AI Bridge! This document outlines the process for contributing to the project and helps to make the development process easy and effective for everyone involved.

## Code of Conduct

By participating in this project, you agree to abide by the [Code of Conduct](CODE_OF_CONDUCT.md). Please read it before contributing.

## Getting Started

1. **Fork the Repository**
   - Fork the repository on GitHub to your own account.

2. **Clone Your Fork**
   ```bash
   git clone https://github.com/edfavieljr/laravel-ai-bridge.git
   cd laravel-ai-bridge
   ```

3. **Set Up the Development Environment**
   ```bash
   composer install
   ```

4. **Create a Branch**
   - Create a branch for your feature or bugfix:
   ```bash
   git checkout -b feature/your-feature-name
   # or
   git checkout -b fix/your-bugfix-name
   ```

## Development Guidelines

### Coding Standards

This project follows [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards. To ensure your code meets these standards, you can use PHP_CodeSniffer:

```bash
./vendor/bin/phpcs
```

To automatically fix coding standard issues:

```bash
./vendor/bin/phpcbf
```

### Testing

All new features or bug fixes should be covered by tests. This project uses PHPUnit for testing:

```bash
./vendor/bin/phpunit
```

### Documentation

- Update the README.md with details of changes to the interface, if applicable.
- Update the PHPDoc comments for any modified code.
- If your changes require a new dependency or a change in configuration, update the installation and configuration sections in the documentation.

## Pull Request Process

1. **Update Your Fork**
   - Make sure your fork is up to date with the main repository:
   ```bash
   git remote add upstream https://github.com/edfavieljr/laravel-ai-bridge.git
   git fetch upstream
   git merge upstream/main
   ```

2. **Push Your Changes**
   ```bash
   git push origin feature/your-feature-name
   ```

3. **Submit a Pull Request**
   - Go to your repository on GitHub and click the "Pull Request" button.
   - Provide a detailed description of the changes and reference any related issues.

4. **Code Review**
   - At least one maintainer will review your code.
   - Address any comments or requested changes.

5. **Merge**
   - Once approved, a maintainer will merge your PR.

## Feature Requests and Bug Reports

We use GitHub issues to track public bugs and feature requests. Please ensure your description is clear and has sufficient instructions to be able to reproduce the issue.

## Adding a New AI Provider

If you're adding support for a new AI provider:

1. Create a new service class in `src/Services/` that implements the `AIServiceInterface`.
2. Create a corresponding facade in `src/Facades/`.
3. Update the `AIBridgeServiceProvider` to register your new service.
4. Update the configuration file to include settings for the new provider.
5. Add test coverage for the new provider.
6. Update the documentation to include examples of using the new provider.

## Versioning

We follow [Semantic Versioning](https://semver.org/). Given a version number MAJOR.MINOR.PATCH:

- MAJOR version for incompatible API changes
- MINOR version for backward-compatible functionality additions
- PATCH version for backward-compatible bug fixes

## License

By contributing to Laravel AI Bridge, you agree that your contributions will be licensed under the project's [MIT License](LICENSE.md).

## Questions?

If you have any questions or need further clarification, feel free to open an issue with the "question" label.

Thank you for contributing to Laravel AI Bridge!