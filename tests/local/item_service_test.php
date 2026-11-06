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
 * Tests for editing, approving and rejecting drafts.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(item_service::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(item_presenter::class)]
final class item_service_test extends \advanced_testcase {
    /**
     * Create a teacher, a course, a source, a job and three drafts (two valid, one flagged).
     *
     * @return array [teacher, course, source, job, item ids keyed good1, good2, bad]
     */
    private function make_job(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $source = (object) [
            'courseid' => $course->id, 'userid' => $teacher->id, 'title' => 'T',
            'text' => 'Tom was reluctant to leave.', 'sourcefileitemid' => 0, 'timecreated' => time(),
        ];
        $source->id = $DB->insert_record('local_aiqg_source', $source);
        $job = (object) [
            'sourceid' => $source->id, 'settings' => '{}', 'status' => 'done', 'tokensused' => 0, 'timecreated' => time(),
        ];
        $job->id = $DB->insert_record('local_aiqg_job', $job);

        $good = [
            'type' => 'multichoice', 'stem' => 'What does "reluctant" mean?',
            'answers' => [
                ['text' => 'unwilling', 'correct' => true, 'feedback' => 'Yes.'],
                ['text' => 'eager', 'correct' => false, 'feedback' => ''],
                ['text' => 'angry', 'correct' => false, 'feedback' => ''],
                ['text' => 'tired', 'correct' => false, 'feedback' => ''],
            ],
            'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
        ];
        $bad = $good;
        $bad['answers'][1]['correct'] = true;
        $bad['flagged'] = true;
        $bad['issues'] = ['correctcount'];

        $ids = [];
        foreach (['good1' => $good, 'good2' => $good, 'bad' => $bad] as $key => $payload) {
            $ids[$key] = $DB->insert_record('local_aiqg_item', (object) [
                'jobid' => $job->id, 'qtype' => 'multichoice', 'status' => 'pending',
                'payload' => json_encode($payload), 'timemodified' => 0,
            ]);
        }
        return [$teacher, $course, $source, $job, $ids];
    }

    /**
     * The presenter exposes letters, flags and readable issues.
     */
    public function test_presenter(): void {
        global $DB;
        $this->resetAfterTest();
        [, , , , $ids] = $this->make_job();

        $good = item_presenter::export($DB->get_record('local_aiqg_item', ['id' => $ids['good1']]));
        $this->assertSame('A', $good['answers'][0]['letter']);
        $this->assertTrue($good['answers'][0]['correct']);
        $this->assertTrue($good['ispending']);
        $this->assertTrue($good['canedit']);
        $this->assertFalse($good['flagged']);

        $bad = item_presenter::export($DB->get_record('local_aiqg_item', ['id' => $ids['bad']]));
        $this->assertTrue($bad['flagged']);
        $this->assertSame([get_string('issue_correctcount', 'local_aiquestiongen')], $bad['issues']);
    }

    /**
     * Approving a valid draft works and fires the event; undo returns it to pending.
     */
    public function test_approve_and_undo(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , , $ids] = $this->make_job();
        $sink = $this->redirectEvents();

