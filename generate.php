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
 * Choose generation settings for a saved article and queue the job.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_aiquestiongen\form\generate_form;
use local_aiquestiongen\local\job_manager;
use local_aiquestiongen\local\qtypes;

require_once(__DIR__ . '/../../config.php');

$sourceid = required_param('sourceid', PARAM_INT);

$source = $DB->get_record('local_aiquestiongen_source', ['id' => $sourceid], '*', MUST_EXIST);
$course = get_course($source->courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/aiquestiongen:use', $context);

// Articles belong to the teacher who added them.
if ((int) $source->userid !== (int) $USER->id) {
    throw new moodle_exception('err_notowner', 'local_aiquestiongen');
}
if (!get_config('local_aiquestiongen', 'enabled')) {
    throw new moodle_exception('disabled', 'local_aiquestiongen', new moodle_url('/course/view.php', ['id' => $course->id]));
}

$url = new moodle_url('/local/aiquestiongen/generate.php', ['sourceid' => $source->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('generateheading', 'local_aiquestiongen'));
$PAGE->set_heading($course->fullname);

$form = new generate_form($url, ['sourceid' => $source->id]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/aiquestiongen/index.php', ['id' => $course->id]));
}

$error = null;
if ($data = $form->get_data()) {
    try {
        $settings = ['cefr' => $data->cefr, 'target' => $data->target, 'language' => $data->language];
        foreach (qtypes::COUNT_KEYS as $field) {
            $settings[$field] = $data->$field;
        }
        $jobid = job_manager::create_job($source, $USER->id, $settings);
        redirect(new moodle_url('/local/aiquestiongen/job.php', ['id' => $jobid]));
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('generateheading', 'local_aiquestiongen'));
echo html_writer::tag('p', get_string('generatingfrom', 'local_aiquestiongen', format_string($source->title)));
if ($error !== null) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
$form->display();
echo $OUTPUT->footer();
