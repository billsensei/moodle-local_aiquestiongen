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

namespace local_aiquestiongen;

/**
 * Tests for the file serving callback in lib.php.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class lib_test extends \advanced_testcase {
    /**
     * A teacher cannot fetch the file another teacher uploaded, even in the same course.
     */
    public function test_pluginfile_refuses_another_teachers_file(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/local/aiquestiongen/lib.php');
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        // Capture messages: the enrolment welcome message would otherwise set up the page theme too early for
        // require_login() to change the course.
        $sink = $this->redirectMessages();
        $owner = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $other = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $sink->close();

        $sourceid = $DB->insert_record('local_aiqg_source', (object) [
            'courseid' => $course->id,
            'userid' => $owner->id,
            'title' => 'Private article',
            'text' => 'Secret text.',
            'sourcefileitemid' => 0,
            'timecreated' => time(),
        ]);
        get_file_storage()->create_file_from_string([
            'contextid' => $context->id,
            'component' => 'local_aiquestiongen',
            'filearea' => 'source',
            'itemid' => $sourceid,
            'filepath' => '/',
            'filename' => 'article.txt',
        ], 'Secret text.');

        $this->setUser($other);
        $this->assertFalse(local_aiquestiongen_pluginfile($course, null, $context, 'source', [$sourceid, 'article.txt'], true));
        // A made-up or non-numeric item id gets the same answer, not an error.
        $this->assertFalse(local_aiquestiongen_pluginfile($course, null, $context, 'source', ['abc', 'article.txt'], true));
        // Other file areas and contexts are not served at all.
        $this->assertFalse(local_aiquestiongen_pluginfile($course, null, $context, 'other', [$sourceid, 'article.txt'], true));
        $this->assertFalse(local_aiquestiongen_pluginfile(
            $course,
            null,
            \context_system::instance(),
            'source',
            [$sourceid, 'article.txt'],
            true
        ));
    }
}
