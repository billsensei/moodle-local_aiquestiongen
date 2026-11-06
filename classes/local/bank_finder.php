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

/**
 * Finds the question banks and categories a teacher may publish into.
 *
 * Since Moodle 5.0, course question banks are mod_qbank activity instances. This class reads them
 * straight from the database so that version differences in the question bank helper classes
 * stay out of the rest of the plugin.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class bank_finder {
    /**
     * List the banks in a course where the user may add questions, with their categories.
     *
     * @param int $courseid The course id.
     * @param int $userid The user.
     * @return array[] Each: cmid, contextid, name, categories (list of id, name).
     */
    public static function get_banks(int $courseid, int $userid): array {
        global $DB;

        $sql = "SELECT cm.id AS cmid, ctx.id AS contextid, qb.name
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'qbank'
                  JOIN {qbank} qb ON qb.id = cm.instance
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :ctxlevel
                 WHERE cm.course = :courseid AND cm.deletioninprogress = 0 AND qb.type <> 'preview'
              ORDER BY qb.name, cm.id";
        $records = $DB->get_records_sql($sql, ['ctxlevel' => CONTEXT_MODULE, 'courseid' => $courseid]);

        $banks = [];
        foreach ($records as $record) {
            $context = \context::instance_by_id($record->contextid);
            if (!has_capability('moodle/question:add', $context, $userid)) {
                continue;
            }
            $categories = $DB->get_records_select(
                'question_categories',
                'contextid = :contextid AND parent <> 0',
                ['contextid' => $record->contextid],
                'sortorder, name',
                'id, name'
            );
            $banks[] = [
                'cmid' => (int) $record->cmid,
                'contextid' => (int) $record->contextid,
                'name' => format_string($record->name, true, ['context' => $context]),
                'categories' => array_map(
                    fn($c) => ['id' => (int) $c->id, 'name' => format_string($c->name, true, ['context' => $context])],
                    array_values($categories)
                ),
            ];
        }
        return $banks;
    }

    /**
     * Select options for the publish form: each existing category, plus "new category" per bank.
     *
     * Keys are "cat:<categoryid>" or "new:<bank contextid>".
     *
     * @param int $courseid The course id.
     * @param int $userid The user.
     * @param string $newname The name a new category would get.
     * @return string[]
     */
    public static function get_options(int $courseid, int $userid, string $newname): array {
        $options = [];
        foreach (self::get_banks($courseid, $userid) as $bank) {
            $options['new:' . $bank['contextid']] = get_string('optionnewcategory', 'local_aiquestiongen', [
                'bank' => $bank['name'],
                'name' => $newname,
            ]);
            foreach ($bank['categories'] as $category) {
                $options['cat:' . $category['id']] = $bank['name'] . ' / ' . $category['name'];
            }
        }
        return $options;
    }
}
