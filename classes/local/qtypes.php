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
 * The question types the plugin can generate, in the order they are generated and shown.
 *
 * One place knows which setting holds each type's count and which validator checks it.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtypes {
    /** @var string[] Type name to the job setting that holds how many to generate. */
    public const COUNT_KEYS = [
        'multichoice' => 'count',
        'truefalse' => 'tfcount',
        'essay' => 'essaycount',
        'multianswer' => 'clozecount',
        'gapselect' => 'gapcount',
    ];

    /**
     * Number of questions requested per type.
     *
     * @param array $settings The job settings.
     * @return int[] Type name to count; types with none requested have 0.
     */
    public static function counts(array $settings): array {
        $counts = [];
        foreach (self::COUNT_KEYS as $qtype => $key) {
            $counts[$qtype] = max(0, (int) ($settings[$key] ?? 0));
        }
        return $counts;
    }

    /**
     * Validate a draft of the given type.
     *
     * @param string $qtype The question type.
     * @param array $payload The decoded draft.
     * @param string $sourcetext The article text.
     * @return string[] Issue codes; empty if valid.
     */
    public static function validate(string $qtype, array $payload, string $sourcetext): array {
        return match ($qtype) {
            'multichoice' => mc_validator::validate($payload, $sourcetext),
            'truefalse' => tf_validator::validate($payload),
            'essay' => essay_validator::validate($payload),
            'multianswer' => fill_validator::validate_typed($payload, $sourcetext),
            'gapselect' => fill_validator::validate_select($payload, $sourcetext),
            default => ['badtype'],
        };
    }
}
