# Changelog

## [Unreleased]

### Tested
- Install, upgrade, PHPUnit (162 tests) and Behat (13 scenarios) pass on Moodle 5.0.11, 5.1.8, 5.2.4 and 5.3.
- The release zip (`local_aiquestiongen_v0.8.0.zip`) installs through Site administration > Plugins > Install
  plugins on 5.0.11, 5.1.8, 5.2.4 and 5.3 (driven in headless Chromium): the ZIP validates, the plugin is listed as
  "To be installed", the upgrade succeeds and all three tables are created. Also installed from the same zip with
  the command-line upgrade, and the 162 PHPUnit tests passed on the installed copy.

### Fixed
- The API key test now loads `adminlib.php` itself; before Moodle 5.3 the admin setting classes are not autoloaded.
  Test only; the plugin code needed no change.

## [0.8.0] - 2026-11-06

### Added
- Setting "Daily regenerations per user" (default 30, 0 for no limit). Every Regenerate request that reaches the AI
  counts, including ones that end with no usable question; requests refused for other reasons (published draft,
  5 per draft reached) do not. The count is kept in a user preference and resets at the user's midnight.
  Tests for the budget, the regenerator and the web service, and two Behat scenarios (13 in all).

## [0.7.2] - 2026-11-06

### Security
- The direct API key is now stored encrypted, using Moodle's encryption and its own admin setting, which never shows
  the saved key. An upgrade step encrypts a key saved as plain text by an earlier version.

### Changed
- If the saved key cannot be decrypted (for example after a restore without the site's `secret` folder), direct mode
  says so instead of sending a broken key.
- Admin guide and setting help explain that the `secret` folder must be backed up with the database.

## [0.7.1] - 2026-11-06

### Security
- A teacher can no longer download an article file uploaded by another teacher in the same course. The file
  callback now requires that the article belongs to the current user, like every other page and web service.

### Added
- Administrator guide and teacher quick-start in `docs/`, with screenshots; README brought up to date.
- Test for the file callback (`tests/lib_test.php`).

## [0.7.0] - 2026-11-06

### Added
- Privacy provider tests (13): metadata, contexts, export, and deletion for a context, a user and a list of users.
  One test compares every database column with the declared privacy fields, so an undeclared new column fails.
- Language pack tests: English and Japanese have the same keys and placeholders, nothing is an untranslated copy,
  every string used in code exists, none is unused, keys are sorted.
- Behat acceptance tests (11 scenarios) with a data generator (`local_aiquestiongen > drafts`) so no AI is needed.
- Tests for the source form.

### Fixed
- Saving an article raised a PHP warning from the form's file check. It now reads the draft file area directly.
- The review page passed every draft to `js_call_amd`, which Moodle warns about. The drafts are now in a page attribute.
- `publish.php` started output before redirecting. The redirect now happens first.
- Switching the plugin off did not stop jobs already waiting, or Regenerate, from calling the AI. Both now stop.
  Teachers can still review and publish drafts they already have.
- Removed one unused language string; review group sections now have an accessible name.

## [0.6.0] - 2026-11-06

### Added
- Regenerate a single draft, with an optional comment on what should change. Limited to 5 per draft; the original
  is kept if the AI cannot write a valid replacement.
- Direct API mode: Anthropic Claude (Messages API) or any OpenAI-compatible service, through Moodle's HTTP client.
  New settings for provider, endpoint, API key, model, maximum tokens and timeout. Core AI stays the default.
- Optional quiz on publishing: a new hidden quiz, or an existing quiz that has no attempts.

### Fixed
- Adding questions to a quiz was counted wrongly because `quiz_add_quiz_question()` returns nothing on success.

## [0.5.0] - 2026-11-06

### Added
- Fill-in-the-blank questions in two forms: the student types the word (Cloze, with other accepted words), or
  chooses from a list of four (gapselect).
- Reading comprehension: optional reading passage added to the question bank as a description item, tagged
  `reading-comprehension` and not duplicated if published again.

## [0.4.0] - 2026-11-06

### Added
- Publishing to the question bank: choose an existing category or a new one named after the article, in a
  question bank module of the same course. New `local/aiquestiongen:publish` checks, plus `moodle/question:add`.
- Questions are tagged `ai-generated`, the CEFR level and the article title, and published as Ready.
- True/false and essay questions (essay with a model answer and marking guide). A question type registry.
- Event `questions_published`.

### Security
- Teacher text is escaped before it goes into question HTML.

## [0.3.0] - 2026-11-06

### Added
- Review page: drafts grouped by type, with Approve, Reject, Undo, Edit and bulk Approve all / Reject all
  (reject all asks for confirmation), without reloading the page. Changes are announced to screen readers.
- The source article is shown beside the drafts. Drafts that break a rule are marked "Needs checking" and cannot
  be approved until edited; bulk approve skips them.
- Web services `update_item`, `set_item_status`, `set_items_status`; event `item_approved`.
- Published drafts are locked against changes.

## [0.2.0] - 2026-11-06

### Added
- AI generation of multiple-choice questions as a background (ad hoc) task, through Moodle's core AI subsystem.
- Prompt templates, reply parser and validation. Invalid questions are kept and flagged; each question gets one
  retry, and an unreadable reply one retry.
- Job screen showing waiting, running, done or failed.
- Limits for questions per job and jobs per user per day; CEFR level, target words and feedback language options.

## [0.1.0] - 2026-11-06

### Added
- Initial Phase 1 skeleton: version, settings, capabilities, upload page.
- Text extraction for .txt, .html, .docx and .pdf (via pdftotext).
- Database tables `local_aiqg_source`, `local_aiqg_job`, `local_aiqg_item`.
- Privacy provider including the external AI provider location.
- English and Japanese language strings.
