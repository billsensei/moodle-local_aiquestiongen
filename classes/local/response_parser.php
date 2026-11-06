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
 * Turns the raw text an AI model returns into a list of question arrays.
 *
 * Models often wrap JSON in markdown fences or add a sentence before it, so this is forgiving
 * about the wrapper but strict about the result: it returns only arrays, never partial guesses.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_parser {
    /**
     * Parse a model response.
     *
     * Accepts a JSON array of questions, a single question object, or an object with a
     * "questions" array.
     *
     * @param string $text The raw model output.
     * @return array[]|null A list of question arrays, or null if nothing usable was found.
     */
    public static function parse(string $text): ?array {
        $decoded = self::decode($text);
        if (!is_array($decoded)) {
            return null;
        }

        if (isset($decoded['questions']) && is_array($decoded['questions'])) {
            $decoded = $decoded['questions'];
        } else if (!array_is_list($decoded)) {
            // A single question object.
            $decoded = [$decoded];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (is_array($item) && !array_is_list($item)) {
                $items[] = $item;
            }
        }
        return $items === [] ? null : $items;
    }

    /**
     * Decode JSON from model output, tolerating fences and surrounding prose.
     *
     * @param string $text The raw model output.
     * @return mixed The decoded value, or null.
     */
    private static function decode(string $text): mixed {
        $text = trim($text);

        // Strip a markdown code fence if present.
        if (preg_match('/\x60{3}(?:json)?\s*(.*?)\x60{3}/is', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Fall back to the outermost array or object in the text.
        foreach (['[' => ']', '{' => '}'] as $open => $close) {
            $start = strpos($text, $open);
            $end = strrpos($text, $close);
            if ($start !== false && $end !== false && $end > $start) {
                $decoded = json_decode(substr($text, $start, $end - $start + 1), true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }
        return null;
    }
}
