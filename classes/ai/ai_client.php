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
 * The one interface the rest of the plugin uses to talk to an AI backend.
 *
 * Mode A (core AI) is implemented by {@see core_ai_client}. A direct-API mode can be added in
 * a later phase by implementing this interface; nothing else in the plugin needs to change.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface ai_client {
    /**
     * Send a prompt and return the generated text.
     *
     * @param string $prompt The full prompt.
     * @return ai_result The generated text and usage details.
     * @throws ai_exception If no text could be generated.
     */
    public function generate_text(string $prompt): ai_result;
}
