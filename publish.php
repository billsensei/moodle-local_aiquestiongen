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
 * Publish a job's approved draft questions to a question bank category.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_aiquestiongen\form\publish_form;
use local_aiquestiongen\local\bank_finder;
use local_aiquestiongen\local\item_service;
use local_aiquestiongen\local\publisher;
use local_aiquestiongen\local\quiz_builder;

require_once(__DIR__ . '/../../config.php');

$jobid = required_param('id', PARAM_INT);

$job = $DB->get_record('local_aiquestiongen_job', ['id' => $jobid], '*', MUST_EXIST);
$source = $DB->get_record('local_aiquestiongen_source', ['id' => $job->sourceid], '*', MUST_EXIST);
$course = get_course($source->courseid);
require_login($course);
// Checks the use capability and that the article is the user's own.
[, , $context] = item_service::load_job($job->id, $USER->id);
require_capability('local/aiquestiongen:publish', $context);

$jobsurl = new moodle_url('/local/aiquestiongen/job.php', ['id' => $job->id]);
$url = new moodle_url('/local/aiquestiongen/publish.php', ['id' => $job->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('publishheading', 'local_aiquestiongen'));
$PAGE->set_heading($course->fullname);

$approved = $DB->count_records('local_aiquestiongen_item', ['jobid' => $job->id, 'status' => 'approved']);
$options = bank_finder::get_options($course->id, $USER->id, \core_text::substr(trim($source->title), 0, 255));

// Decide what can be shown before any output, because publishing ends with a redirect.
$problem = null;
if ($approved === 0) {
    $problem = [get_string('err_nothingapproved', 'local_aiquestiongen'), \core\output\notification::NOTIFY_INFO];
} else if (!$options) {
    $problem = [get_string('err_nobanks', 'local_aiquestiongen'), \core\output\notification::NOTIFY_WARNING];
}

$form = null;
$error = null;
if ($problem === null) {
    // Reading comprehension: true/false statements, and anything the model marked as a comprehension question.
    $hascomprehension = false;
    foreach ($DB->get_records('local_aiquestiongen_item', ['jobid' => $job->id, 'status' => 'approved']) as $approveditem) {
        $focus = json_decode($approveditem->payload, true)['focus'] ?? '';
        if ($approveditem->qtype === 'truefalse' || $focus === 'comprehension') {
            $hascomprehension = true;
            break;
        }
    }

    $form = new publish_form($url, [
        'jobid' => $job->id,
        'options' => $options,
        'count' => $approved,
        'hascomprehension' => $hascomprehension,
        'canaddquiz' => quiz_builder::can_create($course->id, $USER->id),
        'quizzes' => quiz_builder::get_quizzes($course->id, $USER->id),
        'quizname' => \core_text::substr(trim($source->title), 0, 255),
    ]);
    if ($form->is_cancelled()) {
        redirect($jobsurl);
    }

    if ($data = $form->get_data()) {
        try {
            $result = publisher::publish(
                $job,
                $source,
                $USER->id,
                $data->target,
                !empty($data->includepassage),
                $data->quiz ?? 'none'
            );
            $message = get_string('publishdone', 'local_aiquestiongen', $result['published']);
            if ($result['passage']) {
                $message .= ' ' . get_string('publishpassagedone', 'local_aiquestiongen');
            }
            if ($result['quizcmid']) {
                $message .= ' ' . get_string('publishquizdone', 'local_aiquestiongen', [
                    'count' => $result['quizadded'],
                    'name' => $result['quizname'],
                ]);
            }
            $type = \core\output\notification::NOTIFY_SUCCESS;
            if ($result['quizerror'] !== '') {
                $message .= ' ' . get_string('publishquizfailed', 'local_aiquestiongen', $result['quizerror']);
                $type = \core\output\notification::NOTIFY_WARNING;
            }
            if ($result['failed'] > 0) {
                $message .= ' ' . get_string('publishfailed', 'local_aiquestiongen', $result['failed']);
                $type = \core\output\notification::NOTIFY_WARNING;
            }
            redirect($jobsurl, $message, null, $type);
        } catch (moodle_exception $e) {
            $error = $e->getMessage();
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('publishheading', 'local_aiquestiongen'));

if ($problem !== null) {
    echo $OUTPUT->notification($problem[0], $problem[1]);
    echo $OUTPUT->continue_button($jobsurl);
    echo $OUTPUT->footer();
    exit;
}

echo html_writer::tag('p', get_string('publishintro', 'local_aiquestiongen', $approved));
if ($error !== null) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
$form->display();
echo $OUTPUT->footer();
