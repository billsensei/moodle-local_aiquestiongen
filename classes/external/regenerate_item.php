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
use local_aiquestiongen\ai\client_factory;
use local_aiquestiongen\local\item_presenter;
use local_aiquestiongen\local\item_service;
use local_aiquestiongen\local\regenerator;

/**
 * Web service: replace one draft question with a new one, using the teacher's comment.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class regenerate_item extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'itemid' => new external_value(PARAM_INT, 'Item id'),
            'comment' => new external_value(PARAM_RAW, 'What should change', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Regenerate the draft.
     *
     * @param int $itemid Item id.
     * @param string $comment What should change.
     * @return array The updated item.
     */
    public static function execute(int $itemid, string $comment = ''): array {
        global $DB, $USER;

        ['itemid' => $itemid, 'comment' => $comment] =
            self::validate_parameters(self::execute_parameters(), compact('itemid', 'comment'));

        [$item, $source, $context] = item_service::load_item($itemid, $USER->id);
        self::validate_context($context);
        // Regenerating makes an AI request, so it stops when the plugin is switched off.
        if (!get_config('local_aiquestiongen', 'enabled')) {
            throw new \moodle_exception('disabled', 'local_aiquestiongen');
        }
        $job = $DB->get_record('local_aiquestiongen_job', ['id' => $item->jobid], '*', MUST_EXIST);

        // The AI request can take up to the configured timeout. Nothing below writes to the session, so release its
        // lock; otherwise the teacher's other pages would wait for the answer.
        \core\session\manager::write_close();

        $regenerator = new regenerator(client_factory::create($context, $USER->id));
        return item_presenter::export($regenerator->regenerate($item, $job, $source, $comment, (int) $USER->id));
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
