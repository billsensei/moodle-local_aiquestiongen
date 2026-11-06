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

/**
 * Turns a stored draft into the plain array used by the review template and the web services.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_presenter {
    /**
     * Export one draft.
     *
     * @param \stdClass $item The local_aiquestiongen_item record.
     * @return array
     */
    public static function export(\stdClass $item): array {
        $payload = json_decode((string) $item->payload, true) ?: [];

        $answers = [];
        foreach (array_values($payload['answers'] ?? []) as $index => $answer) {
            $answers[] = [
                'index' => $index,
                'letter' => chr(ord('A') + $index),
                'text' => (string) ($answer['text'] ?? ''),
                'correct' => ($answer['correct'] ?? false) === true,
                'feedback' => (string) ($answer['feedback'] ?? ''),
            ];
        }

        $issues = array_map(
            fn($code) => get_string('issue_' . $code, 'local_aiquestiongen'),
            array_values($payload['issues'] ?? [])
        );

        return [
            'id' => (int) $item->id,
            'qtype' => (string) $item->qtype,
            'ismc' => $item->qtype === 'multichoice',
            'istf' => $item->qtype === 'truefalse',
            'isessay' => $item->qtype === 'essay',
            'iscloze' => $item->qtype === 'multianswer',
            'isgap' => $item->qtype === 'gapselect',
            'status' => (string) $item->status,
            'ispending' => $item->status === 'pending',
            'isapproved' => $item->status === 'approved',
            'isrejected' => $item->status === 'rejected',
            'ispublished' => $item->status === 'published',
            'canedit' => $item->status !== 'published',
            'canregenerate' => regenerator::can_regenerate($item),
            'stem' => (string) ($payload['stem'] ?? ''),
            'answers' => $answers,
            'focus' => (string) ($payload['focus'] ?? ''),
            'target' => (string) ($payload['target'] ?? ''),
            'cefr' => (string) ($payload['cefr'] ?? ''),
            'correcttrue' => ($payload['correct'] ?? null) === true,
            'correctfalse' => ($payload['correct'] ?? null) === false,
            'feedbacktrue' => (string) ($payload['feedbacktrue'] ?? ''),
            'feedbackfalse' => (string) ($payload['feedbackfalse'] ?? ''),
            'modelanswer' => (string) ($payload['modelanswer'] ?? ''),
            'rubric' => (string) ($payload['rubric'] ?? ''),
            'fillanswer' => (string) ($payload['answer'] ?? ''),
            'alternatives' => implode("\n", fill_builder::clean_list($payload['alternatives'] ?? [])),
            'distractors' => implode("\n", fill_builder::clean_list($payload['distractors'] ?? [])),
            'flagged' => !empty($payload['flagged']),
            'issues' => $issues,
        ];
    }

    /**
     * The matching web service return structure.
     *
     * @return \core_external\external_single_structure
     */
    public static function structure(): \core_external\external_single_structure {
        return new \core_external\external_single_structure([
            'id' => new \core_external\external_value(PARAM_INT, 'Item id'),
            'qtype' => new \core_external\external_value(PARAM_ALPHANUMEXT, 'Question type'),
            'ismc' => new \core_external\external_value(PARAM_BOOL, 'Is multiple choice'),
            'istf' => new \core_external\external_value(PARAM_BOOL, 'Is true/false'),
            'isessay' => new \core_external\external_value(PARAM_BOOL, 'Is essay'),
            'iscloze' => new \core_external\external_value(PARAM_BOOL, 'Is fill-in-the-blank with typed answer'),
            'isgap' => new \core_external\external_value(PARAM_BOOL, 'Is fill-in-the-blank with a list to choose from'),
            'status' => new \core_external\external_value(PARAM_ALPHA, 'pending, approved, rejected or published'),
            'ispending' => new \core_external\external_value(PARAM_BOOL, 'Is pending'),
            'isapproved' => new \core_external\external_value(PARAM_BOOL, 'Is approved'),
            'isrejected' => new \core_external\external_value(PARAM_BOOL, 'Is rejected'),
            'ispublished' => new \core_external\external_value(PARAM_BOOL, 'Is published'),
            'canedit' => new \core_external\external_value(PARAM_BOOL, 'May be edited'),
            'canregenerate' => new \core_external\external_value(PARAM_BOOL, 'May be regenerated'),
            'stem' => new \core_external\external_value(PARAM_RAW, 'Question text (plain text, escape on output)'),
            'answers' => new \core_external\external_multiple_structure(
                new \core_external\external_single_structure([
                    'index' => new \core_external\external_value(PARAM_INT, 'Position'),
                    'letter' => new \core_external\external_value(PARAM_ALPHA, 'A, B, C...'),
                    'text' => new \core_external\external_value(PARAM_RAW, 'Answer text'),
                    'correct' => new \core_external\external_value(PARAM_BOOL, 'Is the correct answer'),
                    'feedback' => new \core_external\external_value(PARAM_RAW, 'Feedback'),
                ])
            ),
            'focus' => new \core_external\external_value(PARAM_ALPHA, 'vocabulary, grammar or comprehension'),
            'target' => new \core_external\external_value(PARAM_RAW, 'Target word or grammar point'),
            'cefr' => new \core_external\external_value(PARAM_ALPHANUM, 'CEFR level'),
            'correcttrue' => new \core_external\external_value(PARAM_BOOL, 'True/false: the statement is true'),
            'correctfalse' => new \core_external\external_value(PARAM_BOOL, 'True/false: the statement is false'),
            'feedbacktrue' => new \core_external\external_value(PARAM_RAW, 'True/false: feedback for answering True'),
            'feedbackfalse' => new \core_external\external_value(PARAM_RAW, 'True/false: feedback for answering False'),
            'modelanswer' => new \core_external\external_value(PARAM_RAW, 'Essay: model answer'),
            'rubric' => new \core_external\external_value(PARAM_RAW, 'Essay: marking guide'),
            'fillanswer' => new \core_external\external_value(PARAM_RAW, 'Fill-in-the-blank: the correct word'),
            'alternatives' => new \core_external\external_value(PARAM_RAW, 'Typed blank: other correct words, one per line'),
            'distractors' => new \core_external\external_value(PARAM_RAW, 'Chosen blank: wrong words, one per line'),
            'flagged' => new \core_external\external_value(PARAM_BOOL, 'Failed validation'),
            'issues' => new \core_external\external_multiple_structure(
                new \core_external\external_value(PARAM_RAW, 'Problem description')
            ),
        ]);
    }
}
