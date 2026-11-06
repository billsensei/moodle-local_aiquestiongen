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

namespace local_aiquestiongen\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_aiquestiongen\local\item_presenter;
use local_aiquestiongen\local\item_service;

/**
 * Web service: approve, reject or return one draft question to pending.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_item_status extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'Item id'),
            'status' => new external_value(PARAM_ALPHA, 'pending, approved or rejected'),
        ]);
    }

    /**
     * Change the status.
     *
     * @param int $itemid Item id.
     * @param string $status New status.
     * @return array The updated item.
     */
    public static function execute(int $itemid, string $status): array {
        global $USER;

        ['itemid' => $itemid, 'status' => $status] =
            self::validate_parameters(self::execute_parameters(), compact('itemid', 'status'));

        [$item, $source, $context] = item_service::load_item($itemid, $USER->id);
        self::validate_context($context);

        return item_presenter::export(item_service::set_status($item, $source, $context, $status));
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return item_presenter::structure();
    }
}
