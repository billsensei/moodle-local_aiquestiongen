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
 * Job status page and, for a finished job, the review page where drafts are edited, approved or rejected.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$jobid = required_param('id', PARAM_INT);

$job = $DB->get_record('local_aiquestiongen_job', ['id' => $jobid], '*', MUST_EXIST);
$source = $DB->get_record('local_aiquestiongen_source', ['id' => $job->sourceid], '*', MUST_EXIST);
$course = get_course($source->courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/aiquestiongen:use', $context);
if ((int) $source->userid !== (int) $USER->id) {
    throw new moodle_exception('err_notowner', 'local_aiquestiongen');
}

$url = new moodle_url('/local/aiquestiongen/job.php', ['id' => $job->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('jobheading', 'local_aiquestiongen'));
$PAGE->set_heading($course->fullname);

$active = in_array($job->status, ['queued', 'running'], true);
if ($active) {
    // Reload every few seconds until the background task finishes.
    $PAGE->requires->js_init_code('window.setTimeout(function() { window.location.reload(); }, 5000);');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('jobheading', 'local_aiquestiongen'));
echo html_writer::tag('p', get_string('generatingfrom', 'local_aiquestiongen', format_string($source->title)));
$statusname = get_string('status_' . $job->status, 'local_aiquestiongen');
echo html_writer::tag('p', get_string('jobstatus', 'local_aiquestiongen', $statusname));

if ($active) {
    echo $OUTPUT->notification(get_string('jobwaiting', 'local_aiquestiongen'), \core\output\notification::NOTIFY_INFO);
}
if ($job->status === 'done' && !empty($job->error)) {
    // Some question types worked and others did not.
    echo $OUTPUT->notification(
        get_string('partialfailure', 'local_aiquestiongen', s($job->error)),
        \core\output\notification::NOTIFY_WARNING
    );
}
if ($job->status === 'failed') {
    echo $OUTPUT->notification(s($job->error), \core\output\notification::NOTIFY_ERROR);
    echo $OUTPUT->single_button(
        new moodle_url('/local/aiquestiongen/generate.php', ['sourceid' => $source->id]),
        get_string('tryagain', 'local_aiquestiongen')
    );
}

if ($job->status === 'done') {
    $records = $DB->get_records('local_aiquestiongen_item', ['jobid' => $job->id], 'id ASC');

    $groups = [];
    $exported = [];
    $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'flagged' => 0];
    $number = 0;
    foreach ($records as $record) {
        $number++;
        $item = local_aiquestiongen\local\item_presenter::export($record);
        $exported[] = $item;

        if (isset($counts[$item['status']])) {
            $counts[$item['status']]++;
        }
        if ($item['flagged'] && $item['ispending']) {
            $counts['flagged']++;
        }

        $groups[$item['qtype']] ??= [
            'qtype' => $item['qtype'],
            'heading' => get_string('group_' . $item['qtype'], 'local_aiquestiongen'),
            'items' => [],
        ];
        $groups[$item['qtype']]['items'][] = $item + ['number' => $number, 'editing' => false];
    }

    echo $OUTPUT->heading(get_string('draftsheading', 'local_aiquestiongen', count($records)), 3);
    if (has_capability('local/aiquestiongen:publish', $context)) {
        echo $OUTPUT->single_button(
            new moodle_url('/local/aiquestiongen/publish.php', ['id' => $job->id]),
            get_string('publishlink', 'local_aiquestiongen'),
            'get'
        );
    }
    echo $OUTPUT->render_from_template('local_aiquestiongen/review', [
        'jobid' => $job->id,
        'sourcetitle' => format_string($source->title),
        'sourcetext' => $source->text,
        'groups' => array_values($groups),
        // The drafts for the JavaScript. A data attribute, because js_call_amd arguments should stay small.
        'itemsjson' => json_encode($exported),
    ] + $counts);
    $PAGE->requires->js_call_amd('local_aiquestiongen/review', 'init', [(int) $job->id]);
}

echo $OUTPUT->footer();
