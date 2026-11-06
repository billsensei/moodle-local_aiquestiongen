<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace local_aiquestiongen\local;

use local_aiquestiongen\ai\ai_client;
use local_aiquestiongen\ai\ai_exception;
use local_aiquestiongen\ai\ai_result;

/**
 * Runs one generation job: for each requested question type, prompt, call the AI, validate, retry once, store drafts.
 *
 * Nothing here touches the question bank. Items are stored as "pending" drafts and a teacher
 * approves them in a later step.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator {
    /** @var int Tokens used so far in this run. */
    private int $tokens = 0;

    /** @var string Model that answered most recently. */
    private string $model = '';

    /**
     * Constructor.
     *
     * @param ai_client $client The AI backend.
     */
    public function __construct(
        /** @var ai_client The AI backend. */
        private readonly ai_client $client,
    ) {
    }

    /**
     * Run a job. Failures are recorded on the job, not thrown.
     *
     * @param int $jobid The job id.
     */
    public function run(int $jobid): void {
        global $DB;

        $job = $DB->get_record('local_aiqg_job', ['id' => $jobid], '*', MUST_EXIST);
        $source = $DB->get_record('local_aiqg_source', ['id' => $job->sourceid], '*', MUST_EXIST);
        $settings = json_decode((string) $job->settings, true) ?: [];

        $DB->set_field('local_aiqg_job', 'status', 'running', ['id' => $job->id]);

        try {
            $stored = 0;
            $problems = [];
            foreach (qtypes::counts($settings) as $qtype => $wanted) {
                if ($wanted < 1) {
                    continue;
                }
                // One type failing must not throw away the drafts of the others.
                try {
                    $stored += $this->generate_type($job->id, $qtype, $source->text, $settings, $wanted);
                } catch (\moodle_exception $e) {
                    $problems[] = $e;
                }
            }
            if ($stored === 0) {
                throw $problems[0] ?? new \moodle_exception('err_noitems', 'local_aiquestiongen');
            }
            $note = implode(' ', array_map(fn($e) => $e->getMessage(), $problems));
            $this->finish($job->id, 'done', $note);
        } catch (\moodle_exception $e) {
            $this->finish($job->id, 'failed', $e->getMessage());
        } catch (\Throwable $e) {
            // Never leave a job stuck in "running", and keep internals out of the teacher's view.
            debugging($e->getMessage() . "\n" . $e->getTraceAsString(), DEBUG_DEVELOPER);
            $this->finish($job->id, 'failed', get_string('err_unexpected', 'local_aiquestiongen'));
        }
    }

    /**
     * Generate and store drafts of one question type.
     *
     * @param int $jobid The job id.
     * @param string $qtype The question type.
     * @param string $text The article text.
     * @param array $settings The job settings.
     * @param int $wanted How many to generate.
     * @return int Number of drafts stored.
     */
    private function generate_type(int $jobid, string $qtype, string $text, array $settings, int $wanted): int {
        // One retry of the whole request if the reply is not usable JSON.
        $items = null;
        for ($attempt = 0; $attempt < 2 && $items === null; $attempt++) {
            $result = $this->call(prompt_builder::build($qtype, $text, $settings, $wanted));
            $items = response_parser::parse($result->text);
        }
        if ($items === null) {
            throw new \moodle_exception('err_unparseable', 'local_aiquestiongen');
        }

        $stored = 0;
        foreach (array_slice($items, 0, $wanted) as $item) {
            $issues = qtypes::validate($qtype, $item, $text);

            if ($issues !== []) {
                // Retry a failing item once, then keep it flagged rather than dropping it.
                $replacement = $this->retry_item($qtype, $text, $settings, $item, $issues);
                if ($replacement !== null) {
                    $item = $replacement;
                    $issues = [];
                }
            }

            $this->store_item($jobid, $qtype, $item, $issues);
            $stored++;
        }
        return $stored;
    }

    /**
     * Ask the AI to fix one failing question.
     *
     * @param string $qtype The question type.
     * @param string $text The article text.
     * @param array $settings The job settings.
     * @param array $item The failing question.
     * @param string[] $issues Its issue codes.
     * @return array|null A valid replacement, or null if the retry also failed.
     */
    private function retry_item(string $qtype, string $text, array $settings, array $item, array $issues): ?array {
        $problems = array_map(fn($code) => get_string('issue_' . $code, 'local_aiquestiongen'), $issues);

        try {
            $result = $this->call(prompt_builder::retry($qtype, $text, $settings, $item, $problems));
        } catch (ai_exception $e) {
            return null;
        }

        $replacements = response_parser::parse($result->text);
        $replacement = $replacements[0] ?? null;
        if ($replacement !== null && qtypes::validate($qtype, $replacement, $text) === []) {
            return $replacement;
        }
        return null;
    }

    /**
     * Call the AI and keep running totals.
     *
     * @param string $prompt The prompt.
     * @return ai_result
     */
    private function call(string $prompt): ai_result {
        $result = $this->client->generate_text($prompt);
        $this->tokens += $result->tokens;
        $this->model = $result->model !== '' ? $result->model : $this->model;
        return $result;
    }

    /**
     * Store one draft.
     *
     * @param int $jobid The job id.
     * @param string $qtype The question type.
     * @param array $item The question.
     * @param string[] $issues Issue codes; non-empty flags the draft for the teacher.
     */
    private function store_item(int $jobid, string $qtype, array $item, array $issues): void {
        global $DB;

        $item['flagged'] = $issues !== [];
        $item['issues'] = $issues;

        $DB->insert_record('local_aiqg_item', (object) [
            'jobid' => $jobid,
            'qtype' => $qtype,
            'payload' => json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'pending',
            'questionid' => null,
            'timemodified' => time(),
        ]);
    }

    /**
     * Record the final state of a job.
     *
     * @param int $jobid The job id.
     * @param string $status done or failed.
     * @param string $error Error message for the teacher, if any.
     */
    private function finish(int $jobid, string $status, string $error): void {
        global $DB;

        $DB->update_record('local_aiqg_job', (object) [
            'id' => $jobid,
            'status' => $status,
            'error' => $error === '' ? null : $error,
            'provider' => $this->model,
            'tokensused' => $this->tokens,
        ]);
    }
}
