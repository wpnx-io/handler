# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2025-06-14

### Added
- Initial stable release of wpnx/handler package
- Core `Handler` class for WordPress request processing
- Processor chain architecture for extensible request handling
- `Configuration` class with dot notation support and validation
- Built-in processors:
  - `SecurityProcessor` for request validation
  - `TrailingSlashProcessor` for directory redirect handling
  - `StaticFileProcessor` for serving static files
  - `PhpFileProcessor` for direct PHP file requests
  - `DirectoryProcessor` for directory index files
  - `MultisiteProcessor` for WordPress Multisite URL rewriting
  - `WordPressProcessor` for WordPress fallback handling
- `PathValidator` with comprehensive security checks:
  - Path traversal protection (including double-encoded attempts)
  - Null byte injection prevention
  - Hidden file access blocking
  - Symlink validation
- `Environment` class for platform detection (Lambda, standard)
- WordPress Multisite support with URL rewriting
- Static file serving with MIME type detection
- Directory trailing slash enforcement with 307 redirects
- Comprehensive test suite with 100% code coverage
- PSR-12 code style compliance
- PHPStan level 5 static analysis
- PHP 8.0+ support with strict typing

### Security
- Protection against directory traversal attacks
- Protection against null byte injection
- Protection against access to hidden files
- Configurable blocked patterns for sensitive files

### Developer Experience
- Simple API - just 3 lines of code to get started
- Interface-based design for extensibility
- Comprehensive PHPDoc documentation
- Example files for common use cases
- Composer scripts for testing and code quality
- GitHub Actions CI/CD pipeline
