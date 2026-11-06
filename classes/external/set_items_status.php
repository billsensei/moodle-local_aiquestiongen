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
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_aiquestiongen\local\item_presenter;
use local_aiquestiongen\local\item_service;

/**
 * Web service: approve all or reject all pending drafts of one question type in a job.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class set_items_status extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_INT, 'Job id'),
            'qtype' => new external_value(PARAM_ALPHANUMEXT, 'Question type'),
            'status' => new external_value(PARAM_ALPHA, 'approved or rejected'),
        ]);
    }

    /**
     * Change the status of all pending drafts of a type.
     *
     * @param int $jobid Job id.
     * @param string $qtype Question type.
     * @param string $status New status.
     * @return array The changed items and the number skipped.
     */
    public static function execute(int $jobid, string $qtype, string $status): array {
        global $USER;

        ['jobid' => $jobid, 'qtype' => $qtype, 'status' => $status] =
            self::validate_parameters(self::execute_parameters(), compact('jobid', 'qtype', 'status'));

        [$job, $source, $context] = item_service::load_job($jobid, $USER->id);
        self::validate_context($context);

        [$changed, $skipped] = item_service::set_status_bulk($job, $source, $context, $qtype, $status);
        return [
            'items' => array_map([item_presenter::class, 'export'], $changed),
            'skipped' => $skipped,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(item_presenter::structure()),
            'skipped' => new external_value(PARAM_INT, 'Pending drafts left alone because they need checking'),
        ]);
    }
}
