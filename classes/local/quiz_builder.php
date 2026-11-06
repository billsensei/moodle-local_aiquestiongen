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
 * Creates a quiz, or finds one, and puts published questions into it.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_builder {
    /**
     * May the user add a new quiz to the course?
     *
     * @param int $courseid The course id.
     * @param int $userid The user.
     * @return bool
     */
    public static function can_create(int $courseid, int $userid): bool {
        $context = \context_course::instance($courseid);
        return has_capability('mod/quiz:addinstance', $context, $userid)
            && has_capability('moodle/course:manageactivities', $context, $userid);
    }

    /**
     * Quizzes in the course that the user may add questions to.
     *
     * Quizzes that already have attempts are left out, because changing their questions would
     * change what students have already answered.
     *
     * @param int $courseid The course id.
     * @param int $userid The user.
     * @return string[] Quiz id to name.
     */
    public static function get_quizzes(int $courseid, int $userid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $quizzes = [];
        foreach ($DB->get_records('quiz', ['course' => $courseid], 'name') as $quiz) {
            $cm = get_coursemodule_from_instance('quiz', $quiz->id, $courseid);
            if (!$cm || !empty($cm->deletioninprogress)) {
                continue;
            }
            $context = \context_module::instance($cm->id);
            if (has_capability('mod/quiz:manage', $context, $userid) && !quiz_has_attempts($quiz->id)) {
                $quizzes[(int) $quiz->id] = format_string($quiz->name, true, ['context' => $context]);
            }
        }
        return $quizzes;
    }

    /**
     * Create a new quiz in the course.
     *
     * All questions go on one page, so a reading passage stays with its questions; the teacher can
     * change that in the quiz settings.
     *
     * @param \stdClass $course The course record.
     * @param string $name The quiz name.
     * @param int $userid The acting user.
     * @return \stdClass The quiz record, with cmid.
     */
    public static function create_quiz(\stdClass $course, string $name, int $userid): \stdClass {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        if (!self::can_create($course->id, $userid)) {
            throw new \moodle_exception('err_quizcreate', 'local_aiquestiongen');
        }

        $info = (object) [
            'modulename' => 'quiz',
            'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST),
            'course' => $course->id,
            'section' => 0,
            'visible' => 0,
            'visibleoncoursepage' => 1,
            'name' => \core_text::substr(trim($name), 0, 255),
            'introeditor' => ['text' => '', 'format' => FORMAT_HTML, 'itemid' => 0],
            'cmidnumber' => '',
            'groupmode' => 0,
            'groupingid' => 0,
            'availability' => null,
            'completion' => 0,
            'completionview' => 0,
            'completionexpected' => 0,
            'completionpassgrade' => 0,
            'conditiongradegroup' => [],
            'conditionfieldgroup' => [],
            'conditioncompletiongroup' => [],
            'timeopen' => 0, 'timeclose' => 0, 'duedate' => 0, 'timelimit' => 0,
            'preferredbehaviour' => 'deferredfeedback', 'attempts' => 0, 'attemptonlast' => 0,
            'grademethod' => QUIZ_GRADEHIGHEST, 'decimalpoints' => 2, 'questiondecimalpoints' => -1,
            'questionsperpage' => 0, 'shuffleanswers' => 1, 'sumgrades' => 0, 'grade' => 10,
            'overduehandling' => 'autosubmit', 'graceperiod' => 86400, 'quizpassword' => '', 'subnet' => '',
            'browsersecurity' => '', 'delay1' => 0, 'delay2' => 0, 'showuserpicture' => 0, 'showblocks' => 0,
            'navmethod' => QUIZ_NAVMETHOD_FREE,
        ];
        // Review options: show everything at every stage, as in the quiz defaults.
        $stages = ['during', 'immediately', 'open', 'closed'];
        $parts = ['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer',
            'overallfeedback'];
        foreach ($stages as $when) {
            foreach ($parts as $what) {
                $info->{$what . $when} = ($when === 'during' && $what === 'overallfeedback') ? 0 : 1;
            }
        }

        $created = add_moduleinfo($info, $course);
        $quiz = $DB->get_record('quiz', ['id' => $created->instance], '*', MUST_EXIST);
        $quiz->cmid = $created->coursemodule;
        return $quiz;
    }

    /**
     * Load a quiz the user may add questions to.
     *
     * @param int $quizid The quiz id.
     * @param int $courseid The course the article belongs to.
     * @param int $userid The user.
     * @return \stdClass The quiz record, with cmid.
     */
    public static function load_quiz(int $quizid, int $courseid, int $userid): \stdClass {
        global $DB;

        if (!array_key_exists($quizid, self::get_quizzes($courseid, $userid))) {
            throw new \moodle_exception('err_badquiz', 'local_aiquestiongen');
        }
        $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
        $quiz->cmid = get_coursemodule_from_instance('quiz', $quiz->id, $courseid, false, MUST_EXIST)->id;
        return $quiz;
    }

    /**
     * Add questions to the end of a quiz, in the order given. Questions already in the quiz are skipped.
     *
     * @param \stdClass $quiz The quiz record, with cmid.
     * @param int[] $questionids The question ids.
     * @return int How many were added.
     */
    public static function add_questions(\stdClass $quiz, array $questionids): int {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $added = 0;
        foreach ($questionids as $questionid) {
            // Returns false if the question is already in the quiz, and nothing at all on success.
            if (quiz_add_quiz_question((int) $questionid, $quiz) !== false) {
                $added++;
            }
        }
        // Recompute the total marks, so the quiz grade scales to the new questions.
        \mod_quiz\quiz_settings::create($quiz->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        return $added;
    }
}
