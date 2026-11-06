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

namespace local_aiquestiongen\ai;

/**
 * The outcome of one successful AI call.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class ai_result {
    /**
     * Constructor.
     *
     * @param string $text The generated text.
     * @param string $model The model or provider that produced it, or an empty string if unknown.
     * @param int $tokens Total tokens used, or 0 if the provider did not report them.
     */
    public function __construct(
        /** @var string The generated text. */
        public readonly string $text,
        /** @var string The model or provider that produced it. */
        public readonly string $model = '',
        /** @var int Total tokens used. */
        public readonly int $tokens = 0,
    ) {
    }
}
