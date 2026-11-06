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
 * Validates AI-drafted fill-in-the-blank questions, typed (Cloze) and chosen (gapselect).
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fill_validator {
    /** @var int Distractors for a gapselect question. */
    public const DISTRACTOR_COUNT = 3;

    /**
     * Validate a typed-answer (Cloze) question.
     *
     * @param array $item The decoded question.
     * @param string $sourcetext The article text.
     * @return string[] Issue codes; empty if valid.
     */
    public static function validate_typed(array $item, string $sourcetext): array {
        $issues = self::common($item, 'multianswer', $sourcetext);

        $alternatives = $item['alternatives'] ?? [];
        if (!is_array($alternatives) || !array_is_list($alternatives)) {
            $issues[] = 'badalternatives';
            return array_values(array_unique($issues));
        }
        $answers = array_merge([trim((string) ($item['answer'] ?? ''))], fill_builder::clean_list($alternatives));

        foreach ($answers as $answer) {
            // A tilde would split the answer, and an asterisk is a wildcard in short answers.
            if (preg_match('/[~*\r\n]/', $answer)) {
                $issues[] = 'badanswerchars';
                break;
            }
        }

        // Only try the Cloze parser when the pieces it needs are in good shape.
        $blockers = ['noblank', 'noanswer', 'badstemchars', 'badanswerchars'];
        if (!array_intersect($blockers, $issues)) {
            $cloze = fill_builder::cloze_text($item);
            if ($cloze === null || !fill_builder::cloze_parses($cloze, $answers)) {
                $issues[] = 'clozesyntax';
            }
        }
        return array_values(array_unique($issues));
    }

    /**
     * Validate a choose-from-a-list (gapselect) question.
     *
     * @param array $item The decoded question.
     * @param string $sourcetext The article text.
     * @return string[] Issue codes; empty if valid.
     */
    public static function validate_select(array $item, string $sourcetext): array {
        $issues = self::common($item, 'gapselect', $sourcetext);

        $distractors = $item['distractors'] ?? null;
        $goodlist = is_array($distractors) && array_is_list($distractors)
            && count($distractors) === self::DISTRACTOR_COUNT
            && count(fill_builder::clean_list($distractors)) === self::DISTRACTOR_COUNT;
        if (!$goodlist) {
            $issues[] = 'distractorcount';
            return array_values(array_unique($issues));
        }

        $seen = [];
        foreach (array_merge([(string) ($item['answer'] ?? '')], $distractors) as $word) {
            $key = \core_text::strtolower(trim((string) $word));
            if ($key === '') {
                continue;
            }
            if (isset($seen[$key])) {
                $issues[] = 'duplicate';
                break;
            }
            $seen[$key] = true;
        }
        return array_values(array_unique($issues));
    }

    /**
     * Checks shared by both fill-in-the-blank types.
     *
     * @param array $item The decoded question.
     * @param string $qtype The expected type.
     * @param string $sourcetext The article text.
     * @return string[] Issue codes.
     */
    private static function common(array $item, string $qtype, string $sourcetext): array {
        $issues = [];

        if (($item['type'] ?? '') !== $qtype) {
            $issues[] = 'badtype';
        }
        if (!isset($item['focus']) || !in_array($item['focus'], mc_validator::FOCUSES, true)) {
            $issues[] = 'badfocus';
        }

        $stem = isset($item['stem']) && is_string($item['stem']) ? $item['stem'] : '';
        if (trim($stem) === '') {
            $issues[] = 'nostem';
        } else if (fill_builder::split_stem($stem) === null) {
            $issues[] = 'noblank';
        }
        // These would be read as question-format markup inside the stem.
        if (preg_match('/[{}]|\[\[|\]\]/', $stem)) {
            $issues[] = 'badstemchars';
        }

        $answer = isset($item['answer']) && is_string($item['answer']) ? trim($item['answer']) : '';
        if ($answer === '') {
            $issues[] = 'noanswer';
        } else if (($item['focus'] ?? '') === 'vocabulary') {
            // A vocabulary blank should be a word from the article.
            $found = \core_text::strpos(\core_text::strtolower($sourcetext), \core_text::strtolower($answer)) !== false;
            if (!$found) {
                $issues[] = 'targetmissing';
            }
        }
        return $issues;
    }
}
