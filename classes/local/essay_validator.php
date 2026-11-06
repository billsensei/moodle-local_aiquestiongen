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
 * Validates one AI-drafted open-ended (essay) question.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class essay_validator {
    /**
     * Validate a question.
     *
     * @param array $item The decoded question.
     * @return string[] Issue codes; empty if valid.
     */
    public static function validate(array $item): array {
        $issues = [];

        if (($item['type'] ?? '') !== 'essay') {
            $issues[] = 'badtype';
        }
        foreach (['stem' => 'nostem', 'modelanswer' => 'nomodelanswer', 'rubric' => 'norubric'] as $field => $code) {
            if (!isset($item[$field]) || !is_string($item[$field]) || trim($item[$field]) === '') {
                $issues[] = $code;
            }
        }
        if (!isset($item['focus']) || !in_array($item['focus'], mc_validator::FOCUSES, true)) {
            $issues[] = 'badfocus';
        }
        return $issues;
    }
}
