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
 * Tests for publishing approved drafts to the question bank.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(publisher::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(bank_finder::class)]
final class publisher_test extends \advanced_testcase {
    /**
     * Build a course with a teacher, a question bank with one category, and a job with items.
     *
     * @param string[] $statuses Status of each draft to create.
     * @return array Context data.
     */
    private function make_setup(array $statuses = ['approved', 'approved', 'pending']): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $qbank = $gen->create_module('qbank', ['course' => $course->id, 'name' => 'Course bank']);
        $bankcontext = \context_module::instance($qbank->cmid);
        $qgen = $gen->get_plugin_generator('core_question');
        $category = $qgen->create_question_category(['contextid' => $bankcontext->id, 'name' => 'EFL']);

        $source = (object) [
            'courseid' => $course->id, 'userid' => $teacher->id, 'title' => 'Tom & the "Move"',
            'text' => 'Tom was reluctant to leave.', 'sourcefileitemid' => 0, 'timecreated' => time(),
        ];
        $source->id = $DB->insert_record('local_aiquestiongen_source', $source);
        $job = (object) [
            'sourceid' => $source->id, 'settings' => json_encode(['cefr' => 'B1']), 'status' => 'done',
            'tokensused' => 0, 'timecreated' => time(),
        ];
        $job->id = $DB->insert_record('local_aiquestiongen_job', $job);

