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
 * Tests for creating quizzes and adding published questions to them.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(quiz_builder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(publisher::class)]
final class quiz_builder_test extends \advanced_testcase {
    /**
     * Build a course with a teacher, a bank and category, and a job with approved drafts.
     *
     * @return array Context data.
     */
    private function make_setup(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $qbank = $gen->create_module('qbank', ['course' => $course->id, 'name' => 'Course bank']);
        $bankcontext = \context_module::instance($qbank->cmid);
        $category = $gen->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => $bankcontext->id, 'name' => 'EFL']);

        $source = (object) [
            'courseid' => $course->id, 'userid' => $teacher->id, 'title' => 'Tom & the "Move"',
            'text' => "Tom was reluctant to leave.\n\nHe had lived there for years.", 'sourcefileitemid' => 0,
            'timecreated' => time(),
        ];
        $source->id = $DB->insert_record('local_aiquestiongen_source', $source);
        $job = (object) [
            'sourceid' => $source->id, 'settings' => json_encode(['cefr' => 'B1']), 'status' => 'done',
            'tokensused' => 0, 'timecreated' => time(),
        ];
        $job->id = $DB->insert_record('local_aiquestiongen_job', $job);

        // A true/false statement first, then an essay.
        $tf = [
            'type' => 'truefalse', 'stem' => 'Tom left.', 'correct' => true, 'feedbacktrue' => '',
            'feedbackfalse' => '', 'focus' => 'comprehension', 'cefr' => 'B1',
        ];
        $essay = [
            'type' => 'essay', 'stem' => 'Why?', 'modelanswer' => 'Because.', 'rubric' => 'A reason.',
            'focus' => 'comprehension', 'cefr' => 'B1',
        ];
        $drafts = [['truefalse', $tf], ['essay', $essay]];
        foreach ($drafts as [$qtype, $payload]) {
            $DB->insert_record('local_aiquestiongen_item', (object) [
                'jobid' => $job->id, 'qtype' => $qtype, 'status' => 'approved', 'timemodified' => 0,
                'payload' => json_encode($payload + ['flagged' => false, 'issues' => []]),
            ]);
        }
        return compact('course', 'teacher', 'category', 'source', 'job');
    }

    /**
     * Question types in a quiz, in slot order.
     *
     * @param int $quizid The quiz id.
     * @return string[]
     */
    private function quiz_qtypes(int $quizid): array {
        global $DB;
        $rows = $DB->get_records_sql(
            "SELECT s.slot, q.qtype
               FROM {quiz_slots} s
               JOIN {question_references} qr ON qr.itemid = s.id AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
               JOIN {question_versions} qv ON qv.questionbankentryid = qr.questionbankentryid
               JOIN {question} q ON q.id = qv.questionid
              WHERE s.quizid = ?
           ORDER BY s.slot",
            [$quizid]
        );
        return array_values(array_column($rows, 'qtype'));
    }

    /**
     * A new quiz is created hidden, on one page, in the course.
     */
    public function test_create_quiz(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup();
        $this->setUser($s['teacher']);

        $quiz = quiz_builder::create_quiz($s['course'], 'My new quiz', $s['teacher']->id);

        $this->assertSame('My new quiz', $quiz->name);
        $this->assertEquals($s['course']->id, $quiz->course);
        $this->assertEquals(0, $quiz->questionsperpage);
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $s['course']->id, false, MUST_EXIST);
        $this->assertEquals($cm->id, $quiz->cmid);
        $this->assertEquals(0, $cm->visible);
        $this->assertArrayHasKey($quiz->id, quiz_builder::get_quizzes($s['course']->id, $s['teacher']->id));
        $this->assertCount(1, $DB->get_records('quiz', ['course' => $s['course']->id]));
    }

    /**
     * Users who cannot add quizzes are refused, and no quiz is made.
     */
    public function test_create_quiz_needs_capability(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup();
        $student = $this->getDataGenerator()->create_and_enrol($s['course'], 'student');

        $this->assertFalse(quiz_builder::can_create($s['course']->id, $student->id));
        try {
            quiz_builder::create_quiz($s['course'], 'Nope', $student->id);
            $this->fail('A student must not create a quiz');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_quizcreate', $e->errorcode);
        }
        $this->assertSame(0, $DB->count_records('quiz'));
    }

    /**
     * The quiz list leaves out quizzes the user cannot manage and quizzes that already have attempts.
     */
    public function test_get_quizzes(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup();
        $gen = $this->getDataGenerator();
        $open = $gen->create_module('quiz', ['course' => $s['course']->id, 'name' => 'Open quiz']);
        $used = $gen->create_module('quiz', ['course' => $s['course']->id, 'name' => 'Used quiz']);
        $DB->insert_record('quiz_attempts', (object) [
            'quiz' => $used->id, 'userid' => $s['teacher']->id, 'attempt' => 1, 'uniqueid' => 987654, 'layout' => '',
            'currentpage' => 0, 'preview' => 0, 'state' => 'inprogress', 'timestart' => time(), 'timefinish' => 0,
            'timemodified' => time(), 'timemodifiedoffline' => 0, 'timecheckstate' => null, 'sumgrades' => null,
            'gradednotificationsenttime' => null,
        ]);

        $quizzes = quiz_builder::get_quizzes($s['course']->id, $s['teacher']->id);
        $this->assertSame(['Open quiz'], array_values($quizzes));
        $this->assertArrayHasKey($open->id, $quizzes);

        $student = $gen->create_and_enrol($s['course'], 'student');
        $this->assertSame([], quiz_builder::get_quizzes($s['course']->id, $student->id));
    }

    /**
     * Publishing into a new quiz puts the reading passage first, then the questions, and sets the total marks.
     */
    public function test_publish_into_new_quiz(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup();
        $this->setUser($s['teacher']);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id, true, 'new');

        $this->assertSame(2, $result['published']);
        $this->assertSame('', $result['quizerror']);
        $this->assertSame(3, $result['quizadded']);
        $this->assertNotEmpty($result['quizcmid']);
        $this->assertSame(format_string('Tom & the "Move"'), $result['quizname']);

        $quiz = $DB->get_record('quiz', ['course' => $s['course']->id], '*', MUST_EXIST);
        $this->assertSame(['description', 'truefalse', 'essay'], $this->quiz_qtypes($quiz->id));
        $this->assertGreaterThan(0, (float) $DB->get_field('quiz', 'sumgrades', ['id' => $quiz->id]));
    }

    /**
     * Publishing without the passage puts only the questions in the quiz.
     */
    public function test_publish_into_new_quiz_without_passage(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup();
        $this->setUser($s['teacher']);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id, false, 'new');

        $this->assertSame(2, $result['quizadded']);
        $quiz = $DB->get_record('quiz', ['course' => $s['course']->id], '*', MUST_EXIST);
        $this->assertSame(['truefalse', 'essay'], $this->quiz_qtypes($quiz->id));
    }

    /**
     * Publishing into an existing quiz appends, and a later publish does not repeat the passage in it.
     */
    public function test_publish_into_existing_quiz(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup();
        $existing = $this->getDataGenerator()->create_module('quiz', ['course' => $s['course']->id, 'name' => 'Old quiz']);
        $this->setUser($s['teacher']);

        $result = publisher::publish(
            $s['job'],
            $s['source'],
            $s['teacher']->id,
            'cat:' . $s['category']->id,
            true,
            'quiz:' . $existing->id
        );
        $this->assertSame(3, $result['quizadded']);
        $this->assertEquals($existing->cmid, $result['quizcmid']);
        $this->assertSame(['description', 'truefalse', 'essay'], $this->quiz_qtypes($existing->id));
        $this->assertCount(1, $DB->get_records('quiz', ['course' => $s['course']->id]));
    }

    /**
     * Bad quiz choices stop the whole publish before any question is made.
     */
    public function test_bad_quiz_choice_stops_everything(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup();
        $gen = $this->getDataGenerator();
        $othercourse = $gen->create_course();
        $elsewhere = $gen->create_module('quiz', ['course' => $othercourse->id]);
        $this->setUser($s['teacher']);

        foreach (['quiz:999999', 'quiz:' . $elsewhere->id, 'quiz:abc', 'bogus', ''] as $choice) {
            try {
                publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id, false, $choice);
                $this->fail("Quiz choice '$choice' must be refused");
            } catch (\moodle_exception $e) {
                $this->assertContains($e->errorcode, ['err_badquiz']);
            }
        }
        $this->assertSame(0, $DB->count_records('question', ['qtype' => 'truefalse']));
        $this->assertSame(0, $DB->count_records('local_aiquestiongen_item', ['status' => 'published']));
    }

    /**
     * A quiz with attempts cannot be chosen.
     */
    public function test_quiz_with_attempts_is_refused(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup();
        $used = $this->getDataGenerator()->create_module('quiz', ['course' => $s['course']->id]);
        $DB->insert_record('quiz_attempts', (object) [
            'quiz' => $used->id, 'userid' => $s['teacher']->id, 'attempt' => 1, 'uniqueid' => 987655, 'layout' => '',
            'currentpage' => 0, 'preview' => 0, 'state' => 'inprogress', 'timestart' => time(), 'timefinish' => 0,
            'timemodified' => time(), 'timemodifiedoffline' => 0, 'timecheckstate' => null, 'sumgrades' => null,
            'gradednotificationsenttime' => null,
        ]);
        $this->setUser($s['teacher']);

        $this->expectException(\moodle_exception::class);
        publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id, false, 'quiz:' . $used->id);
    }

    /**
     * Without the quiz option nothing is created, and the result says so.
     */
    public function test_no_quiz_by_default(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup();
        $this->setUser($s['teacher']);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);
        $this->assertSame(0, $result['quizcmid']);
        $this->assertSame(0, $result['quizadded']);
        $this->assertSame(0, $DB->count_records('quiz'));
    }
}
