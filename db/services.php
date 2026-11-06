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
 * Web service (AJAX) functions for the review page.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_aiquestiongen_update_item' => [
        'classname' => \local_aiquestiongen\external\update_item::class,
        'description' => 'Save edits to a draft question.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/aiquestiongen:use',
    ],
    'local_aiquestiongen_set_item_status' => [
        'classname' => \local_aiquestiongen\external\set_item_status::class,
        'description' => 'Approve, reject or un-decide one draft question.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/aiquestiongen:use',
    ],
    'local_aiquestiongen_regenerate_item' => [
        'classname' => \local_aiquestiongen\external\regenerate_item::class,
        'description' => 'Replace one draft question with a new one, using the teacher\'s comment.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/aiquestiongen:use',
    ],
    'local_aiquestiongen_set_items_status' => [
        'classname' => \local_aiquestiongen\external\set_items_status::class,
        'description' => 'Approve or reject all pending drafts of one type in a job.',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'local/aiquestiongen:use',
    ],
];
