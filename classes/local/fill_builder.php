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
 * Turns a fill-in-the-blank draft into the text formats Moodle's question types expect.
 *
 * A draft has a stem with one blank (three or more underscores) and the word that belongs there.
 * Typed answers become a Cloze (multianswer) SHORTANSWER; chosen answers become a gapselect question.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class fill_builder {
    /** @var string Pattern for the blank in a stem: three or more underscores. */
    public const BLANK = '/_{3,}/';

    /**
     * Split a stem around its single blank.
     *
     * @param string $stem The stem.
     * @return string[]|null [text before, text after], or null unless there is exactly one blank.
     */
    public static function split_stem(string $stem): ?array {
        $parts = preg_split(self::BLANK, $stem);
        return count($parts) === 2 ? $parts : null;
    }

    /**
     * Cloze question text: the stem as HTML with the blank replaced by a SHORTANSWER.
     *
     * The first answer is marked fully correct, and so is each alternative. The only characters that need
     * escaping inside a Cloze answer are "}" and "#"; "~" cannot be escaped, so the validator rejects it.
     *
     * @param array $item The draft.
     * @return string|null HTML, or null if the stem has no single blank.
     */
    public static function cloze_text(array $item): ?string {
        $parts = self::split_stem((string) ($item['stem'] ?? ''));
        if ($parts === null) {
            return null;
        }

        $answers = array_merge([(string) ($item['answer'] ?? '')], self::clean_list($item['alternatives'] ?? []));
        $alternatives = array_map(
            fn($answer) => '=' . preg_replace('/([}#])/', '\\\\$1', $answer),
            $answers
        );
        $block = '{1:SHORTANSWER:' . implode('~', $alternatives) . '}';

        return '<p>' . s($parts[0]) . $block . s($parts[1]) . '</p>';
    }

    /**
     * Gapselect question text: the stem as HTML with the blank replaced by [[1]].
     *
     * @param array $item The draft.
     * @return string|null HTML, or null if the stem has no single blank.
     */
    public static function gapselect_text(array $item): ?string {
        $parts = self::split_stem((string) ($item['stem'] ?? ''));
        if ($parts === null) {
            return null;
        }
        return '<p>' . s($parts[0]) . '[[1]]' . s($parts[1]) . '</p>';
    }

    /**
     * Gapselect choices: the correct word first, so it is the answer to [[1]], then the distractors.
     *
     * @param array $item The draft.
     * @return array[] Rows for the form's "choices".
     */
    public static function gapselect_choices(array $item): array {
        $words = array_merge([(string) ($item['answer'] ?? '')], self::clean_list($item['distractors'] ?? []));
        return array_map(fn($word) => ['answer' => $word, 'choicegroup' => '1'], $words);
    }

    /**
     * Trim a list of strings and drop the empty ones.
     *
     * @param mixed $list A list from a draft.
     * @return string[]
     */
    public static function clean_list(mixed $list): array {
        if (!is_array($list)) {
            return [];
        }
        $out = [];
        foreach ($list as $value) {
            if (is_string($value) && trim($value) !== '') {
                $out[] = trim($value);
            }
        }
        return $out;
    }

    /**
     * Check that Moodle's own Cloze parser reads the text back as the intended answers.
     *
     * @param string $cloze The Cloze HTML from {@see cloze_text()}.
     * @param string[] $expected The answers it should contain, in order.
     * @return bool
     */
    public static function cloze_parses(string $cloze, array $expected): bool {
        global $CFG;
        require_once($CFG->dirroot . '/question/type/multianswer/questiontype.php');

        $question = qtype_multianswer_extract_question(['text' => $cloze, 'format' => FORMAT_HTML, 'itemid' => '']);
        if (!$question || count($question->options->questions) !== 1 || empty($question->options->questions[1])) {
            return false;
        }
        $sub = $question->options->questions[1];
        if ($sub->qtype !== 'shortanswer') {
            return false;
        }
        $parsed = array_values(array_map(fn($a) => is_array($a) ? $a['text'] : $a, (array) $sub->answer));
        return $parsed === array_values($expected);
    }
}
