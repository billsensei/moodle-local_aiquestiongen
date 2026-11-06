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
 * Upload page: paste or upload a source article for question generation.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_aiquestiongen\form\source_form;
use local_aiquestiongen\local\text_extractor;

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('id', PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('local/aiquestiongen:use', $context);

if (!get_config('local_aiquestiongen', 'enabled')) {
    throw new moodle_exception('disabled', 'local_aiquestiongen', new moodle_url('/course/view.php', ['id' => $course->id]));
}

$url = new moodle_url('/local/aiquestiongen/index.php', ['id' => $course->id]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('generatequestions', 'local_aiquestiongen'));
$PAGE->set_heading($course->fullname);

$form = new source_form($url, ['courseid' => $course->id]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

$error = null;
if ($data = $form->get_data()) {
    try {
        $sourcefileitemid = 0;
        $draftfile = null;
        $text = trim((string) $data->articletext);

        if ($text === '') {
            // The form guarantees exactly one of text or file; the file is in the user draft area.
            $usercontext = context_user::instance($USER->id);
            $drafts = get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $data->sourcefile, 'id', false);
            $draftfile = reset($drafts);
            if (!$draftfile) {
                throw new moodle_exception('err_notextorfile', 'local_aiquestiongen');
            }
            $text = text_extractor::extract_from_file($draftfile);
        } else {
            $text = text_extractor::normalise_text($text);
        }

        if ($text === '') {
            throw new moodle_exception('err_emptytext', 'local_aiquestiongen');
        }
        $maxlength = (int) get_config('local_aiquestiongen', 'maxarticlelength');
        if ($maxlength > 0 && core_text::strlen($text) > $maxlength) {
            throw new moodle_exception('err_textlong', 'local_aiquestiongen', '', $maxlength);
        }

        $record = (object) [
            'courseid' => $course->id,
            'userid' => $USER->id,
            'title' => $data->title,
            'text' => $text,
            'sourcefileitemid' => 0,
            'timecreated' => time(),
        ];
        $record->id = $DB->insert_record('local_aiquestiongen_source', $record);

        if ($draftfile) {
            file_save_draft_area_files(
                $data->sourcefile,
                $context->id,
                'local_aiquestiongen',
                'source',
                $record->id,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
            $DB->set_field('local_aiquestiongen_source', 'sourcefileitemid', $record->id, ['id' => $record->id]);
        }

        redirect(
            new moodle_url('/local/aiquestiongen/generate.php', ['sourceid' => $record->id]),
            get_string('sourcesaved', 'local_aiquestiongen'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (moodle_exception $e) {
        $error = $e->getMessage();
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('uploadheading', 'local_aiquestiongen'));
if ($error !== null) {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}
$form->display();
echo $OUTPUT->footer();
