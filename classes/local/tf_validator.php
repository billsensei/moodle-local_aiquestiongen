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
 * Validates one AI-drafted true/false question.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tf_validator {
    /**
     * Validate a question.
     *
     * @param array $item The decoded question.
     * @return string[] Issue codes; empty if valid.
     */
    public static function validate(array $item): array {
        $issues = [];

        if (($item['type'] ?? '') !== 'truefalse') {
            $issues[] = 'badtype';
        }
        if (!isset($item['stem']) || !is_string($item['stem']) || trim($item['stem']) === '') {
            $issues[] = 'nostem';
        }
        // Only a real boolean counts, so the string "false" cannot be mistaken for true.
        if (!isset($item['correct']) || !is_bool($item['correct'])) {
            $issues[] = 'nocorrect';
        }
        if (!isset($item['focus']) || !in_array($item['focus'], mc_validator::FOCUSES, true)) {
            $issues[] = 'badfocus';
        }
        return $issues;
    }
}
