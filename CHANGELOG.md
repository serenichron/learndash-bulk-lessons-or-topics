# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.6] - 2026-06-08

### Added
- Nonce check on form submission for CSRF protection (`extended_learndash_bulk_create_nonce`)
- Error message display in admin UI when import fails
- `Extended_LearnDash_Bulk_Create` error message collector for user-facing error feedback
- `dump()` method on `Data` class for debug logging

### Fixed
- Question type validation: throws explicit error when question type does not exist instead of silently failing
- Exception handling in CSV upload: wraps the entire import process in try/catch with user-facing error reporting

## [1.2.5] - 2026-03-27

### Fixed
- Restore correct LearnDash question-to-quiz assignment updates during import
- Ensure question mappings are written back to the quiz post and removed from the previous quiz when reassigned

## [1.2.4] - 2026-03-19

### Changed
- Allow all quiz meta fields for flexible quiz import configuration

### Removed
- Unused question type classes, traits, contracts, and PostFactory

## [1.2.3] - 2026-01-10

### Fixed
- Fix quiz pro fields method call (was incorrectly calling `questionProFields()` instead of `quizProFields()`)

## [1.2.2] - 2026-01-07

### Added
- Quiz pro fields support for quiz import

## [1.2.1] - 2025-12-15

### Changed
- Version bump for release maintenance

## [1.2.0] - 2025-12-15

### Added
- CSV parser for improved data handling
- Exporter functionality for quiz and question data
- Custom fields support for questions and quizzes
- Quiz affixes export for extended metadata
- Quiz metadata support

### Changed
- Update menu order on questions for better organization
- Ensure different creation timestamps for proper ordering

### Removed
- Unused files for cleaner codebase

### Fixed
- Various fixes for new structure implementation

## [1.1.5] - 2025-03-31

### Changed
- Maintenance release

## [1.1.3] - 2025-03-18

### Changed
- Bug fixes and improvements

## [1.1.2] - Earlier

### Changed
- Initial stable releases with core functionality

[1.2.5]: https://github.com/serenichron/learndash-bulk-lessons-or-topics/compare/v1.2.4...v1.2.5
[1.2.4]: https://github.com/serenichron/learndash-bulk-lessons-or-topics/compare/v1.2.3...v1.2.4
[1.2.3]: https://github.com/serenichron/learndash-bulk-lessons-or-topics/compare/v1.2.2...v1.2.3
[1.2.2]: https://github.com/serenichron/learndash-bulk-lessons-or-topics/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/serenichron/learndash-bulk-lessons-or-topics/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/serenichron/learndash-bulk-lessons-or-topics/compare/v1.1.5...v1.2.0
[1.1.5]: https://github.com/serenichron/learndash-bulk-lessons-or-topics/compare/v1.1.3...v1.1.5
[1.1.3]: https://github.com/serenichron/learndash-bulk-lessons-or-topics/releases/tag/v1.1.3
