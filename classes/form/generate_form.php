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

use local_aiquestiongen\local\job_manager;
use local_aiquestiongen\local\prompt_builder;
use local_aiquestiongen\local\qtypes;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * Form for choosing generation settings for a saved article.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generate_form extends \moodleform {
    /** @var string[] Language string for each question type's count field. */
    private const STRINGS = [
        'multichoice' => 'mccount',
        'truefalse' => 'tfcount',
        'essay' => 'essaycount',
        'multianswer' => 'clozecount',
        'gapselect' => 'gapcount',
    ];

    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'sourceid', $this->_customdata['sourceid'] ?? 0);
        $mform->setType('sourceid', PARAM_INT);

        $levels = array_combine(prompt_builder::CEFR_LEVELS, prompt_builder::CEFR_LEVELS);
        $mform->addElement('select', 'cefr', get_string('cefrlevel', 'local_aiquestiongen'), $levels);
        $mform->setDefault('cefr', 'B1');

        $max = job_manager::max_questions();
        $mform->addElement('static', 'countsintro', '', get_string('countsintro', 'local_aiquestiongen', $max));
        foreach (qtypes::COUNT_KEYS as $qtype => $field) {
            $string = self::STRINGS[$qtype];
            $mform->addElement('text', $field, get_string($string, 'local_aiquestiongen'), ['size' => 4]);
            $mform->setType($field, PARAM_INT);
            $mform->setDefault($field, $field === 'count' ? min(5, $max) : 0);
            $mform->addRule($field, null, 'numeric', null, 'client');
            $mform->addHelpButton($field, $string, 'local_aiquestiongen');
        }

        $mform->addElement('textarea', 'target', get_string('targetpoints', 'local_aiquestiongen'), [
            'rows' => 3,
            'cols' => 60,
        ]);
        $mform->setType('target', PARAM_TEXT);
        $mform->addHelpButton('target', 'targetpoints', 'local_aiquestiongen');

        $mform->addElement('select', 'language', get_string('feedbacklanguage', 'local_aiquestiongen'), [
            'English' => get_string('lang_english', 'local_aiquestiongen'),
            'Japanese' => get_string('lang_japanese', 'local_aiquestiongen'),
        ]);

        $this->add_action_buttons(true, get_string('generatedrafts', 'local_aiquestiongen'));
    }

    /**
     * Validate the total question count against the admin limit.
     *
     * @param array $data The submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $max = job_manager::max_questions();
        $total = 0;
        foreach (qtypes::COUNT_KEYS as $field) {
            if ((int) $data[$field] < 0) {
                $errors[$field] = get_string('err_count', 'local_aiquestiongen', $max);
            }
            $total += max(0, (int) $data[$field]);
        }
        if ($total < 1 || $total > $max) {
            $errors['count'] = get_string('err_count', 'local_aiquestiongen', $max);
        }
        return $errors;
    }
}
