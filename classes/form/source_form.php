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

namespace local_aiquestiongen\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for uploading or pasting a source article.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class source_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        global $OUTPUT;

        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['courseid'] ?? 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('html', $OUTPUT->notification(
            get_string('copyrightnotice', 'local_aiquestiongen'),
            \core\output\notification::NOTIFY_INFO,
            false
        ));

        $mform->addElement('text', 'title', get_string('articletitle', 'local_aiquestiongen'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform->addElement('textarea', 'articletext', get_string('articletext', 'local_aiquestiongen'), [
            'rows' => 12,
            'cols' => 80,
        ]);
        $mform->setType('articletext', PARAM_RAW);
        $mform->addHelpButton('articletext', 'articletext', 'local_aiquestiongen');

        $mform->addElement('filemanager', 'sourcefile', get_string('sourcefile', 'local_aiquestiongen'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'maxbytes' => get_max_upload_file_size(),
            'accepted_types' => ['.txt', '.docx', '.pdf', '.html', '.htm'],
        ]);
        $mform->addHelpButton('sourcefile', 'sourcefile', 'local_aiquestiongen');

        $this->add_action_buttons(true, get_string('savesource', 'local_aiquestiongen'));
    }

    /**
     * Require exactly one of pasted text or an uploaded file.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        global $USER;

        $hastext = trim($data['articletext'] ?? '') !== '';

        // The file manager submits the id of a draft area. Look in it directly: moodleform::get_draft_files()
        // raises a PHP warning inside the form library for this kind of element.
        $hasfile = false;
        $draftid = (int) ($data['sourcefile'] ?? 0);
        if ($draftid) {
            $usercontext = \context_user::instance($USER->id);
            $hasfile = (bool) get_file_storage()->get_area_files($usercontext->id, 'user', 'draft', $draftid, 'id', false);
        }

        if (!$hastext && !$hasfile) {
            $errors['articletext'] = get_string('err_notextorfile', 'local_aiquestiongen');
        } else if ($hastext && $hasfile) {
            $errors['articletext'] = get_string('err_textorfile', 'local_aiquestiongen');
        }

        return $errors;
    }
}
