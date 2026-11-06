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

namespace local_aiquestiongen\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_aiquestiongen.
 *
 * Source articles are owned by the uploading user and live in the course context.
 * Jobs and items hold no user id of their own and are reached through their source.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the data stored and the external location it is sent to.
     *
     * @param collection $collection The collection to add to.
     * @return collection The updated collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_aiqg_source', [
            'courseid' => 'privacy:metadata:local_aiqg_source:courseid',
            'userid' => 'privacy:metadata:local_aiqg_source:userid',
            'title' => 'privacy:metadata:local_aiqg_source:title',
            'text' => 'privacy:metadata:local_aiqg_source:text',
            'sourcefileitemid' => 'privacy:metadata:local_aiqg_source:sourcefileitemid',
            'timecreated' => 'privacy:metadata:local_aiqg_source:timecreated',
        ], 'privacy:metadata:local_aiqg_source');

        $collection->add_database_table('local_aiqg_job', [
            'sourceid' => 'privacy:metadata:local_aiqg_job:sourceid',
            'settings' => 'privacy:metadata:local_aiqg_job:settings',
            'status' => 'privacy:metadata:local_aiqg_job:status',
            'error' => 'privacy:metadata:local_aiqg_job:error',
            'provider' => 'privacy:metadata:local_aiqg_job:provider',
            'tokensused' => 'privacy:metadata:local_aiqg_job:tokensused',
            'timecreated' => 'privacy:metadata:local_aiqg_job:timecreated',
        ], 'privacy:metadata:local_aiqg_job');

        $collection->add_database_table('local_aiqg_item', [
            'jobid' => 'privacy:metadata:local_aiqg_item:jobid',
            'qtype' => 'privacy:metadata:local_aiqg_item:qtype',
            'payload' => 'privacy:metadata:local_aiqg_item:payload',
            'status' => 'privacy:metadata:local_aiqg_item:status',
            'questionid' => 'privacy:metadata:local_aiqg_item:questionid',
            'timemodified' => 'privacy:metadata:local_aiqg_item:timemodified',
        ], 'privacy:metadata:local_aiqg_item');

        $collection->add_subsystem_link('core_files', [], 'privacy:metadata:core_files');

        // Article text is sent to the AI provider configured in Moodle (or directly by the plugin).
        $collection->add_external_location_link('aiprovider', [
            'articletext' => 'privacy:metadata:aiprovider:articletext',
            'prompt' => 'privacy:metadata:aiprovider:prompt',
        ], 'privacy:metadata:aiprovider');

        return $collection;
    }

    /**
     * Get the course contexts holding source articles uploaded by the user.
     *
     * @param int $userid The user id.
     * @return contextlist The contexts.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {local_aiqg_source} s ON s.courseid = ctx.instanceid
                 WHERE ctx.contextlevel = :contextlevel AND s.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    /**
     * Get the users with data in a context.
     *
     * @param userlist $userlist The userlist to add to.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $sql = "SELECT s.userid FROM {local_aiqg_source} s WHERE s.courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    /**
     * Export the user's data for the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if ($contextlist->count() === 0) {
            return;
        }
        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $sources = $DB->get_records('local_aiqg_source', ['courseid' => $context->instanceid, 'userid' => $userid]);
            foreach ($sources as $source) {
                $jobs = [];
                foreach ($DB->get_records('local_aiqg_job', ['sourceid' => $source->id]) as $job) {
                    $items = [];
                    foreach ($DB->get_records('local_aiqg_item', ['jobid' => $job->id]) as $item) {
                        $items[] = (object) [
                            'qtype' => $item->qtype,
                            'payload' => $item->payload,
                            'status' => $item->status,
                            'questionid' => $item->questionid,
                            'timemodified' => \core_privacy\local\request\transform::datetime($item->timemodified),
                        ];
                    }
                    $jobs[] = (object) [
                        'settings' => $job->settings,
                        'status' => $job->status,
                        'error' => $job->error,
                        'provider' => $job->provider,
                        'tokensused' => $job->tokensused,
                        'timecreated' => \core_privacy\local\request\transform::datetime($job->timecreated),
                        'items' => $items,
                    ];
                }

                $subcontext = [
                    get_string('pluginname', 'local_aiquestiongen'),
                    $source->id . '-' . clean_param($source->title, PARAM_FILE),
                ];
                writer::with_context($context)->export_data($subcontext, (object) [
                    'title' => $source->title,
                    'text' => $source->text,
                    'timecreated' => \core_privacy\local\request\transform::datetime($source->timecreated),
                    'jobs' => $jobs,
                ]);
                writer::with_context($context)->export_area_files(
                    $subcontext,
                    'local_aiquestiongen',
                    'source',
                    $source->id
                );
            }
        }
    }

    /**
     * Delete all data in a context.
     *
     * @param \context $context The context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }
        $sourceids = $DB->get_fieldset_select('local_aiqg_source', 'id', 'courseid = :courseid', [
            'courseid' => $context->instanceid,
        ]);
        self::delete_sources($context, $sourceids);
    }

    /**
     * Delete a user's data in the approved contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_COURSE) {
                continue;
            }
            $sourceids = $DB->get_fieldset_select(
                'local_aiqg_source',
                'id',
                'courseid = :courseid AND userid = :userid',
                ['courseid' => $context->instanceid, 'userid' => $userid]
            );
            self::delete_sources($context, $sourceids);
        }
    }

    /**
     * Delete data for several users in one context.
     *
     * @param approved_userlist $userlist The approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        $userids = $userlist->get_userids();
        if ($context->contextlevel != CONTEXT_COURSE || empty($userids)) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $sourceids = $DB->get_fieldset_select('local_aiqg_source', 'id', "courseid = :courseid AND userid $insql", $params);
        self::delete_sources($context, $sourceids);
    }

    /**
     * Delete sources together with their jobs, items and files.
     *
     * @param \context $context The course context the sources belong to.
     * @param int[] $sourceids Source ids.
     */
    private static function delete_sources(\context $context, array $sourceids): void {
        global $DB;

        if (empty($sourceids)) {
            return;
        }
        $fs = get_file_storage();
        [$sourcesql, $sourceparams] = $DB->get_in_or_equal($sourceids, SQL_PARAMS_NAMED);
        $jobids = $DB->get_fieldset_select('local_aiqg_job', 'id', "sourceid $sourcesql", $sourceparams);
        if (!empty($jobids)) {
            [$jobsql, $jobparams] = $DB->get_in_or_equal($jobids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_aiqg_item', "jobid $jobsql", $jobparams);
            $DB->delete_records_select('local_aiqg_job', "id $jobsql", $jobparams);
        }
        foreach ($sourceids as $sourceid) {
            $fs->delete_area_files($context->id, 'local_aiquestiongen', 'source', $sourceid);
        }
        $DB->delete_records_select('local_aiqg_source', "id $sourcesql", $sourceparams);
    }
}
