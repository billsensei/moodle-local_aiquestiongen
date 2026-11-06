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

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Form for choosing where to publish approved questions.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publish_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id', $this->_customdata['jobid'] ?? 0);
        $mform->setType('id', PARAM_INT);

        $mform->addElement('select', 'target', get_string('publishtarget', 'local_aiquestiongen'), $this->_customdata['options']);
        $mform->addRule('target', null, 'required', null, 'client');
        $mform->addHelpButton('target', 'publishtarget', 'local_aiquestiongen');

        $quizoptions = ['none' => get_string('quiznone', 'local_aiquestiongen')];
        if (!empty($this->_customdata['canaddquiz'])) {
            $quizoptions['new'] = get_string('quiznew', 'local_aiquestiongen', $this->_customdata['quizname'] ?? '');
        }
        foreach (($this->_customdata['quizzes'] ?? []) as $quizid => $quizname) {
            $quizoptions['quiz:' . $quizid] = get_string('quizexisting', 'local_aiquestiongen', $quizname);
        }
        if (count($quizoptions) > 1) {
            $mform->addElement('select', 'quiz', get_string('quizchoice', 'local_aiquestiongen'), $quizoptions);
            $mform->addHelpButton('quiz', 'quizchoice', 'local_aiquestiongen');
        }

        $mform->addElement('advcheckbox', 'includepassage', get_string('includepassage', 'local_aiquestiongen'), ' ');
        $mform->setDefault('includepassage', !empty($this->_customdata['hascomprehension']) ? 1 : 0);
        $mform->addHelpButton('includepassage', 'includepassage', 'local_aiquestiongen');

        $this->add_action_buttons(true, get_string('publishbutton', 'local_aiquestiongen', $this->_customdata['count']));
    }
}
