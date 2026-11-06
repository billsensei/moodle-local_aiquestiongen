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

use local_aiquestiongen\task\generate_questions;

/**
 * Creates generation jobs and enforces the admin limits.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class job_manager {
    /**
     * Create a job for a source article and queue it to run in the background.
     *
     * @param \stdClass $source The local_aiqg_source record.
     * @param int $userid The user starting the job.
     * @param array $settings Requested settings: cefr, target, language and one count per question type (see qtypes::COUNT_KEYS).
     * @return int The new job id.
     * @throws \moodle_exception If the daily limit is reached or the settings are invalid.
     */
    public static function create_job(\stdClass $source, int $userid, array $settings): int {
        global $DB;

        $dailylimit = (int) get_config('local_aiquestiongen', 'dailyjoblimit');
        if ($dailylimit > 0 && self::jobs_started_today($userid) >= $dailylimit) {
            throw new \moodle_exception('err_dailylimit', 'local_aiquestiongen', '', $dailylimit);
        }

        // The limit applies to all question types together.
        $max = self::max_questions();
        $counts = qtypes::counts($settings);
        $total = array_sum($counts);
        if ($total < 1 || $total > $max) {
            throw new \moodle_exception('err_count', 'local_aiquestiongen', '', $max);
        }

        $cefr = $settings['cefr'] ?? '';
        if (!in_array($cefr, prompt_builder::CEFR_LEVELS, true)) {
            throw new \moodle_exception('err_cefr', 'local_aiquestiongen');
        }

        $clean = [
            'cefr' => $cefr,
            'target' => trim((string) ($settings['target'] ?? '')),
            'language' => trim((string) ($settings['language'] ?? '')) ?: 'English',
            'qtypes' => array_keys(array_filter($counts)),
        ];

        // One count per question type, under the setting name the registry gives it.
        foreach (qtypes::COUNT_KEYS as $qtype => $key) {
            $clean[$key] = $counts[$qtype];
        }

        $jobid = $DB->insert_record('local_aiqg_job', (object) [
            'sourceid' => $source->id,
            'settings' => json_encode($clean, JSON_UNESCAPED_UNICODE),
            'status' => 'queued',
            'error' => null,
            'provider' => null,
            'tokensused' => 0,
            'timecreated' => time(),
        ]);

        $task = new generate_questions();
        $task->set_custom_data(['jobid' => $jobid]);
        $task->set_userid($userid);
        $task->set_component('local_aiquestiongen');
        \core\task\manager::queue_adhoc_task($task);

        return $jobid;
    }

    /**
     * The largest number of questions one job may request.
     *
     * @return int
     */
    public static function max_questions(): int {
        $max = (int) get_config('local_aiquestiongen', 'maxquestionsperjob');
        return $max > 0 ? $max : 20;
    }

    /**
     * How many jobs a user has started since midnight (in their timezone).
     *
     * @param int $userid The user id.
     * @return int
     */
    public static function jobs_started_today(int $userid): int {
        global $DB;

        return $DB->count_records_sql(
            "SELECT COUNT(j.id)
               FROM {local_aiqg_job} j
               JOIN {local_aiqg_source} s ON s.id = j.sourceid
              WHERE s.userid = :userid AND j.timecreated >= :since",
            ['userid' => $userid, 'since' => usergetmidnight(time())]
        );
    }
}
