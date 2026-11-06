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

use local_aiquestiongen\ai\ai_client;
use local_aiquestiongen\ai\ai_exception;
use local_aiquestiongen\ai\ai_result;

/**
 * Tests for regenerating one draft.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(regenerator::class)]
final class regenerator_test extends \advanced_testcase {
    /**
     * A fake client with scripted replies.
     *
     * @param array $script Reply strings, or exceptions to throw.
     * @return ai_client
     */
    private function fake(array $script): ai_client {
        return new class ($script) implements ai_client {
            /** @var string[] Prompts received. */
            public array $prompts = [];

            /**
             * Constructor.
             *
             * @param array $script Replies.
             */
            public function __construct(
                /** @var array Replies left. */
                private array $script
            ) {
            }

            #[\Override]
            public function generate_text(string $prompt): ai_result {
                $this->prompts[] = $prompt;
                $next = array_shift($this->script);
                if ($next instanceof \Throwable) {
                    throw $next;
                }
                return new ai_result($next, 'fake', 25);
            }
        };
    }

    /**
     * A valid true/false question as JSON.
     *
     * @param string $stem The statement.
     * @return string
     */
    private static function tf_json(string $stem = 'Tom left early.'): string {
        return json_encode([[
            'type' => 'truefalse', 'stem' => $stem, 'correct' => true, 'feedbacktrue' => 'Yes.',
            'feedbackfalse' => 'No.', 'focus' => 'comprehension', 'cefr' => 'B1',
        ]]);
    }

    /**
     * Create a job and one true/false draft.
     *
     * @param array $payloadextra Extra payload fields.
     * @param string $status Item status.
     * @return array [item, job, source]
     */
    private function setup_item(array $payloadextra = [], string $status = 'pending'): array {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $source = (object) [
            'courseid' => $course->id, 'userid' => $user->id, 'title' => 'T',
            'text' => 'Tom was reluctant to leave.', 'sourcefileitemid' => 0, 'timecreated' => time(),
        ];
        $source->id = $DB->insert_record('local_aiquestiongen_source', $source);
        $job = (object) [
            'sourceid' => $source->id, 'settings' => json_encode(['cefr' => 'B1', 'language' => 'English', 'target' => '']),
            'status' => 'done', 'tokensused' => 100, 'timecreated' => time(),
        ];
        $job->id = $DB->insert_record('local_aiquestiongen_job', $job);
        $payload = $payloadextra + [
            'type' => 'truefalse', 'stem' => 'Old statement.', 'correct' => false, 'feedbacktrue' => '',
            'feedbackfalse' => '', 'focus' => 'comprehension', 'cefr' => 'B1', 'flagged' => false, 'issues' => [],
        ];
        $item = (object) [
            'jobid' => $job->id, 'qtype' => 'truefalse', 'status' => $status, 'timemodified' => 0,
            'payload' => json_encode($payload),
        ];
        $item->id = $DB->insert_record('local_aiquestiongen_item', $item);
        return [$item, $job, $source];
    }

    /**
     * A successful regeneration replaces the draft in place, clears flags, counts and returns it to pending.
     */
    public function test_regenerate_replaces_draft(): void {
        global $DB;
        $this->resetAfterTest();
        [$item, $job, $source] = $this->setup_item(['flagged' => true, 'issues' => ['nocorrect']], 'approved');

        $client = $this->fake([self::tf_json('New statement.')]);
        $result = (new regenerator($client))->regenerate($item, $job, $source, '  Make it   easier  ');

        $record = $DB->get_record('local_aiquestiongen_item', ['id' => $item->id], '*', MUST_EXIST);
        $payload = json_decode($record->payload, true);
        $this->assertSame('New statement.', $payload['stem']);
        $this->assertTrue($payload['correct']);
        $this->assertFalse($payload['flagged']);
        $this->assertSame([], $payload['issues']);
        $this->assertSame(1, $payload['regenerations']);
        $this->assertSame('pending', $record->status);
        $this->assertSame('pending', $result->status);
        $this->assertEquals(125, $DB->get_field('local_aiquestiongen_job', 'tokensused', ['id' => $job->id]));

        // The prompt carries the comment (whitespace tidied), the old question and the earlier problem.
        $this->assertCount(1, $client->prompts);
        $this->assertStringContainsString('Teacher comment: Make it easier', $client->prompts[0]);
        $this->assertStringContainsString('Old statement.', $client->prompts[0]);
        $this->assertStringContainsString(get_string('issue_nocorrect', 'local_aiquestiongen'), $client->prompts[0]);
        $this->assertStringContainsString('Tom was reluctant to leave.', $client->prompts[0]);
    }

    /**
     * Without a comment the prompt still says the teacher wants a different question.
     */
    public function test_regenerate_without_comment(): void {
        $this->resetAfterTest();
        [$item, $job, $source] = $this->setup_item();
        $client = $this->fake([self::tf_json()]);
        (new regenerator($client))->regenerate($item, $job, $source);
        $this->assertStringContainsString('wants this question replaced', $client->prompts[0]);
        $this->assertStringNotContainsString('Teacher comment', $client->prompts[0]);
    }

    /**
     * A comment is cut to the maximum length before it is sent.
     */
    public function test_long_comment_is_cut(): void {
        $this->resetAfterTest();
        [$item, $job, $source] = $this->setup_item();
        $client = $this->fake([self::tf_json()]);
        (new regenerator($client))->regenerate($item, $job, $source, str_repeat('a', 3000));
        $this->assertStringContainsString(str_repeat('a', regenerator::MAX_COMMENT), $client->prompts[0]);
        $this->assertStringNotContainsString(str_repeat('a', regenerator::MAX_COMMENT + 1), $client->prompts[0]);
    }

    /**
     * An invalid first reply gets one more try; two invalid replies keep the original and report it.
     */
    public function test_invalid_replies(): void {
        global $DB;
        $this->resetAfterTest();
        [$item, $job, $source] = $this->setup_item();
        $bad = json_encode([['type' => 'truefalse', 'stem' => 'No answer given.', 'focus' => 'comprehension']]);

        $client = $this->fake([$bad, self::tf_json('Second try.')]);
        (new regenerator($client))->regenerate($item, $job, $source);
        $this->assertCount(2, $client->prompts);
        $payload = json_decode($DB->get_field('local_aiquestiongen_item', 'payload', ['id' => $item->id]), true);
        $this->assertSame('Second try.', $payload['stem']);

        [$item, $job, $source] = $this->setup_item();
        $client = $this->fake([$bad, 'not json at all']);
        try {
            (new regenerator($client))->regenerate($item, $job, $source);
            $this->fail('Two invalid replies must fail');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_regenfailed', $e->errorcode);
        }
        $payload = json_decode($DB->get_field('local_aiquestiongen_item', 'payload', ['id' => $item->id]), true);
        $this->assertSame('Old statement.', $payload['stem']);
        $this->assertArrayNotHasKey('regenerations', $payload);
        // The tokens of the failed tries are still counted.
        $this->assertEquals(150, $DB->get_field('local_aiquestiongen_job', 'tokensused', ['id' => $job->id]));
    }

    /**
     * An AI backend error passes through and leaves the draft alone.
     */
    public function test_ai_exception_leaves_draft(): void {
        global $DB;
        $this->resetAfterTest();
        [$item, $job, $source] = $this->setup_item();
        try {
            (new regenerator($this->fake([new ai_exception('quota')])))->regenerate($item, $job, $source);
            $this->fail('Should fail');
        } catch (ai_exception $e) {
            $this->assertStringContainsString('quota', $e->getMessage());
        }
        $payload = json_decode($DB->get_field('local_aiquestiongen_item', 'payload', ['id' => $item->id]), true);
        $this->assertSame('Old statement.', $payload['stem']);
    }

    /**
     * Published drafts cannot be regenerated, and each draft has a limit.
     */
    public function test_limits(): void {
        $this->resetAfterTest();
        [$published, $job, $source] = $this->setup_item([], 'published');
        $this->assertFalse(regenerator::can_regenerate($published));
        try {
            (new regenerator($this->fake([])))->regenerate($published, $job, $source);
            $this->fail('Published must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_published', $e->errorcode);
        }

        [$tired, $job, $source] = $this->setup_item(['regenerations' => regenerator::MAX_PER_ITEM]);
        $this->assertFalse(regenerator::can_regenerate($tired));
        try {
            (new regenerator($this->fake([])))->regenerate($tired, $job, $source);
            $this->fail('The limit must be enforced');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_regenlimit', $e->errorcode);
        }

        [$fresh] = $this->setup_item(['regenerations' => regenerator::MAX_PER_ITEM - 1]);
        $this->assertTrue(regenerator::can_regenerate($fresh));
    }

    /**
     * The daily limit counts every request that reaches the AI, and stops the next one before it does.
     */
    public function test_daily_limit(): void {
        $this->resetAfterTest();
        set_config('dailyregenlimit', '2', 'local_aiquestiongen');
        [$item, $job, $source] = $this->setup_item();
        $user = $this->getDataGenerator()->create_user();

        // A success counts, and so does a failure (two unusable replies still cost two AI calls).
        (new regenerator($this->fake([self::tf_json()])))->regenerate($item, $job, $source, '', $user->id);
        $this->assertSame(1, regen_budget::used_today($user->id));
        try {
            (new regenerator($this->fake(['no json', 'still no json'])))->regenerate($item, $job, $source, '', $user->id);
            $this->fail('No usable replacement');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_regenfailed', $e->errorcode);
        }
        $this->assertSame(2, regen_budget::used_today($user->id));

        // The limit is reached: refused, and the AI is not called.
        $client = $this->fake([self::tf_json('Never used.')]);
        try {
            (new regenerator($client))->regenerate($item, $job, $source, '', $user->id);
            $this->fail('The daily limit must be enforced');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_regendaily', $e->errorcode);
        }
        $this->assertSame([], $client->prompts);
    }

    /**
     * Requests that are refused before the AI would be called do not use up the allowance.
     */
    public function test_refused_requests_are_not_counted(): void {
        $this->resetAfterTest();
        set_config('dailyregenlimit', '5', 'local_aiquestiongen');
        $user = $this->getDataGenerator()->create_user();

        [$published, $job, $source] = $this->setup_item([], 'published');
        [$tired, $job2, $source2] = $this->setup_item(['regenerations' => regenerator::MAX_PER_ITEM]);
        foreach ([[$published, $job, $source], [$tired, $job2, $source2]] as [$item, $itemjob, $itemsource]) {
            try {
                (new regenerator($this->fake([])))->regenerate($item, $itemjob, $itemsource, '', $user->id);
                $this->fail('Must be refused');
            } catch (\moodle_exception $e) {
                $this->assertContains($e->errorcode, ['err_published', 'err_regenlimit']);
            }
        }
        $this->assertSame(0, regen_budget::used_today($user->id));
    }

    /**
     * The presenter tells the page whether the button should show.
     */
    public function test_presenter_flag(): void {
        $this->resetAfterTest();
        [$item] = $this->setup_item();
        $this->assertTrue(item_presenter::export($item)['canregenerate']);
        $item->status = 'published';
        $this->assertFalse(item_presenter::export($item)['canregenerate']);
    }
}
