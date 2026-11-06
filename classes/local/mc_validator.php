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
 * Validates one AI-drafted multiple-choice question.
 *
 * Each problem is returned as a short code. The codes map to the lang strings "issue_<code>",
 * so they can be shown to the teacher and reused in the retry prompt.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mc_validator {
    /** @var int Answers per question: one correct answer and three distractors. */
    public const ANSWER_COUNT = 4;

    /** @var string[] Allowed values for the "focus" field. */
    public const FOCUSES = ['vocabulary', 'grammar', 'comprehension'];

    /**
     * Validate a question.
     *
     * @param array $item The decoded question.
     * @param string $sourcetext The article text, used to check that a vocabulary target appears in it.
     * @return string[] Issue codes; empty if the question is valid.
     */
    public static function validate(array $item, string $sourcetext): array {
        $issues = [];

        if (($item['type'] ?? '') !== 'multichoice') {
            $issues[] = 'badtype';
        }

        if (!isset($item['stem']) || !is_string($item['stem']) || trim($item['stem']) === '') {
            $issues[] = 'nostem';
        }

        if (!isset($item['focus']) || !in_array($item['focus'], self::FOCUSES, true)) {
            $issues[] = 'badfocus';
        }

        $answers = $item['answers'] ?? null;
        if (!is_array($answers) || !array_is_list($answers) || count($answers) !== self::ANSWER_COUNT) {
            $issues[] = 'answercount';
            return $issues;
        }

        $correct = 0;
        $seen = [];
        $duplicate = false;
        $blank = false;
        foreach ($answers as $answer) {
            $text = is_array($answer) && isset($answer['text']) && is_string($answer['text'])
                ? trim($answer['text']) : '';
            if ($text === '') {
                $blank = true;
                continue;
            }
            if (is_array($answer) && ($answer['correct'] ?? false) === true) {
                $correct++;
            }
            $key = \core_text::strtolower($text);
            if (isset($seen[$key])) {
                $duplicate = true;
            }
            $seen[$key] = true;
        }

        if ($blank) {
            $issues[] = 'blankanswer';
        }
        if ($correct !== 1) {
            $issues[] = 'correctcount';
        }
        if ($duplicate) {
            $issues[] = 'duplicate';
        }

        // A vocabulary question must be about a word that is really in the article.
        if (($item['focus'] ?? '') === 'vocabulary') {
            $target = isset($item['target']) && is_string($item['target']) ? trim($item['target']) : '';
            $found = $target !== ''
                && \core_text::strpos(\core_text::strtolower($sourcetext), \core_text::strtolower($target)) !== false;
            if (!$found) {
                $issues[] = 'targetmissing';
            }
        }

        return $issues;
    }
}
