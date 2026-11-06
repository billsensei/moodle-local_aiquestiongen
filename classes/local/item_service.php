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

use local_aiquestiongen\event\item_approved;

/**
 * Edit, approve and reject draft questions. All access checks live here.
 *
 * Nothing in this class writes to the question bank; publishing is a separate later step.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_service {
    /** @var string[] Statuses a teacher may set. "published" is only set by publishing. */
    public const SETTABLE = ['pending', 'approved', 'rejected'];

    /** @var int The most characters one edited field (question text, an answer, feedback) may have. */
    public const MAX_FIELD_LENGTH = 2000;

    /**
     * Load a draft and check the user may work on it.
     *
     * @param int $itemid The item id.
     * @param int $userid The acting user.
     * @return array [item record, source record, course context]
     */
    public static function load_item(int $itemid, int $userid): array {
        global $DB;

        $item = $DB->get_record('local_aiquestiongen_item', ['id' => $itemid], '*', MUST_EXIST);
        [$job, $source, $context] = self::load_job($item->jobid, $userid);
        return [$item, $source, $context];
    }

    /**
     * Load a job and check the user may work on it.
     *
     * @param int $jobid The job id.
     * @param int $userid The acting user.
     * @return array [job record, source record, course context]
     */
    public static function load_job(int $jobid, int $userid): array {
        global $DB;

        $job = $DB->get_record('local_aiquestiongen_job', ['id' => $jobid], '*', MUST_EXIST);
        $source = $DB->get_record('local_aiquestiongen_source', ['id' => $job->sourceid], '*', MUST_EXIST);
        $context = \context_course::instance($source->courseid);

        require_capability('local/aiquestiongen:use', $context, $userid);
        if ((int) $source->userid !== $userid) {
            throw new \moodle_exception('err_notowner', 'local_aiquestiongen');
        }
        return [$job, $source, $context];
    }

    /**
     * Refuse an edit with a field longer than a question could reasonably need.
     *
     * @param string $stem The question text.
     * @param array $answers Multiple choice answers.
     * @param array $extra Type-specific fields.
     * @throws \moodle_exception If any field is too long.
     */
    private static function require_field_lengths(string $stem, array $answers, array $extra): void {
        $texts = [$stem];
        foreach ($answers as $answer) {
            $texts[] = (string) ($answer['text'] ?? '');
            $texts[] = (string) ($answer['feedback'] ?? '');
        }
        foreach ($extra as $value) {
            if (is_string($value)) {
                $texts[] = $value;
            }
        }
        foreach ($texts as $text) {
            if (\core_text::strlen($text) > self::MAX_FIELD_LENGTH) {
                throw new \moodle_exception('err_fieldlength', 'local_aiquestiongen', '', self::MAX_FIELD_LENGTH);
            }
        }
    }

    /**
     * Save the teacher's edits to a draft.
     *
     * The edit is saved even if it is not valid, so work is never lost; the draft is then flagged.
     * An approved draft goes back to pending, because the teacher has not approved the new wording.
     *
     * Type-specific fields go in $extra: "correct", "feedbacktrue" and "feedbackfalse" for true/false;
     * "modelanswer" and "rubric" for essay; "answer" plus "alternatives" (typed blank) or "distractors"
     * (chosen blank), as text with one word per line, for fill-in-the-blank. Fields that are null or missing are left as they were.
     *
     * @param \stdClass $item The item record.
     * @param \stdClass $source The source record.
     * @param string $stem The new question text.
     * @param array $answers For multiple choice: list of [text, correct, feedback].
     * @param array $extra Type-specific fields.
     * @return \stdClass The updated item record.
     */
    public static function update_item(
        \stdClass $item,
        \stdClass $source,
        string $stem,
        array $answers = [],
        array $extra = []
    ): \stdClass {
        global $DB;

        self::require_not_published($item);
        self::require_field_lengths($stem, $answers, $extra);

        $payload = json_decode((string) $item->payload, true) ?: [];
        $payload['stem'] = trim($stem);

        switch ($item->qtype) {
            case 'multichoice':
                $payload['answers'] = [];
                foreach ($answers as $answer) {
                    $payload['answers'][] = [
                        'text' => trim((string) $answer['text']),
                        'correct' => (bool) $answer['correct'],
                        'feedback' => trim((string) $answer['feedback']),
                    ];
                }
                break;
            case 'truefalse':
                if (isset($extra['correct'])) {
                    $payload['correct'] = (bool) $extra['correct'];
                }
                foreach (['feedbacktrue', 'feedbackfalse'] as $field) {
                    if (isset($extra[$field])) {
                        $payload[$field] = trim((string) $extra[$field]);
                    }
                }
                break;
            case 'essay':
                foreach (['modelanswer', 'rubric'] as $field) {
                    if (isset($extra[$field])) {
                        $payload[$field] = trim((string) $extra[$field]);
                    }
                }
                break;
            case 'multianswer':
            case 'gapselect':
                if (isset($extra['answer'])) {
                    $payload['answer'] = trim((string) $extra['answer']);
                }
                // The list that belongs to this type: other correct words, or wrong words.
                $listfield = $item->qtype === 'multianswer' ? 'alternatives' : 'distractors';
                if (isset($extra[$listfield])) {
                    $payload[$listfield] = fill_builder::clean_list(preg_split('/\R/u', (string) $extra[$listfield]));
                }
                break;
        }

        $issues = qtypes::validate($item->qtype, $payload, $source->text);
        $payload['flagged'] = $issues !== [];
        $payload['issues'] = $issues;

        $item->payload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $item->status = $item->status === 'approved' ? 'pending' : $item->status;
        $item->timemodified = time();
        $DB->update_record('local_aiquestiongen_item', $item);
        return $item;
    }

    /**
     * Approve, reject or return a draft to pending.
     *
     * @param \stdClass $item The item record.
     * @param \stdClass $source The source record.
     * @param \context $context The course context.
     * @param string $status pending, approved or rejected.
     * @return \stdClass The updated item record.
     */
    public static function set_status(\stdClass $item, \stdClass $source, \context $context, string $status): \stdClass {
        global $DB;

        if (!in_array($status, self::SETTABLE, true)) {
            throw new \moodle_exception('err_badstatus', 'local_aiquestiongen');
        }
        self::require_not_published($item);

        if ($status === 'approved') {
            // A draft that fails validation must be fixed before it can be approved.
            $payload = json_decode((string) $item->payload, true) ?: [];
            $issues = qtypes::validate($item->qtype, $payload, $source->text);
            if ($issues !== []) {
                $descriptions = array_map(fn($code) => get_string('issue_' . $code, 'local_aiquestiongen'), $issues);
                throw new \moodle_exception('err_cannotapprove', 'local_aiquestiongen', '', implode('; ', $descriptions));
            }
        }

        $wasapproved = $item->status === 'approved';
        $item->status = $status;
        $item->timemodified = time();
        $DB->update_record('local_aiquestiongen_item', $item);

        if ($status === 'approved' && !$wasapproved) {
            item_approved::create([
                'objectid' => $item->id,
                'context' => $context,
                'other' => ['jobid' => $item->jobid],
            ])->trigger();
        }
        return $item;
    }

    /**
     * Approve or reject every pending draft of one question type in a job.
     *
     * "Approve all" skips drafts that fail validation; the teacher has to look at those one by one.
     *
     * @param \stdClass $job The job record.
     * @param \stdClass $source The source record.
     * @param \context $context The course context.
     * @param string $qtype The question type.
     * @param string $status approved or rejected.
     * @return array [changed item records, number skipped]
     */
    public static function set_status_bulk(
        \stdClass $job,
        \stdClass $source,
        \context $context,
        string $qtype,
        string $status
    ): array {
        global $DB;

        if (!in_array($status, ['approved', 'rejected'], true)) {
            throw new \moodle_exception('err_badstatus', 'local_aiquestiongen');
        }

        $pending = $DB->get_records(
            'local_aiquestiongen_item',
            ['jobid' => $job->id, 'qtype' => $qtype, 'status' => 'pending'],
            'id ASC'
        );

        $changed = [];
        $skipped = 0;
        foreach ($pending as $item) {
            try {
                $changed[] = self::set_status($item, $source, $context, $status);
            } catch (\moodle_exception $e) {
                $skipped++;
            }
        }
        return [$changed, $skipped];
    }

    /**
     * Published drafts are in the question bank and cannot be changed here.
     *
     * @param \stdClass $item The item record.
     */
    private static function require_not_published(\stdClass $item): void {
        if ($item->status === 'published') {
            throw new \moodle_exception('err_published', 'local_aiquestiongen');
        }
    }
}
