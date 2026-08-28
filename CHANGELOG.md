# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **Try the ids from another site.** Staging sites are usually clones, so what
  production calls quiz 2159694 is very often what QA calls quiz 2159694. Once
  one site is adopted, Tools now offers to take those same numbers and ask
  another site about them, rather than making you paste the list in again for
  every site. One item per site, sitting under Adopt the ids in this sheet.

  It is the adopt screen you already have: same lookup, same post type check,
  same titles side by side. What is not there cannot be ticked, so those rows
  stay CREATE and a push makes them.

  A title that disagrees starts unticked here, unlike when you paste ids in
  yourself. You chose those numbers; these the script proposed, and on a site
  that is not a true clone a plausible id can be a different post entirely.
  This is the one action that could tie fifty rows to the wrong content in a
  single press, so the burden sits on ticking rather than unticking.

### Fixed
- **Borrowing another site’s ids adopted none of them and said so cheerfully.**
  The screen showed thirty-one, all matching, all ticked. Pressing the button
  wrote nothing and reported nothing left over, which reads as success until
  you notice the number is zero. Which site the numbers came from was sent out
  to the screen and back, and when it did not survive the trip the second pass
  fell back to reading the id cells. On a tab showing CREATE there is nothing
  there, so it found nothing, wrote nothing, and skipped nothing. All true,
  and about a different job than the one asked for.

  The sheet now writes down where the numbers came from when the screen opens,
  and that is what the button reads. A run that adopts nothing and refuses
  nothing also says, in as many words, that it had nothing to work from.
- **The menu never changed in a spreadsheet that already ran another script.**
  Apps Script loads every file in a project into one namespace, so two files
  each holding a full copy of the shim do not both run. The one that loads
  last replaces the other, and the loser simply never happens, with nothing
  said either way. Pasting into the wrong copy looked exactly like pasting
  into the right one. The menu is built in `ldbcMenu` now and `onOpen` only
  calls it, so a project that already has an `onOpen` adds one line to its
  own rather than the two fighting over the name.
- **A push that made courses, lessons and topics said it had done nothing.**
  The summary counted two levels and threw the other three away, so a sheet
  of nothing but courses came back as "12 rows, 0 quizzes, 0 questions" and
  read as a run that had failed quietly. Every level is counted now, and both
  the push report and the admin page leave out whatever is zero, so one
  number is easy to find rather than buried under four noughts.
- **Every row of the adopt screen read as a different title.** `lookup`
  answered with `get_the_title`, which runs the `the_title` filter, and that
  texturizes. A hyphen between spaces comes back as an en dash, straight
  quotes come back curly, and some of it arrives as entities rather than
  characters. So a site saying "SB-L &#8211; Listen to a Conversation 1"
  disagreed with a sheet saying "SB-L - Listen to a Conversation 1", and the
  one column worth reading on that screen became worth ignoring. `lookup` now
  answers with the stored title, and the spreadsheet undoes that typesetting
  before it compares, so an older site still lines up.
- **A new key was shown once, off the top of the screen, and never again.**
  The box carried WordPress's `notice` class, and WordPress moves anything
  with that class to the very top of an admin page. The address the page is
  reached at ends in `#keys`, which scrolls straight past it to the table
  below. So the one thing that is shown once and never again was the one
  thing landing where nobody was looking. The box now stays where it is put,
  next to the keys it belongs to, and the page scrolls to it.

## [1.5.1] - 2026-08-24

### Fixed
- **Five quizzes uploaded as Set 1 to Set 5 came back as 5, 3, 4, 1, 2.** A
  bulk import makes a whole sheet inside one second, so every row it creates
  carries the same `post_date`. WordPress sorts its admin lists by date, and
  when dates tie it leaves the answer to MySQL, which returns rows in whatever
  order suits it. New `AdminOrder` sorts by id as well, and ids are handed out
  in creation order, so a tie on the second now resolves to the order the sheet
  had. Nothing is written and no timestamp is invented. It applies only to the
  six post types this plugin creates, and only while a list is on its date
  sort, so clicking any other column still does what it says.

### Changed
- **Adopting an id shows you what you are about to adopt.** Adoption checked
  that the post existed on that site and was the right kind of thing, then
  wrote silently. Neither check catches the failure that matters: a real quiz
  id that is the wrong quiz. A review screen now opens first, one line per
  number, showing the row, the level, the id, what the site calls that post,
  what the sheet calls it, and its status. What is not there, or is a lesson
  where the column says quiz, cannot be ticked. Trouble sorts to the top and
  the rest can be unticked one by one.

  The titles are put side by side rather than compared, because you adopt an id
  precisely so you can overwrite that content, so the sheet being newer than
  the site is the normal case rather than a fault. What two titles catch is the
  real id pointing at an entirely different quiz, and those never read as
  near-misses of each other. The site is asked again when the button is
  pressed, so what is written is what is true at that moment rather than what
  was on screen while you read it. No plugin change: `lookup` already returned
  title and status, and bulk adoption was throwing them away.
- **Tests.** The spreadsheet fake is up to 145 assertions and the admin
  ordering rules run against a fake of `WP_Query`. `npm test` runs both.

## [1.5.0] - 2026-08-19

