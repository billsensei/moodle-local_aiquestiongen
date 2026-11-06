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
 * Chooses the AI client for a request.
 *
 * Mode A, core AI, is the default. Mode B calls a provider's API directly, and is chosen in the plugin settings.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class client_factory {
    /**
     * Create the client for the given context and user.
     *
     * @param \context $context The course context.
     * @param int $userid The user the request is made for.
     * @return ai_client
     */
    public static function create(\context $context, int $userid): ai_client {
        if (get_config('local_aiquestiongen', 'aimode') === 'direct') {
            return direct_api_client::from_config();
        }
        return new core_ai_client($context->id, $userid);
    }
}
