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

namespace local_aiquestiongen\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_aiquestiongen\local\item_presenter;
use local_aiquestiongen\local\item_service;

/**
 * Web service: save a teacher's edits to a draft question.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class update_item extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'Item id'),
            'stem' => new external_value(PARAM_RAW, 'Question text'),
            'answers' => new external_multiple_structure(
                new external_single_structure([
                    'text' => new external_value(PARAM_RAW, 'Answer text'),
                    'correct' => new external_value(PARAM_BOOL, 'Is the correct answer'),
                    'feedback' => new external_value(PARAM_RAW, 'Feedback'),
                ]),
                'Multiple choice answers',
                VALUE_DEFAULT,
                []
            ),
            'correcttrue' => new external_value(PARAM_BOOL, 'True/false: the statement is true', VALUE_DEFAULT, null, NULL_ALLOWED),
            'feedbacktrue' => new external_value(PARAM_RAW, 'True/false: feedback for True', VALUE_DEFAULT, null, NULL_ALLOWED),
            'feedbackfalse' => new external_value(PARAM_RAW, 'True/false: feedback for False', VALUE_DEFAULT, null, NULL_ALLOWED),
            'modelanswer' => new external_value(PARAM_RAW, 'Essay: model answer', VALUE_DEFAULT, null, NULL_ALLOWED),
            'rubric' => new external_value(PARAM_RAW, 'Essay: marking guide', VALUE_DEFAULT, null, NULL_ALLOWED),
            'fillanswer' => new external_value(PARAM_RAW, 'Blank: the correct word', VALUE_DEFAULT, null, NULL_ALLOWED),
            'alternatives' => new external_value(PARAM_RAW, 'Typed blank: other correct words', VALUE_DEFAULT, null, NULL_ALLOWED),
            'distractors' => new external_value(PARAM_RAW, 'Chosen blank: wrong words', VALUE_DEFAULT, null, NULL_ALLOWED),
        ]);
    }

    /**
     * Save the edits.
     *
     * @param int $itemid Item id.
     * @param string $stem Question text.
     * @param array $answers Multiple choice answers.
     * @param bool|null $correcttrue True/false: the statement is true.
     * @param string|null $feedbacktrue True/false: feedback for True.
     * @param string|null $feedbackfalse True/false: feedback for False.
     * @param string|null $modelanswer Essay: model answer.
     * @param string|null $rubric Essay: marking guide.
     * @param string|null $fillanswer Blank: the correct word.
     * @param string|null $alternatives Typed blank: other correct words, one per line.
     * @param string|null $distractors Chosen blank: wrong words, one per line.
     * @return array The updated item.
     */
    public static function execute(
        int $itemid,
        string $stem,
        array $answers = [],
        ?bool $correcttrue = null,
        ?string $feedbacktrue = null,
        ?string $feedbackfalse = null,
        ?string $modelanswer = null,
        ?string $rubric = null,
        ?string $fillanswer = null,
        ?string $alternatives = null,
        ?string $distractors = null
    ): array {
        global $USER;

        $params = self::validate_parameters(
            self::execute_parameters(),
            compact(
                'itemid',
                'stem',
                'answers',
                'correcttrue',
                'feedbacktrue',
                'feedbackfalse',
                'modelanswer',
                'rubric',
                'fillanswer',
                'alternatives',
                'distractors'
            )
        );
        ['itemid' => $itemid, 'stem' => $stem, 'answers' => $answers] = $params;
        $extra = [
            'correct' => $params['correcttrue'],
            'feedbacktrue' => $params['feedbacktrue'],
            'feedbackfalse' => $params['feedbackfalse'],
            'modelanswer' => $params['modelanswer'],
            'rubric' => $params['rubric'],
            'answer' => $params['fillanswer'],
            'alternatives' => $params['alternatives'],
            'distractors' => $params['distractors'],
        ];

        [$item, $source, $context] = item_service::load_item($itemid, $USER->id);
        self::validate_context($context);

        return item_presenter::export(item_service::update_item($item, $source, $stem, $answers, $extra));
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return item_presenter::structure();
    }
}