        [$item, $source, $context] = item_service::load_item($ids['good1'], $teacher->id);
        $item = item_service::set_status($item, $source, $context, 'approved');
        $this->assertSame('approved', $DB->get_field('local_aiqg_item', 'status', ['id' => $ids['good1']]));

        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \local_aiquestiongen\event\item_approved);
        $this->assertCount(1, $events);

        // Approving again does not fire a second event.
        item_service::set_status($item, $source, $context, 'approved');
        $events = array_filter($sink->get_events(), fn($e) => $e instanceof \local_aiquestiongen\event\item_approved);
        $this->assertCount(1, $events);

        item_service::set_status($item, $source, $context, 'pending');
        $this->assertSame('pending', $DB->get_field('local_aiqg_item', 'status', ['id' => $ids['good1']]));
    }

    /**
     * A draft that fails validation cannot be approved, but can be rejected.
     */
    public function test_flagged_cannot_be_approved(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , , $ids] = $this->make_job();

        [$item, $source, $context] = item_service::load_item($ids['bad'], $teacher->id);
        try {
            item_service::set_status($item, $source, $context, 'approved');
            $this->fail('A flagged draft must not be approvable');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_cannotapprove', $e->errorcode);
        }
        $this->assertSame('pending', $DB->get_field('local_aiqg_item', 'status', ['id' => $ids['bad']]));

        item_service::set_status($item, $source, $context, 'rejected');
        $this->assertSame('rejected', $DB->get_field('local_aiqg_item', 'status', ['id' => $ids['bad']]));
    }

    /**
     * Only pending, approved or rejected can be set; published drafts are locked.
     */
    public function test_bad_and_published_status(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , , $ids] = $this->make_job();
        [$item, $source, $context] = item_service::load_item($ids['good1'], $teacher->id);

        try {
            item_service::set_status($item, $source, $context, 'published');
            $this->fail('Teachers must not set published');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_badstatus', $e->errorcode);
        }

        $DB->set_field('local_aiqg_item', 'status', 'published', ['id' => $item->id]);
        $item->status = 'published';
        $this->expectException(\moodle_exception::class);
        item_service::update_item($item, $source, 'x', []);
    }

    /**
     * Editing a flagged draft into a valid one clears the flag.
     */
    public function test_edit_fixes_flag(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , , $ids] = $this->make_job();
        [$item, $source] = item_service::load_item($ids['bad'], $teacher->id);

        $answers = [
            ['text' => 'unwilling', 'correct' => true, 'feedback' => 'Yes.'],
            ['text' => 'eager', 'correct' => false, 'feedback' => ''],
            ['text' => 'angry', 'correct' => false, 'feedback' => ''],
            ['text' => 'tired', 'correct' => false, 'feedback' => ''],
        ];
        item_service::update_item($item, $source, '  New stem?  ', $answers);

        $payload = json_decode($DB->get_field('local_aiqg_item', 'payload', ['id' => $ids['bad']]), true);
        $this->assertSame('New stem?', $payload['stem']);
        $this->assertFalse($payload['flagged']);
        $this->assertSame([], $payload['issues']);
    }

    /**
     * An invalid edit is saved (work is not lost) and flagged; an approved draft goes back to pending.
     */
    public function test_invalid_edit_is_saved_and_flagged(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , , $ids] = $this->make_job();
        [$item, $source, $context] = item_service::load_item($ids['good1'], $teacher->id);
        $item = item_service::set_status($item, $source, $context, 'approved');

        $answers = [
            ['text' => 'unwilling', 'correct' => true, 'feedback' => ''],
            ['text' => 'unwilling', 'correct' => false, 'feedback' => ''],
            ['text' => 'angry', 'correct' => false, 'feedback' => ''],
            ['text' => 'tired', 'correct' => false, 'feedback' => ''],
        ];
        item_service::update_item($item, $source, 'Edited?', $answers);

        $record = $DB->get_record('local_aiqg_item', ['id' => $ids['good1']]);
        $payload = json_decode($record->payload, true);
        $this->assertSame('Edited?', $payload['stem']);
        $this->assertTrue($payload['flagged']);
        $this->assertContains('duplicate', $payload['issues']);
        $this->assertSame('pending', $record->status);
    }

    /**
     * Special characters are stored as typed (they are escaped on output, not on save).
     */
    public function test_special_characters_round_trip(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , , $ids] = $this->make_job();
        [$item, $source] = item_service::load_item($ids['good1'], $teacher->id);

        $stem = 'Is 3 < 5 & "reluctant" <b>bold</b>?';
        item_service::update_item($item, $source, $stem, json_decode($item->payload, true)['answers']);

        $payload = json_decode($DB->get_field('local_aiqg_item', 'payload', ['id' => $ids['good1']]), true);
        $this->assertSame($stem, $payload['stem']);
    }

    /**
     * Bulk approve changes valid pending drafts and skips flagged ones; bulk reject takes everything pending.
     */
    public function test_bulk(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , , $ids] = $this->make_job();
        [$job, $source, $context] = item_service::load_job(
            $DB->get_field('local_aiqg_item', 'jobid', ['id' => $ids['good1']]),
            $teacher->id
        );

        [$changed, $skipped] = item_service::set_status_bulk($job, $source, $context, 'multichoice', 'approved');
        $this->assertCount(2, $changed);
        $this->assertSame(1, $skipped);
        $this->assertSame('pending', $DB->get_field('local_aiqg_item', 'status', ['id' => $ids['bad']]));

        // Already-approved drafts are not touched by reject-all; only pending ones are.
        [$changed, $skipped] = item_service::set_status_bulk($job, $source, $context, 'multichoice', 'rejected');
        $this->assertCount(1, $changed);
        $this->assertSame(0, $skipped);
        $this->assertSame('approved', $DB->get_field('local_aiqg_item', 'status', ['id' => $ids['good1']]));
        $this->assertSame('rejected', $DB->get_field('local_aiqg_item', 'status', ['id' => $ids['bad']]));
    }

    /**
     * Another teacher, a student and a non-enrolled user are all refused.
     */
    public function test_access_control(): void {
        $this->resetAfterTest();
        [, $course, , , $ids] = $this->make_job();
        $gen = $this->getDataGenerator();
        $otherteacher = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');
        $outsider = $gen->create_user();

        try {
            item_service::load_item($ids['good1'], $otherteacher->id);
            $this->fail('Another teacher must not use this teacher\'s draft');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_notowner', $e->errorcode);
        }
        foreach ([$student, $outsider] as $user) {
            try {
                item_service::load_item($ids['good1'], $user->id);
                $this->fail('A user without the capability must be refused');
            } catch (\required_capability_exception $e) {
                $this->assertSame('nopermissions', $e->errorcode);
            }
        }
    }

    /**
     * Insert a true/false and an essay draft into a job.
     *
     * @param int $jobid The job id.
     * @return array [true/false item id, essay item id]
     */
    private function add_tf_and_essay(int $jobid): array {
        global $DB;
        $tf = $DB->insert_record('local_aiqg_item', (object) [
            'jobid' => $jobid, 'qtype' => 'truefalse', 'status' => 'pending', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'truefalse', 'stem' => 'Tom stayed.', 'correct' => false, 'feedbacktrue' => 'No.',
                'feedbackfalse' => 'Yes.', 'focus' => 'comprehension', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
            ]),
        ]);
        $essay = $DB->insert_record('local_aiqg_item', (object) [
            'jobid' => $jobid, 'qtype' => 'essay', 'status' => 'pending', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'essay', 'stem' => 'Why did Tom leave?', 'modelanswer' => 'He had to.',
                'rubric' => 'Gives a reason.', 'focus' => 'comprehension', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
            ]),
        ]);
        return [$tf, $essay];
    }

    /**
     * The presenter exposes the true/false and essay fields.
     */
    public function test_presenter_other_types(): void {
        global $DB;
        $this->resetAfterTest();
        [, , , $job] = $this->make_job();
        [$tfid, $essayid] = $this->add_tf_and_essay($job->id);

        $tf = item_presenter::export($DB->get_record('local_aiqg_item', ['id' => $tfid]));
        $this->assertTrue($tf['istf']);
        $this->assertFalse($tf['ismc']);
        $this->assertTrue($tf['correctfalse']);
        $this->assertFalse($tf['correcttrue']);
        $this->assertSame('Yes.', $tf['feedbackfalse']);
        $this->assertSame([], $tf['answers']);

        $essay = item_presenter::export($DB->get_record('local_aiqg_item', ['id' => $essayid]));
        $this->assertTrue($essay['isessay']);
        $this->assertSame('He had to.', $essay['modelanswer']);
        $this->assertSame('Gives a reason.', $essay['rubric']);
    }

    /**
     * Editing a true/false draft changes the answer and feedback, and leaves omitted fields alone.
     */
    public function test_edit_truefalse(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , $job] = $this->make_job();
        [$tfid] = $this->add_tf_and_essay($job->id);
        [$item, $source] = item_service::load_item($tfid, $teacher->id);

        item_service::update_item($item, $source, 'Tom left.', [], ['correct' => true, 'feedbacktrue' => 'Good.']);

        $payload = json_decode($DB->get_field('local_aiqg_item', 'payload', ['id' => $tfid]), true);
        $this->assertSame('Tom left.', $payload['stem']);
        $this->assertTrue($payload['correct']);
        $this->assertSame('Good.', $payload['feedbacktrue']);
        $this->assertSame('Yes.', $payload['feedbackfalse']);
        $this->assertFalse($payload['flagged']);
    }

    /**
     * Editing an essay so the marking guide is empty saves the work and flags it, and approval is refused.
     */
    public function test_edit_essay_and_flag(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , $job] = $this->make_job();
        [, $essayid] = $this->add_tf_and_essay($job->id);
        [$item, $source, $context] = item_service::load_item($essayid, $teacher->id);

        $item = item_service::update_item($item, $source, 'New prompt?', [], ['modelanswer' => 'Changed.', 'rubric' => '  ']);

        $payload = json_decode($DB->get_field('local_aiqg_item', 'payload', ['id' => $essayid]), true);
        $this->assertSame('Changed.', $payload['modelanswer']);
        $this->assertTrue($payload['flagged']);
        $this->assertSame(['norubric'], $payload['issues']);

        try {
            item_service::set_status($item, $source, $context, 'approved');
            $this->fail('A flagged essay must not be approvable');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_cannotapprove', $e->errorcode);
        }
    }

    /**
     * Approving valid true/false and essay drafts works.
     */
    public function test_approve_other_types(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , $job] = $this->make_job();
        foreach ($this->add_tf_and_essay($job->id) as $id) {
            [$item, $source, $context] = item_service::load_item($id, $teacher->id);
            item_service::set_status($item, $source, $context, 'approved');
            $this->assertSame('approved', $DB->get_field('local_aiqg_item', 'status', ['id' => $id]));
        }
    }

    /**
     * Bulk actions only touch the chosen question type.
     */
    public function test_bulk_is_per_type(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , $job, $ids] = $this->make_job();
        [$tfid, $essayid] = $this->add_tf_and_essay($job->id);
        [$job, $source, $context] = item_service::load_job($job->id, $teacher->id);

        [$changed, $skipped] = item_service::set_status_bulk($job, $source, $context, 'truefalse', 'approved');
        $this->assertCount(1, $changed);
        $this->assertSame(0, $skipped);
        $this->assertSame('approved', $DB->get_field('local_aiqg_item', 'status', ['id' => $tfid]));
        $this->assertSame('pending', $DB->get_field('local_aiqg_item', 'status', ['id' => $essayid]));
        $this->assertSame('pending', $DB->get_field('local_aiqg_item', 'status', ['id' => $ids['good1']]));
    }

    /**
     * Insert a typed and a chosen fill-in-the-blank draft.
     *
     * @param int $jobid The job id.
     * @return array [typed item id, chosen item id]
     */
    private function add_fill_items(int $jobid): array {
        global $DB;
        $typed = $DB->insert_record('local_aiqg_item', (object) [
            'jobid' => $jobid, 'qtype' => 'multianswer', 'status' => 'pending', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'multianswer', 'stem' => 'Tom was ____ to leave.', 'answer' => 'reluctant',
                'alternatives' => ['unwilling'], 'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1',
                'flagged' => false, 'issues' => [],
            ]),
        ]);
        $chosen = $DB->insert_record('local_aiqg_item', (object) [
            'jobid' => $jobid, 'qtype' => 'gapselect', 'status' => 'pending', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'gapselect', 'stem' => 'Tom was ____ to leave.', 'answer' => 'reluctant',
                'distractors' => ['eager', 'angry', 'tired'], 'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1',
                'flagged' => false, 'issues' => [],
            ]),
        ]);
        return [$typed, $chosen];
    }

    /**
     * The presenter shows the blank answer and the word lists, one word per line.
     */
    public function test_presenter_fill_types(): void {
        global $DB;
        $this->resetAfterTest();
        [, , , $job] = $this->make_job();
        [$typedid, $chosenid] = $this->add_fill_items($job->id);

        $typed = item_presenter::export($DB->get_record('local_aiqg_item', ['id' => $typedid]));
        $this->assertTrue($typed['iscloze']);
        $this->assertFalse($typed['isgap']);
        $this->assertSame('reluctant', $typed['fillanswer']);
        $this->assertSame('unwilling', $typed['alternatives']);
        $this->assertSame('', $typed['distractors']);

        $chosen = item_presenter::export($DB->get_record('local_aiqg_item', ['id' => $chosenid]));
        $this->assertTrue($chosen['isgap']);
        $this->assertSame("eager\nangry\ntired", $chosen['distractors']);
    }

    /**
     * Editing the typed blank: new answer and alternatives, one per line, blank lines dropped.
     */
    public function test_edit_typed_blank(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , $job] = $this->make_job();
        [$typedid] = $this->add_fill_items($job->id);
        [$item, $source] = item_service::load_item($typedid, $teacher->id);

        item_service::update_item($item, $source, 'Tom was ____ to go.', [], [
            'answer' => ' unwilling ', 'alternatives' => "reluctant\n\n  \nloath\r\n",
        ]);

        $payload = json_decode($DB->get_field('local_aiqg_item', 'payload', ['id' => $typedid]), true);
        $this->assertSame('unwilling', $payload['answer']);
        $this->assertSame(['reluctant', 'loath'], $payload['alternatives']);
        $this->assertSame('Tom was ____ to go.', $payload['stem']);
    }

    /**
     * Removing the blank from the sentence saves the edit but flags it and blocks approval.
     */
    public function test_edit_fill_flags_and_blocks_approval(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , $job] = $this->make_job();
        [, $chosenid] = $this->add_fill_items($job->id);
        [$item, $source, $context] = item_service::load_item($chosenid, $teacher->id);

        // Only two distractors, and no blank.
        $item = item_service::update_item($item, $source, 'Tom was reluctant.', [], ['distractors' => "eager\nangry"]);
        $payload = json_decode($DB->get_field('local_aiqg_item', 'payload', ['id' => $chosenid]), true);
        $this->assertTrue($payload['flagged']);
        $this->assertEqualsCanonicalizing(['noblank', 'distractorcount'], $payload['issues']);

        $this->expectException(\moodle_exception::class);
        item_service::set_status($item, $source, $context, 'approved');
    }

    /**
     * Valid fill-in-the-blank drafts can be approved, in bulk per type.
     */
    public function test_approve_fill_types(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , $job] = $this->make_job();
        [$typedid, $chosenid] = $this->add_fill_items($job->id);
        [$job, $source, $context] = item_service::load_job($job->id, $teacher->id);

        [$changed] = item_service::set_status_bulk($job, $source, $context, 'multianswer', 'approved');
        $this->assertCount(1, $changed);
        $this->assertSame('approved', $DB->get_field('local_aiqg_item', 'status', ['id' => $typedid]));
        $this->assertSame('pending', $DB->get_field('local_aiqg_item', 'status', ['id' => $chosenid]));
    }
}