        $ids = [];
        foreach ($statuses as $i => $status) {
            $payload = [
                'type' => 'multichoice', 'stem' => "Is 3 < 5 & \"reluctant\" <b>ok</b>? #$i",
                'answers' => [
                    ['text' => 'unwilling <i>', 'correct' => true, 'feedback' => 'Yes & yes.'],
                    ['text' => 'eager', 'correct' => false, 'feedback' => ''],
                    ['text' => 'angry', 'correct' => false, 'feedback' => ''],
                    ['text' => 'tired', 'correct' => false, 'feedback' => ''],
                ],
                'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
            ];
            $ids[] = $DB->insert_record('local_aiquestiongen_item', (object) [
                'jobid' => $job->id, 'qtype' => 'multichoice', 'status' => $status,
                'payload' => json_encode($payload), 'timemodified' => 0,
            ]);
        }
        return compact('course', 'teacher', 'qbank', 'bankcontext', 'category', 'source', 'job', 'ids');
    }

    /**
     * Approved drafts become real multiple-choice questions; others are left alone.
     */
    public function test_publish_creates_questions(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup();
        $this->setUser($s['teacher']);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);
        $this->assertSame(2, $result['published']);
        $this->assertSame(0, $result['failed']);
        $this->assertEquals($s['category']->id, $result['categoryid']);
        $this->assertEquals($s['qbank']->cmid, $result['bankcmid']);

        $item = $DB->get_record('local_aiquestiongen_item', ['id' => $s['ids'][0]]);
        $this->assertSame('published', $item->status);
        $this->assertNotEmpty($item->questionid);
        $this->assertSame('pending', $DB->get_field('local_aiquestiongen_item', 'status', ['id' => $s['ids'][2]]));
        $this->assertNull($DB->get_field('local_aiquestiongen_item', 'questionid', ['id' => $s['ids'][2]]));

        $question = \question_bank::load_question($item->questionid);
        $this->assertSame('multichoice', $question->qtype->name());
        $this->assertEquals(1, $DB->get_field('qtype_multichoice_options', 'single', ['questionid' => $item->questionid]));
        $this->assertCount(4, $question->answers);

        $fractions = array_map(fn($a) => (float) $a->fraction, array_values($question->answers));
        $this->assertEqualsCanonicalizing([1.0, 0.0, 0.0, 0.0], $fractions);

        $entry = $DB->get_record_sql(
            'SELECT qbe.questioncategoryid
               FROM {question_versions} qv
               JOIN {question_bank_entries} qbe ON qbe.id = qv.questionbankentryid
              WHERE qv.questionid = ?',
            [$item->questionid]
        );
        $this->assertEquals($s['category']->id, $entry->questioncategoryid);
    }

    /**
     * Teacher text is escaped when stored as HTML, so markup typed into a draft cannot run.
     */
    public function test_text_is_escaped(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup(['approved']);
        $this->setUser($s['teacher']);

        publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);

        $questionid = $DB->get_field('local_aiquestiongen_item', 'questionid', ['id' => $s['ids'][0]]);
        $question = $DB->get_record('question', ['id' => $questionid]);
        $this->assertStringContainsString('&lt;b&gt;ok&lt;/b&gt;', $question->questiontext);
        $this->assertStringNotContainsString('<b>', $question->questiontext);
        $answer = $DB->get_record('question_answers', ['question' => $questionid, 'fraction' => 1]);
        $this->assertStringContainsString('unwilling &lt;i&gt;', $answer->answer);
        $this->assertStringContainsString('Yes &amp; yes.', $answer->feedback);
    }

    /**
     * Published questions are tagged with ai-generated, the CEFR level and the article title.
     */
    public function test_tags(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup(['approved']);
        $this->setUser($s['teacher']);

        publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);

        $questionid = $DB->get_field('local_aiquestiongen_item', 'questionid', ['id' => $s['ids'][0]]);
        $tags = \core_tag_tag::get_item_tags_array('core_question', 'question', $questionid);
        $this->assertContains('ai-generated', $tags);
        $this->assertContains('cefr-b1', $tags);
        // Tag names come back escaped for display.
        $this->assertContains(s('Tom & the "Move"'), $tags);
    }

    /**
     * Choosing "new category" creates one named after the article.
     */
    public function test_new_category(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup(['approved']);
        $this->setUser($s['teacher']);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'new:' . $s['bankcontext']->id);

        $category = $DB->get_record('question_categories', ['id' => $result['categoryid']], '*', MUST_EXIST);
        $this->assertSame('Tom & the "Move"', $category->name);
        $this->assertEquals($s['bankcontext']->id, $category->contextid);
        $this->assertNotEquals(0, $category->parent);
        $this->assertSame(1, $result['published']);
    }

    /**
     * Publishing twice does not publish the same draft again.
     */
    public function test_second_publish_has_nothing_to_do(): void {
        $this->resetAfterTest();
        $s = $this->make_setup(['approved']);
        $this->setUser($s['teacher']);

        publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);
        try {
            publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);
            $this->fail('A second publish must find nothing approved');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_nothingapproved', $e->errorcode);
        }
    }

    /**
     * Targets outside the article's course, the top category, and junk are all refused, and nothing is published.
     */
    public function test_bad_targets(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup(['approved']);
        $this->setUser($s['teacher']);

        $gen = $this->getDataGenerator();
        $othercourse = $gen->create_course();
        $otherbank = $gen->create_module('qbank', ['course' => $othercourse->id]);
        $othercat = $gen->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => \context_module::instance($otherbank->cmid)->id]);
        $top = question_get_top_category($s['bankcontext']->id, true);

        $targets = [
            'cat:' . $othercat->id,
            'new:' . \context_module::instance($otherbank->cmid)->id,
            'cat:' . $top->id,
            'cat:999999',
            'whatever',
            '',
        ];
        foreach ($targets as $target) {
            try {
                publisher::publish($s['job'], $s['source'], $s['teacher']->id, $target);
                $this->fail("Target '$target' must be refused");
            } catch (\moodle_exception $e) {
                $this->assertSame('err_badtarget', $e->errorcode);
            }
        }
        $this->assertSame('approved', $DB->get_field('local_aiquestiongen_item', 'status', ['id' => $s['ids'][0]]));
    }

    /**
     * Users who lack the publish capability, or who do not own the article, are refused.
     */
    public function test_access_control(): void {
        $this->resetAfterTest();
        $s = $this->make_setup(['approved']);
        $gen = $this->getDataGenerator();
        $student = $gen->create_and_enrol($s['course'], 'student');
        $other = $gen->create_and_enrol($s['course'], 'editingteacher');
        $target = 'cat:' . $s['category']->id;

        try {
            publisher::publish($s['job'], $s['source'], $student->id, $target);
            $this->fail('A student must not publish');
        } catch (\required_capability_exception $e) {
            $this->assertSame('nopermissions', $e->errorcode);
        }

        // Another teacher on the course can publish in general but does not own this article.
        try {
            publisher::publish($s['job'], $s['source'], $other->id, $target);
            $this->fail('Another teacher must not publish this article');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notowner', $e->errorcode);
        }
    }

    /**
     * A teacher who cannot add questions to the bank sees no banks and cannot publish into it.
     */
    public function test_question_add_capability_is_required(): void {
        $this->resetAfterTest();
        $s = $this->make_setup(['approved']);
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('moodle/question:add', CAP_PROHIBIT, $roleid, $s['bankcontext']->id, true);
        role_assign($roleid, $s['teacher']->id, $s['bankcontext']->id);
        $this->setUser($s['teacher']);

        $this->assertSame([], bank_finder::get_banks($s['course']->id, $s['teacher']->id));
        $this->expectException(\moodle_exception::class);
        publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);
    }

    /**
     * The publish event is fired with the number published.
     */
    public function test_event(): void {
        $this->resetAfterTest();
        $s = $this->make_setup();
        $this->setUser($s['teacher']);
        $sink = $this->redirectEvents();

        publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);

        $events = array_values(array_filter(
            $sink->get_events(),
            fn($e) => $e instanceof \local_aiquestiongen\event\questions_published
        ));
        $this->assertCount(1, $events);
        $this->assertSame(2, $events[0]->other['count']);
    }

    /**
     * The bank list gives each bank's categories and the select options.
     */
    public function test_bank_finder_options(): void {
        $this->resetAfterTest();
        $s = $this->make_setup();

        $banks = bank_finder::get_banks($s['course']->id, $s['teacher']->id);
        $this->assertCount(1, $banks);
        $this->assertSame('Course bank', $banks[0]['name']);
        $this->assertContains('EFL', array_column($banks[0]['categories'], 'name'));

        $options = bank_finder::get_options($s['course']->id, $s['teacher']->id, 'My article');
        $this->assertArrayHasKey('cat:' . $s['category']->id, $options);
        $this->assertArrayHasKey('new:' . $s['bankcontext']->id, $options);
    }

    /**
     * Add approved true/false and essay drafts to the job from make_setup().
     *
     * @param \stdClass $job The job.
     * @return array [true/false item id, essay item id]
     */
    private function add_other_types(\stdClass $job): array {
        global $DB;
        $tf = $DB->insert_record('local_aiquestiongen_item', (object) [
            'jobid' => $job->id, 'qtype' => 'truefalse', 'status' => 'approved', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'truefalse', 'stem' => 'Tom said "no" & <b>left</b>.', 'correct' => false,
                'feedbacktrue' => 'Not <quite>.', 'feedbackfalse' => 'Right & well done.',
                'focus' => 'comprehension', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
            ]),
        ]);
        $essay = $DB->insert_record('local_aiquestiongen_item', (object) [
            'jobid' => $job->id, 'qtype' => 'essay', 'status' => 'approved', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'essay', 'stem' => 'Why did <Tom> leave?', 'modelanswer' => "He had to.\nHe was sad & tired.",
                'rubric' => 'Gives a <reason>.', 'focus' => 'comprehension', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
            ]),
        ]);
        return [$tf, $essay];
    }

    /**
     * Approved true/false and essay drafts publish as real questions with the right options.
     */
    public function test_publish_truefalse_and_essay(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup([]);
        [$tfid, $essayid] = $this->add_other_types($s['job']);
        $this->setUser($s['teacher']);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);
        $this->assertSame(2, $result['published']);
        $this->assertSame(0, $result['failed']);

        // True/false: "False" is the correct answer.
        $tfq = $DB->get_record('local_aiquestiongen_item', ['id' => $tfid], '*', MUST_EXIST);
        $this->assertSame('published', $tfq->status);
        $question = \question_bank::load_question($tfq->questionid);
        $this->assertSame('truefalse', $question->qtype->name());
        $this->assertFalse($question->rightanswer);
        $text = $DB->get_field('question', 'questiontext', ['id' => $tfq->questionid]);
        $this->assertStringContainsString('&lt;b&gt;left&lt;/b&gt;', $text);
        $answers = $DB->get_records('question_answers', ['question' => $tfq->questionid], 'id');
        $feedback = array_column($answers, 'feedback');
        $this->assertStringContainsString('Not &lt;quite&gt;.', $feedback[0]);
        $this->assertStringContainsString('Right &amp; well done.', $feedback[1]);

        // Essay: model answer and marking guide are in the grader information, escaped.
        $essayq = $DB->get_record('local_aiquestiongen_item', ['id' => $essayid], '*', MUST_EXIST);
        $this->assertSame('published', $essayq->status);
        $question = \question_bank::load_question($essayq->questionid);
        $this->assertSame('essay', $question->qtype->name());
        $this->assertSame('editor', $question->responseformat);
        $text = $DB->get_field('question', 'questiontext', ['id' => $essayq->questionid]);
        $this->assertStringContainsString('Why did &lt;Tom&gt; leave?', $text);
        $grader = $question->graderinfo;
        $this->assertStringContainsString('He had to.<br>', $grader);
        $this->assertStringContainsString('sad &amp; tired', $grader);
        $this->assertStringContainsString('Gives a &lt;reason&gt;.', $grader);
        $this->assertStringContainsString(get_string('modelanswer', 'local_aiquestiongen'), $grader);
    }

    /**
     * All three types in one publish are tagged and counted together.
     */
    public function test_publish_all_three_types_tagged(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup(['approved']);
        $this->add_other_types($s['job']);
        $this->setUser($s['teacher']);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);
        $this->assertSame(3, $result['published']);

        $items = $DB->get_records('local_aiquestiongen_item', ['jobid' => $s['job']->id]);
        $this->assertSame(['published'], array_values(array_unique(array_column($items, 'status'))));
        foreach ($items as $item) {
            $tags = \core_tag_tag::get_item_tags_array('core_question', 'question', $item->questionid);
            $this->assertContains('ai-generated', $tags);
            $this->assertContains('cefr-b1', $tags);
        }
    }

    /**
     * Add approved fill-in-the-blank drafts to the job from make_setup().
     *
     * @param \stdClass $job The job.
     * @return array [typed item id, chosen item id]
     */
    private function add_fill_items(\stdClass $job): array {
        global $DB;
        $typed = $DB->insert_record('local_aiquestiongen_item', (object) [
            'jobid' => $job->id, 'qtype' => 'multianswer', 'status' => 'approved', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'multianswer', 'stem' => 'Is 3 < 5 & Tom ____ "ok"?', 'answer' => 'reluctant',
                'alternatives' => ['unwilling', 'a}b'], 'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1',
                'flagged' => false, 'issues' => [],
            ]),
        ]);
        $chosen = $DB->insert_record('local_aiquestiongen_item', (object) [
            'jobid' => $job->id, 'qtype' => 'gapselect', 'status' => 'approved', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'gapselect', 'stem' => 'Tom <b>was</b> ____ to leave.', 'answer' => 'reluctant',
                'distractors' => ['eager', 'angry', 'tired'], 'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1',
                'flagged' => false, 'issues' => [],
            ]),
        ]);
        return [$typed, $chosen];
    }

    /**
     * Typed blanks publish as Cloze questions that accept every listed word.
     */
    public function test_publish_cloze(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup([]);
        [$typedid] = $this->add_fill_items($s['job']);
        $DB->set_field('local_aiquestiongen_item', 'status', 'pending', ['jobid' => $s['job']->id, 'qtype' => 'gapselect']);
        $this->setUser($s['teacher']);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);
        $this->assertSame(1, $result['published']);
        $this->assertSame(0, $result['failed']);

        $item = $DB->get_record('local_aiquestiongen_item', ['id' => $typedid], '*', MUST_EXIST);
        $this->assertSame('published', $item->status);
        $question = $DB->get_record('question', ['id' => $item->questionid], '*', MUST_EXIST);
        $this->assertSame('multianswer', $question->qtype);
        $this->assertStringContainsString('Is 3 &lt; 5 &amp; Tom {#1} &quot;ok&quot;?', $question->questiontext);
        $this->assertStringContainsString('____', $question->name);

        // The sub-question holds the three accepted words.
        $multi = $DB->get_record('question_multianswer', ['question' => $question->id], '*', MUST_EXIST);
        $subid = (int) explode(',', $multi->sequence)[0];
        $answers = $DB->get_records('question_answers', ['question' => $subid], 'id');
        $this->assertSame(['reluctant', 'unwilling', 'a}b'], array_values(array_column($answers, 'answer')));
        $this->assertSame([1.0, 1.0, 1.0], array_map('floatval', array_values(array_column($answers, 'fraction'))));
        $this->assertSame('shortanswer', $DB->get_field('question', 'qtype', ['id' => $subid]));
    }

    /**
     * Chosen blanks publish as gapselect questions with the right word as the answer to [[1]].
     */
    public function test_publish_gapselect(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup([]);
        [, $chosenid] = $this->add_fill_items($s['job']);
        $DB->set_field('local_aiquestiongen_item', 'status', 'pending', ['jobid' => $s['job']->id, 'qtype' => 'multianswer']);
        $this->setUser($s['teacher']);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);
        $this->assertSame(1, $result['published']);

        $item = $DB->get_record('local_aiquestiongen_item', ['id' => $chosenid], '*', MUST_EXIST);
        $this->assertSame('published', $item->status);
        $question = \question_bank::load_question($item->questionid);
        $this->assertSame('gapselect', $question->qtype->name());
        $this->assertStringContainsString('Tom &lt;b&gt;was&lt;/b&gt; [[1]] to leave.', $question->questiontext);
        $this->assertSame(1, $question->rightchoices[1]);
        $this->assertSame('reluctant', $question->choices[1][1]->text);
        // Choices are numbered from 1.
        $texts = array_values(array_map(fn($c) => $c->text, $question->choices[1]));
        $this->assertSame(['reluctant', 'eager', 'angry', 'tired'], $texts);
    }

    /**
     * Including the passage adds one description item, tags comprehension questions, and is not repeated.
     */
    public function test_reading_passage(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup([]);
        // Two true/false statements (comprehension) and a vocabulary multiple-choice question.
        $this->add_other_types($s['job']);
        $DB->set_field('local_aiquestiongen_item', 'status', 'pending', ['jobid' => $s['job']->id, 'qtype' => 'essay']);
        $DB->insert_record('local_aiquestiongen_item', (object) [
            'jobid' => $s['job']->id, 'qtype' => 'multichoice', 'status' => 'approved', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'multichoice', 'stem' => 'Vocabulary?', 'focus' => 'vocabulary', 'target' => 'reluctant',
                'answers' => [
                    ['text' => 'a', 'correct' => true, 'feedback' => ''], ['text' => 'b', 'correct' => false, 'feedback' => ''],
                    ['text' => 'c', 'correct' => false, 'feedback' => ''], ['text' => 'd', 'correct' => false, 'feedback' => ''],
                ],
                'flagged' => false, 'issues' => [],
            ]),
        ]);
        $this->setUser($s['teacher']);
        $s['source']->text = "Tom was <reluctant> to leave.\n\nHe had lived there for years.";
        $DB->set_field('local_aiquestiongen_source', 'text', $s['source']->text, ['id' => $s['source']->id]);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id, true);
        $this->assertSame(2, $result['published']);
        $this->assertTrue($result['passage']);

        $passages = $DB->get_records('question', ['qtype' => 'description']);
        $this->assertCount(1, $passages);
        $passage = reset($passages);
        $this->assertStringContainsString('Tom was &lt;reluctant&gt; to leave.', $passage->questiontext);
        $this->assertStringContainsString('<p>He had lived there for years.</p>', $passage->questiontext);
        $this->assertStringContainsString('Reading passage', $passage->name);
        $passagetags = \core_tag_tag::get_item_tags_array('core_question', 'question', $passage->id);
        $this->assertContains('reading-comprehension', $passagetags);

        // Only the true/false statement is tagged as comprehension, not the vocabulary question.
        foreach ($DB->get_records('local_aiquestiongen_item', ['jobid' => $s['job']->id, 'status' => 'published']) as $item) {
            $tags = \core_tag_tag::get_item_tags_array('core_question', 'question', $item->questionid);
            $this->assertSame($item->qtype === 'truefalse', in_array('reading-comprehension', $tags, true), $item->qtype);
        }

        // Publishing more later does not add a second passage.
        $DB->set_field('local_aiquestiongen_item', 'status', 'approved', ['jobid' => $s['job']->id, 'qtype' => 'essay']);
        $job = $DB->get_record('local_aiquestiongen_job', ['id' => $s['job']->id]);
        $again = publisher::publish($job, $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id, true);
        $this->assertSame(1, $again['published']);
        $this->assertFalse($again['passage']);
        $this->assertCount(1, $DB->get_records('question', ['qtype' => 'description']));
    }

    /**
     * Without the option no passage is created.
     */
    public function test_no_passage_by_default(): void {
        global $DB;
        $this->resetAfterTest();
        $s = $this->make_setup(['approved']);
        $this->setUser($s['teacher']);

        $result = publisher::publish($s['job'], $s['source'], $s['teacher']->id, 'cat:' . $s['category']->id);
        $this->assertFalse($result['passage']);
        $this->assertFalse($DB->record_exists('question', ['qtype' => 'description']));
    }
}
