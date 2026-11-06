# Real AI provider test

Version 0.8.1 has only been tested with a stubbed AI. This checklist runs it once against a real provider.
Use a **test site**. The test makes real API calls (a few cents with Claude) and creates a course and questions.

## 1. Set up

- [ ] Plugin installed and the upgrade finished (Site administration > Notifications shows no pending upgrade).
- [ ] Site administration > Plugins > Local plugins > AI Question Generator. Set:
  - AI mode: **Direct API** (or **Moodle core AI** if you have a core provider set up under Site administration > AI)
  - Provider: **Claude** (or OpenAI-compatible, with the endpoint)
  - Direct API key: your key. Type it only here; the page never shows it again.
  - Model: `claude-sonnet-5-5`, or any model your account can use.
- [ ] Save. The page says "Changes saved". Leave "Daily regenerations per user" at 30 and the job limit as is.
- [ ] Cron is running (`php admin/cli/cron.php`), or run `php admin/cli/adhoc_task.php --execute` after each job starts.

## 2. Teacher flow (browser)

Log in as a teacher in a test course.

- [ ] Open the plugin from the course menu and upload any English article of about 300 words (paste it or upload a file). Check the extracted text is correct.
- [ ] Generate with CEFR B1 and a few of each type: multiple choice, true/false, essay, cloze, gap select.
- [ ] The job moves from queued to done within about a minute. Note the time.
- [ ] Every requested type produced drafts. Each draft is in English, matches the CEFR level, has one clearly correct
  answer where that applies, and is answerable from the article.
- [ ] Drafts with problems show their warnings, and the review page does not break on odd output.
- [ ] Edit one draft and save. Reject one. Approve the rest.
- [ ] Regenerate one draft with a comment ("make it easier"). The new draft follows the comment. Do it 6 times on one
  draft: the sixth is refused.
- [ ] Publish to a new question bank and a new quiz, with the article as a description item.
- [ ] Open the quiz as a student (Login as). The questions display, can be answered and are marked correctly.
  Check the cloze and gap select questions in particular.

## 3. Failures

- [ ] Save a wrong key and generate: the job fails with a clear message and no key text anywhere on the page or in
  the cron output.
- [ ] Set the timeout to 1 second and generate: the job fails cleanly, and generating again after restoring it works.
- [ ] Set "Daily regenerations per user" to 1, regenerate twice: the second is refused.

## 4. Quick script version (no browser)

`docs/live-test.php.txt` does most of step 2 for you as the site admin (generate, regenerate, approve, publish with a quiz) on a throwaway course:

    cp docs/live-test.php.txt /tmp/live-test.php
    php /tmp/live-test.php /path/to/moodle

It prints the job status, tokens used, every draft with its warnings, the regenerated draft and the publish result.

## 5. What to record

For each run: provider, model, time to generate, tokens used, how many drafts passed validation, how many needed
a retry, and any question you would not give to a student. Put the findings in the changelog under "Tested".
