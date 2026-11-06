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

/**
 * Behat data generator for local_aiquestiongen.
 *
 * @package    local_aiquestiongen
 * @category   test
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Behat data generator for local_aiquestiongen.
 *
 * Use: And the following "local_aiquestiongen > drafts" exist:
 *
 * @package    local_aiquestiongen
 * @category   test
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_aiquestiongen_generator extends behat_generator_base {
    /**
     * Entities that can be created.
     *
     * @return array
     */
    protected function get_creatable_entities(): array {
        return [
            'drafts' => [
                'singular' => 'draft',
                'datagenerator' => 'draft',
                'required' => ['course', 'user', 'article', 'qtype', 'stem'],
                'switchids' => ['course' => 'courseid', 'user' => 'userid'],
            ],
        ];
    }
}
