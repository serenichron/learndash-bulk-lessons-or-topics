# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2026-08-12

### Added
- Push content straight from a Google spreadsheet. `apps-script/` holds the
  script, which adds a LearnDash menu with Check and Push and writes the new
  IDs back into the sheet.
- Three REST routes under `ldbc/v1`: `ping`, `check` and `push`. None delete.
- API keys, made and cancelled on the admin page. One per spreadsheet, stored
  hashed, shown once. Ten failures from an address buys a fifteen minute wait.
- A check pass that runs before every import. Nothing is written unless the
  whole sheet is clean, so a bad row can no longer leave half a quiz behind.
  It catches malformed JSON, unknown question types, IDs that are missing or
  the wrong post type, `PREV` with nothing above it, `PREV` on a question,
  questions with no quiz, and sheets that are not upload sheets.
- Check only and detach options on the admin CSV form.
- Questions dropped from a sheet are reported, and taken out of their quiz
  only when asked. The question post is always kept.
- The admin page now says what an import did instead of staying silent.

### Fixed
- **Question order.** Every question was written with `menu_order` 0 and pro
  `sort` 1, leaving the creation timestamp as the only tiebreak. WordPress
  stores that to the second, which is why the import paused a second per row.
  Both fields now carry the question's real position in its quiz.
- **A row with no question no longer kills the import.** `setProps()` ran
  before the null check, so an empty question type threw on any course,
  lesson or topic row.
- **The CSV upload reads the whole file before deleting anything.** The
  delete ran first, so a file that failed to parse cost you the quiz.
- **The exporter never wrote quiz columns.** It tested a variable that was
  never passed in, so the test always failed.
- **The exporter had no permission or nonce check.** Any logged in user could
  pull question and quiz content.
- Exported files used semicolons while the importer read commas, so a
  template could not be imported without editing it first.
- `ld_quiz_questions` is rebuilt in sheet order, so reordering rows reorders
  the quiz.
- Error output on the admin page was echoed raw, including values from the
  CSV.
- Duplicate element IDs on the admin page pointed labels at the wrong fields.

### Changed
- The importer no longer depends on the admin plugin object, so it runs in a
  REST request. New `ImportContext` carries the error list and the per-quiz
  position counters.
- Structured columns can arrive already decoded, not only as JSON strings.
  The spreadsheet sends real JSON rather than JSON quoted inside a CSV.
- Admin script version is the plugin version, not `time()`.
- The exporter emits `quiz_meta`, `quiz_pro_fields` and `question_pro_fields`,
  which the importer already read.

### Removed
- The `update2` action, which read a `demo.csv` that does not exist and
  deleted post 0 on the way.
- Around 270 lines of unreachable code: the backup, diff and confirm changes
  feature that was never wired up, along with its `confirm_changes` AJAX
  endpoint and the backups directory made on activation.

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
