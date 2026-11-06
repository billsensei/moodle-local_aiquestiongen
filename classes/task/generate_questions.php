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

namespace local_aiquestiongen\task;

use local_aiquestiongen\ai\client_factory;
use local_aiquestiongen\local\generator;

/**
 * Ad hoc task that runs one question generation job in the background.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_questions extends \core\task\adhoc_task {
    #[\Override]
    public function get_name(): string {
        return get_string('task_generatequestions', 'local_aiquestiongen');
    }

    #[\Override]
    public function execute(): void {
        global $DB;

        $jobid = (int) ($this->get_custom_data()->jobid ?? 0);
        $job = $DB->get_record('local_aiquestiongen_job', ['id' => $jobid]);
        // The job may have been deleted, or already handled by an earlier run of this task.
        if (!$job || $job->status !== 'queued') {
            return;
        }

        // An administrator may switch the plugin off while jobs are waiting; no AI request may be made then.
        if (!get_config('local_aiquestiongen', 'enabled')) {
            $DB->update_record('local_aiquestiongen_job', (object) [
                'id' => $job->id,
                'status' => 'failed',
                'error' => get_string('disabled', 'local_aiquestiongen'),
            ]);
            return;
        }

        $source = $DB->get_record('local_aiquestiongen_source', ['id' => $job->sourceid], '*', MUST_EXIST);
        $context = \context_course::instance($source->courseid);

        $generator = new generator(client_factory::create($context, (int) $source->userid));
        $generator->run($jobid);
    }
}
