# AI Question Generator (local_aiquestiongen)

Teachers upload or paste an article; AI drafts EFL questions from it; nothing reaches the
question bank until the teacher approves it. Targets Moodle 5.0 or later.

**Status: version 0.8.1, feature complete, tested on Moodle 5.0, 5.1, 5.2 and 5.3 with a stubbed AI.**
Not yet tried with a real AI provider.

Features: article upload (.txt, .docx, .pdf, .html) or paste; five question types (multiple choice,
true/false, essay, typed fill-in-the-blank, drop-down fill-in-the-blank); review, edit, regenerate and
approve; publish to the question bank with tags, optional reading passage and optional quiz; Moodle
core AI or a direct Claude/OpenAI-compatible API; privacy provider; English and Japanese.

## Documentation

- [Administrator guide](docs/ADMIN-GUIDE.md): install, settings, AI connection, troubleshooting.
- [Teacher quick-start](docs/TEACHER-QUICKSTART.md): from article to published questions.

## Installation

1. Copy this directory to `local/aiquestiongen` (Moodle 5.0) or `public/local/aiquestiongen` (Moodle 5.1+).
2. Visit Site administration > Notifications, or run `php admin/cli/upgrade.php`.
3. Make sure cron is running (generation is a background task).
4. Configure at Site administration > Plugins > Local plugins > AI Question Generator.
5. For PDF uploads install `poppler-utils` and set the path to `pdftotext` in the settings.

Teachers with `local/aiquestiongen:use` see "AI question generator" in the course "More" menu.

## Tests

```bash
vendor/bin/phpunit --testsuite local_aiquestiongen_testsuite
```

## License

GNU GPL v3 or later.
