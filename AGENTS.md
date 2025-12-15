# Repository Guidelines

## Project Structure & Module Organization
- Core plugin bootstrap lives in `learndash-bulk-create.php` and wires WordPress hooks, AJAX, and admin UI.
- PHP domain logic follows PSR-4 under `classes/` (`TSTPrep\LDImporter\` namespace) for data parsing, export, and post creation; new functionality should extend these classes first.
- Admin markup resides in `templates/admin-page.php`; keep presentation concerns here and avoid business logic in templates.
- Client-side behaviors for the admin screen live in `js/admin.js`; stick to jQuery and enqueue via the existing handle.
- Composer-managed dependencies install to `vendor/` (ignored in VCS); do not commit vendor output.

## Build, Test, and Development Commands
- `composer install` — install PHP dependencies (run in the plugin root).
- `composer dump-autoload` — rebuild the Composer autoloader after adding classes.
- `npm install` — fetch JS typings/dev tooling if you touch `js/` (no build step today).
- `php -l learndash-bulk-create.php classes/**/*.php` — quick syntax check before pushing.
- Activate locally via WP-CLI once placed in `wp-content/plugins/learndash-bulk-lessons-or-topics/`: `wp plugin activate learndash-bulk-lessons-or-topics`.

## Coding Style & Naming Conventions
- PHP: match existing 2-space indentation, trailing commas in arrays, and WordPress-style early returns. Use PascalCase for classes, camelCase for methods/properties, snake_case for WordPress filter/action names.
- JS: 2-space indentation, jQuery-first patterns, avoid global leakage; keep selectors prefixed for this plugin.
- Run Prettier for PHP when formatting (`npx prettier --write learndash-bulk-create.php classes/**/*.php`) if available; do not reflow unrelated code.

## Testing Guidelines
- No automated suite exists; rely on WP-CLI or admin UI manual checks. Use small CSVs to validate create/update/delete flows.
- When adding data operations, test with Courses, Lessons, Topics, and Quizzes to confirm parent-child linkage and meta updates.
- Document manual test steps in PRs (inputs used, expected counts, any cleanup).

## Commit & Pull Request Guidelines
- Prefer concise, present-tense commit messages using `type: summary` (e.g., `fix: handle empty CSV rows`, `chore: bump version`); group related changes.
- PRs should include: goal/approach summary, testing notes, screenshots/GIFs for admin UI tweaks, and any deployment/setup considerations (e.g., new CSV columns).
- Reference related issues or tickets, and flag breaking behaviors or schema changes in the description.

## Security & Configuration Tips
- Preserve nonce and capability checks when touching admin/AJAX handlers; mirror the patterns in `learndash-bulk-create.php`.
- Sanitize and validate CSV input rigorously; reject uploads without required headers.
- When enqueueing scripts/styles, keep handles and versions stable to avoid cache thrash; only load on the plugin’s admin screen.
