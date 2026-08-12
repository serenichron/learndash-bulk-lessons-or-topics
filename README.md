# LearnDash Bulk Create

A WordPress plugin for building LearnDash quizzes and questions in bulk,
either from a CSV file or straight from a Google spreadsheet.

## What it does

Content is described one row at a time. A row can carry a group, a course,
a lesson, a topic, a quiz and a question, and each of those is either made
fresh, updated by ID, or carried down from the row above.

Two ways in.

**A CSV upload**, on the admin page under LearnDash, Bulk Create/Update.

**A Google spreadsheet**, using the script in [apps-script/](apps-script/).
It adds a LearnDash menu to your spreadsheet with Check and Push, and writes
the new IDs back into the sheet for you. Setup is in
[apps-script/README.md](apps-script/README.md).

Both go through the same importer, so a quiz comes out the same either way.

## Nothing is written until the whole sheet is clean

Every import is checked first. If any row has a problem, nothing at all is
written, and you get the list of problems with the row and column named.

The check catches malformed JSON in any of the structured columns, question
types that do not exist, IDs that are not on the site or are the wrong post
type, `PREV` with nothing above it to point at, questions with no quiz to go
in, and sheets that are not upload sheets at all.

## Columns

| Column | Meaning |
| --- | --- |
| `group_id` | An existing LearnDash group. Numbers only |
| `course_id`, `course_post_title`, `course_post_content` | |
| `lesson_id`, `lesson_post_title`, `lesson_post_content` | |
| `topic_id`, `topic_post_title`, `topic_post_content` | |
| `quiz_id`, `quiz_post_title`, `quiz_post_content` | |
| `quiz_affixes`, `quiz_meta`, `quiz_pro_fields` | JSON |
| `question_id`, `question_post_title`, `question_post_content` | |
| `question_type` | Must match a type registered by the advanced quizzes plugin |
| `question_answers`, `question_meta`, `question_affixes`, `question_pro_fields` | JSON |

Every column is optional. A row simply does nothing at a level whose ID
column is empty.

Each `*_id` column takes one of four things.

| Value | What happens |
| --- | --- |
| `CREATE` | Make something new. Its ID is reported back |
| `PREV` | Reuse whatever the row above resolved to |
| a number | Update the post with that ID |
| empty | This row has nothing at this level |

`PREV` does not work on `question_id`. Every question needs its own row.

Question order follows sheet order. Reordering rows in the sheet and pushing
again reorders the quiz.

## Requirements

- WordPress 5.0 or higher
- PHP 8.0 or higher
- LearnDash LMS 3.0 or higher
- The TSTPrep advanced quizzes plugin, which registers the question types

## Installation

Composer, from the plugin root:

```
composer install
```

To pull it into a site, add the repository to your project's `composer.json`:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:serenichron/learndash-bulk-lessons-or-topics.git"
    }
  ]
}
```

Then `composer require serenichron/learndash-bulk-create` and activate.

## The API

Three routes under `ldbc/v1`, used by the spreadsheet script.

| Route | Does |
| --- | --- |
| `GET /ping` | Reports which site this is, and whether LearnDash and the question types plugin are present |
| `POST /check` | Reads rows and reports. Writes nothing |
| `POST /push` | Checks, then builds. Returns the resolved IDs per row |

None of them delete anything.

Authentication is a key made on the admin page, sent as an `X-LDBC-Key`
header or a bearer token. Make one key per spreadsheet so they can be
cancelled one at a time. Only the hash is stored, and the key is shown once.

`push` takes a `detach_missing` flag, off by default. When on, a question
that sits in a quiz on the site but is no longer in the sheet is taken out
of the quiz. The question post itself is always kept.

## Support

Bug reports and feature requests go to the
[GitHub issue tracker](https://github.com/serenichron/learndash-bulk-create/issues).

## License

GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
