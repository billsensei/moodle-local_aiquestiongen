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

/**
 * Data generator for local_aiquestiongen tests.
 *
 * @package    local_aiquestiongen
 * @category   test
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Creates articles, finished jobs and draft questions without calling an AI.
 *
 * @package    local_aiquestiongen
 * @category   test
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_aiquestiongen_generator extends component_generator_base {
    /** @var string Default article text; it contains the word the multiple-choice drafts ask about. */
    public const ARTICLE = 'Tom was reluctant to leave the town. He had lived there for many years, and everyone knew him.';

    /**
     * Create one draft question, with its article and a finished job if they do not exist yet.
     *
     * Fields: courseid, userid, article (title), qtype (multichoice, truefalse, essay, multianswer, gapselect),
     * stem. Optional: status (default pending), flagged (1 gives it a validation problem), correct (true/false
     * questions: "true" or "false"), text (article text, used when the article is created).
     *
     * @param array|stdClass $record The fields.
     * @return stdClass The item record.
     */
    public function create_draft($record): stdClass {
        global $DB;
        $record = (array) $record;

        $source = $DB->get_record('local_aiqg_source', [
            'courseid' => $record['courseid'], 'userid' => $record['userid'], 'title' => $record['article'],
        ]);
        if (!$source) {
            $source = (object) [
                'courseid' => $record['courseid'], 'userid' => $record['userid'], 'title' => $record['article'],
                'text' => $record['text'] ?? self::ARTICLE, 'sourcefileitemid' => 0, 'timecreated' => time(),
            ];
            $source->id = $DB->insert_record('local_aiqg_source', $source);
        }

        $job = $DB->get_record('local_aiqg_job', ['sourceid' => $source->id]);
        if (!$job) {
            $job = (object) [
                'sourceid' => $source->id, 'settings' => json_encode(['cefr' => 'B1', 'language' => 'English', 'target' => '']),
                'status' => 'done', 'tokensused' => 0, 'timecreated' => time(),
            ];
            $job->id = $DB->insert_record('local_aiqg_job', $job);
        }

        $payload = $this->payload($record['qtype'], $record['stem'], $record);
        if (!empty($record['flagged'])) {
            // A draft the validator would reject, so the page shows it needing attention.
            $payload = array_diff_key($payload, ['correct' => 1, 'modelanswer' => 1, 'answer' => 1]);
            $payload['answers'] = isset($payload['answers']) ? array_slice($payload['answers'], 0, 3) : null;
            $payload = array_filter($payload, fn($v) => $v !== null);
            $payload['flagged'] = true;
            $payload['issues'] = [$record['qtype'] === 'multichoice' ? 'answercount' : 'nocorrect'];
        }

        $item = (object) [
            'jobid' => $job->id, 'qtype' => $record['qtype'], 'status' => ($record['status'] ?? '') ?: 'pending',
            'payload' => json_encode($payload), 'timemodified' => time(),
        ];
        $item->id = $DB->insert_record('local_aiqg_item', $item);
        return $item;
    }

    /**
     * A valid draft of the given type.
     *
     * @param string $qtype The question type.
     * @param string $stem The question text.
     * @param array $record The row, for type-specific options.
     * @return array
     */
    private function payload(string $qtype, string $stem, array $record): array {
        $base = ['type' => $qtype, 'stem' => $stem, 'cefr' => 'B1', 'flagged' => false, 'issues' => []];
        switch ($qtype) {
            case 'multichoice':
                return $base + [
                    'focus' => 'vocabulary', 'target' => 'reluctant',
                    'answers' => [
                        ['text' => 'unwilling', 'correct' => true, 'feedback' => 'Yes.'],
                        ['text' => 'eager', 'correct' => false, 'feedback' => ''],
                        ['text' => 'angry', 'correct' => false, 'feedback' => ''],
                        ['text' => 'tired', 'correct' => false, 'feedback' => ''],
                    ],
                ];
            case 'truefalse':
                return $base + [
                    'focus' => 'comprehension', 'correct' => (($record['correct'] ?? '') ?: 'true') === 'true',
                    'feedbacktrue' => 'Right.', 'feedbackfalse' => 'Not quite.',
                ];
            case 'essay':
                return $base + [
                    'focus' => 'comprehension', 'modelanswer' => 'He did not want to go.',
                    'rubric' => 'Gives a reason.',
                ];
            case 'multianswer':
                return $base + [
                    'focus' => 'vocabulary', 'target' => 'reluctant', 'answer' => 'reluctant', 'alternatives' => ['unwilling'],
                ];
            case 'gapselect':
                return $base + [
                    'focus' => 'vocabulary', 'target' => 'reluctant', 'answer' => 'reluctant',
                    'distractors' => ['eager', 'angry', 'tired'],
                ];
        }
        throw new coding_exception("Unknown question type '$qtype'");
    }
}