### Added
- **One sheet, three sites, and no way for an id to cross between them.** The
  spreadsheet script pushed to whichever site was selected in settings, and the
  id columns held that site's numbers. With three cloned sites that was a
  loaded gun: a QA quiz id very often exists on production, as a quiz, with a
  plausible title, so a push to the wrong site passed every check and quietly
  overwrote the wrong content. Now every content row carries a `row_key` the
  script writes once, a hidden `_ldbc_ids` ledger remembers which post that row
  became on each site, and a push sends the ledger's ids for its target and
  ignores the numbers in the cells entirely. The id columns became a view of
  the ledger rather than the source of truth.
- **A push button per site.** Dev staging, QA staging and Production each have
  their own menu item, so the choice is made at the moment of the push rather
  than in a settings box twenty minutes earlier. Each names the host, and says
  how many rows would be updated and how many created, before it runs. A push
  to production asks a second time.
- **Every tab says which site it is showing.** Green, amber or red on the tab
  itself, plus ` -D`, ` -Q` or ` -P` on its name, and a site panel that names
  the site and the host in words and follows you from tab to tab. Each tab
  remembers its own site, so two tabs can show different ones. The menu says
  none of this on purpose: it is built when the spreadsheet opens and cannot be
  rebuilt when you click a tab, so a site name in it would be right at first
  and wrong from the second tab onwards.
- **Adopting ids that already exist.** Content uploaded before any of this
  existed comes under the ledger's care by pasting its ids in, pointing the tab
  at the site they came from, and running Tools, Adopt the ids in this sheet.
  Every id is checked on that site first through the new `lookup` route, so one
  that is not there, or is a lesson where the column says quiz, is reported and
  not written. There is a single-row version under Tools as well.
- **`GET /wp-json/ldbc/v1/lookup`.** Answers what a batch of post ids are on
  this site, up to two hundred at a time: whether each exists, its post type,
  title and status. It is what makes adopting an id fail at the moment you
  adopt it rather than on a push weeks later.
- **A guard against numbers nobody recognises.** A number in an id column that
  matches nothing in the ledger was typed or pasted by a person, and the script
  will not guess what it meant. Nothing can be pushed or repainted until it is
  adopted or cleared, because guessing wrong means either a duplicate or the
  wrong post overwritten.
- **A guard against copied spreadsheets and copied rows.** A copy of the
  spreadsheet inherits every link and would edit the same posts as the
  original, which is rarely what someone making a backup expects, so it is
  noticed and you choose whether to keep the links or start clean. A copied
  row carries its row key and would have two rows claiming one post on every
  site, so it stops the run and Tools, Fix duplicate row keys, sorts it out.
- **A site fingerprint.** The ledger records which host each site's ids came
  from. Point a profile at a different address and the push stops, rather than
  landing old ids on a new WordPress.
- **A library, so twelve spreadsheets are one edit.** `Shim.gs` is eighty lines
  of one-line handoffs and is the only file that goes in a spreadsheet.
  Everything else lives in one standalone script attached as `LDBC`. See
  `apps-script/README.md`.
- **Tests.** The ledger, the resolving, the repainting and the tab painting run
  against a small fake of Apps Script, 122 assertions, `npm run
  test:apps-script`. One of them checks that the shim exposes exactly the names
  the library's menu and dialogs call, so the two cannot drift apart.

### Changed
- **Settings picks which site a tab shows, not where a push goes.** The radio
  buttons are still there and still choose a site, but a push now names its own
  site in the menu.
- **`lookup` answers for many ids at once.** It takes `ids` as a comma
  separated list and returns one entry per id, so adopting a sheet of a
  thousand pasted numbers is ten requests rather than a thousand.

## [1.4.0] - 2026-08-19

### Added
- **A third site, and doors that ask for a password.** The spreadsheet script
  held two sites, staging and live. It now holds three, dev staging, QA staging
  and production, and each carries an optional site username and password for
  the server's own login box, sent as basic auth alongside the key.

### Fixed
- **"Unknown username" from a site behind a front-door password.** The web
  server asks for that password and then hands the same `Authorization` header
  to PHP, where WordPress read it as an application password and refused the
  request before any route of ours was reached. On our routes, and only for a
  caller carrying a key of ours, that verdict is now ignored. The caller stays
  logged out and is still judged by its key.
- **Sheets without a quiz.** A sheet was only accepted if it had a `quiz_id` or
  `question_id` column, so a sheet of nothing but courses, lessons or topics was
  turned away as "not an upload sheet" by both the check and the spreadsheet
  script, though the importer had always built one correctly. Any one of the six
  id columns is now enough.
- **Question order as a student sees it.** Reordering a sheet moved
  `menu_order` and the `ld_quiz_questions` list, but the quiz itself still came
  out in the old order. The pro mapper's `save()` never writes `sort` on an
  update and picks its own on an insert, while the query behind the quiz orders
  by `sort`. `savePro()` now calls `updateSort()`, the one method that writes
  that column, whenever the stored value differs from the question's position.
  Positions count from 1 rather than 0, matching LearnDash.

### Changed
- **An import no longer pays the cache purge on every row.** An eleven row
  sheet took 42.08s, almost all of it in a cache plugin purging and re-warming
  pages over HTTP after each save. New `BulkMode` holds the purge for the
  length of a run and does it once at the end, defers term counting and drops
  revisions, and restores all three in a `finally`. `menu_order` now rides
  along with the title and content in the same save, via a new `extraArgs()`
  hook on `Post`, halving the saves per question. The same sheet imports in
  4.57s.

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
