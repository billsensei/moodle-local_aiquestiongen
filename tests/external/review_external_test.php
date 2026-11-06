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

namespace local_aiquestiongen\external;

/**
 * Tests for the review web services, called as a real user.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(update_item::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_item_status::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(set_items_status::class)]
final class review_external_test extends \advanced_testcase {
    /**
     * Create a teacher with one job and two valid drafts.
     *
     * @return array [teacher, course, job id, item ids]
     */
    private function setup_job(): array {
        global $DB;
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $teacher = $gen->create_and_enrol($course, 'editingteacher');
        $sourceid = $DB->insert_record('local_aiqg_source', (object) [
            'courseid' => $course->id, 'userid' => $teacher->id, 'title' => 'T',
            'text' => 'Tom was reluctant to leave.', 'sourcefileitemid' => 0, 'timecreated' => time(),
        ]);
        $jobid = $DB->insert_record('local_aiqg_job', (object) [
            'sourceid' => $sourceid, 'settings' => '{}', 'status' => 'done', 'tokensused' => 0, 'timecreated' => time(),
        ]);
        $payload = json_encode([
            'type' => 'multichoice', 'stem' => 'What does "reluctant" mean?',
            'answers' => [
                ['text' => 'unwilling', 'correct' => true, 'feedback' => 'Yes.'],
                ['text' => 'eager', 'correct' => false, 'feedback' => ''],
                ['text' => 'angry', 'correct' => false, 'feedback' => ''],
                ['text' => 'tired', 'correct' => false, 'feedback' => ''],
            ],
            'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
        ]);
        $ids = [];
        foreach ([1, 2] as $unused) {
            $ids[] = $DB->insert_record('local_aiqg_item', (object) [
                'jobid' => $jobid, 'qtype' => 'multichoice', 'status' => 'pending', 'payload' => $payload, 'timemodified' => 0,
            ]);
        }
        return [$teacher, $course, $jobid, $ids];
    }

    /**
     * The owner can approve one draft and the full item comes back.
     */
    public function test_set_item_status(): void {
        $this->resetAfterTest();
        [$teacher, , , $ids] = $this->setup_job();
        $this->setUser($teacher);

        $result = set_item_status::execute($ids[0], 'approved');
        $result = \core_external\external_api::clean_returnvalue(set_item_status::execute_returns(), $result);
        $this->assertSame('approved', $result['status']);
        $this->assertTrue($result['isapproved']);
        $this->assertCount(4, $result['answers']);
    }

    /**
     * Editing through the service saves the new text, unchanged by return cleaning.
     */
    public function test_update_item(): void {
        $this->resetAfterTest();
        [$teacher, , , $ids] = $this->setup_job();
        $this->setUser($teacher);

        $answers = [
            ['text' => 'unwilling', 'correct' => false, 'feedback' => ''],
            ['text' => 'eager', 'correct' => true, 'feedback' => 'Hmm.'],
            ['text' => 'angry', 'correct' => false, 'feedback' => ''],
            ['text' => 'tired', 'correct' => false, 'feedback' => ''],
        ];
        $result = update_item::execute($ids[0], 'Changed <b>stem</b>?', $answers);
        $result = \core_external\external_api::clean_returnvalue(update_item::execute_returns(), $result);
        $this->assertSame('Changed <b>stem</b>?', $result['stem']);
        $this->assertTrue($result['answers'][1]['correct']);
        $this->assertFalse($result['flagged']);
    }

    /**
     * The bulk service returns the changed items.
     */
    public function test_set_items_status(): void {
        $this->resetAfterTest();
        [$teacher, , $jobid] = $this->setup_job();
        $this->setUser($teacher);

        $result = set_items_status::execute($jobid, 'multichoice', 'approved');
        $result = \core_external\external_api::clean_returnvalue(set_items_status::execute_returns(), $result);
        $this->assertCount(2, $result['items']);
        $this->assertSame(0, $result['skipped']);
    }

    /**
     * A different teacher and a student are refused by every function.
     */
    public function test_other_users_are_refused(): void {
        $this->resetAfterTest();
        [, $course, $jobid, $ids] = $this->setup_job();
        $gen = $this->getDataGenerator();
        $other = $gen->create_and_enrol($course, 'editingteacher');
        $student = $gen->create_and_enrol($course, 'student');

        foreach ([$other, $student] as $user) {
            $this->setUser($user);
            try {
                set_item_status::execute($ids[0], 'approved');
                $this->fail('set_item_status must be refused');
            } catch (\moodle_exception $e) {
                $this->assertNotEmpty($e->errorcode);
            }
            try {
                set_items_status::execute($jobid, 'multichoice', 'approved');
                $this->fail('set_items_status must be refused');
            } catch (\moodle_exception $e) {
                $this->assertNotEmpty($e->errorcode);
            }
            try {
                update_item::execute($ids[0], 'x', []);
                $this->fail('update_item must be refused');
            } catch (\moodle_exception $e) {
                $this->assertNotEmpty($e->errorcode);
            }
        }
    }

    /**
     * True/false and essay drafts can be edited through the web service and come back in full.
     */
    public function test_update_item_other_types(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , $jobid] = $this->setup_job();
        $this->setUser($teacher);

        $tfid = $DB->insert_record('local_aiqg_item', (object) [
            'jobid' => $jobid, 'qtype' => 'truefalse', 'status' => 'pending', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'truefalse', 'stem' => 'Tom stayed.', 'correct' => false, 'feedbacktrue' => '',
                'feedbackfalse' => '', 'focus' => 'comprehension', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
            ]),
        ]);
        $essayid = $DB->insert_record('local_aiqg_item', (object) [
            'jobid' => $jobid, 'qtype' => 'essay', 'status' => 'pending', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'essay', 'stem' => 'Why?', 'modelanswer' => 'Because.', 'rubric' => 'A reason.',
                'focus' => 'comprehension', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
            ]),
        ]);

        $result = update_item::execute($tfid, 'Tom left.', [], true, 'Yes!', 'No!');
        $result = \core_external\external_api::clean_returnvalue(update_item::execute_returns(), $result);
        $this->assertTrue($result['istf']);
        $this->assertTrue($result['correcttrue']);
        $this->assertFalse($result['correctfalse']);
        $this->assertSame('Yes!', $result['feedbacktrue']);
        $this->assertSame('Tom left.', $result['stem']);

        $result = update_item::execute($essayid, 'Why <now>?', [], null, null, null, 'Because <x>.', 'Two reasons.');
        $result = \core_external\external_api::clean_returnvalue(update_item::execute_returns(), $result);
        $this->assertTrue($result['isessay']);
        $this->assertSame('Because <x>.', $result['modelanswer']);
        $this->assertSame('Two reasons.', $result['rubric']);
        $this->assertSame('Why <now>?', $result['stem']);
        $this->assertFalse($result['flagged']);
    }

    /**
     * Fill-in-the-blank drafts can be edited through the web service, with one word per line.
     */
    public function test_update_item_fill_types(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , $jobid] = $this->setup_job();
        $this->setUser($teacher);

        $typed = $DB->insert_record('local_aiqg_item', (object) [
            'jobid' => $jobid, 'qtype' => 'multianswer', 'status' => 'pending', 'timemodified' => 0,
            'payload' => json_encode([
                'type' => 'multianswer', 'stem' => 'Tom was ____ to leave.', 'answer' => 'reluctant', 'alternatives' => [],
                'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
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

        $result = update_item::execute(
            $typed,
            'Tom was ____ to go.',
            fillanswer: 'reluctant',
            alternatives: "unwilling\nloath"
        );
        $result = \core_external\external_api::clean_returnvalue(update_item::execute_returns(), $result);
        $this->assertTrue($result['iscloze']);
        $this->assertSame('reluctant', $result['fillanswer']);
        $this->assertSame("unwilling\nloath", $result['alternatives']);
        $this->assertFalse($result['flagged']);

        $result = update_item::execute($chosen, 'Tom was ____ to go.', distractors: "eager\nangry\nsad");
        $result = \core_external\external_api::clean_returnvalue(update_item::execute_returns(), $result);
        $this->assertTrue($result['isgap']);
        $this->assertSame("eager\nangry\nsad", $result['distractors']);
        $this->assertFalse($result['flagged']);
    }

    /**
     * Regenerate is refused for other teachers and students before any AI call is made.
     */
    public function test_regenerate_access(): void {
        $this->resetAfterTest();
        [, $course, , $ids] = $this->setup_job();
        $gen = $this->getDataGenerator();
        foreach ([$gen->create_and_enrol($course, 'editingteacher'), $gen->create_and_enrol($course, 'student')] as $user) {
            $this->setUser($user);
            try {
                regenerate_item::execute($ids[0], 'easier');
                $this->fail('Regenerate must be refused');
            } catch (\moodle_exception $e) {
                $this->assertNotEmpty($e->errorcode);
                $this->assertNotSame('err_aifailed', $e->errorcode);
            }
        }
    }

    /**
     * The owner gets as far as the AI call; with no provider configured the failure is clear and the draft is unchanged.
     */
    public function test_regenerate_without_provider(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , $ids] = $this->setup_job();
        $this->setUser($teacher);
        $before = $DB->get_field('local_aiqg_item', 'payload', ['id' => $ids[0]]);

        try {
            regenerate_item::execute($ids[0], 'easier');
            $this->fail('No provider is configured');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_aifailed', $e->errorcode);
        }
        $this->assertSame($before, $DB->get_field('local_aiqg_item', 'payload', ['id' => $ids[0]]));
    }

    /**
     * The daily regeneration limit is enforced for the logged-in teacher, before any AI request is made.
     */
    public function test_regenerate_daily_limit(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , $ids] = $this->setup_job();
        $this->setUser($teacher);
        set_config('dailyregenlimit', '1', 'local_aiquestiongen');

        // The first request reaches the AI (and fails, as no provider is configured); it still uses the allowance.
        try {
            regenerate_item::execute($ids[0], 'easier');
            $this->fail('No provider is configured');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_aifailed', $e->errorcode);
        }
        $requests = $DB->count_records('ai_action_register');

        try {
            regenerate_item::execute($ids[1], 'easier');
            $this->fail('The daily limit must be enforced');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_regendaily', $e->errorcode);
        }
        $this->assertEquals($requests, $DB->count_records('ai_action_register'));
    }

    /**
     * Regenerate is refused when the plugin is switched off, before any AI request is made.
     */
    public function test_regenerate_refused_when_disabled(): void {
        global $DB;
        $this->resetAfterTest();
        [$teacher, , , $ids] = $this->setup_job();
        $this->setUser($teacher);
        set_config('enabled', 0, 'local_aiquestiongen');

        try {
            regenerate_item::execute($ids[0], 'easier');
            $this->fail('Regenerate must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('disabled', $e->errorcode);
        }
        $this->assertEquals(0, $DB->count_records('ai_action_register'));

        // Editing and approving still work, so drafts are not lost.
        $result = set_item_status::execute($ids[0], 'approved');
        $this->assertSame('approved', $result['status']);
    }
}
