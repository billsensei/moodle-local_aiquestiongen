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
 * Tests for the generation job runner, using a scripted fake AI client.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(generator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(prompt_builder::class)]
final class generator_test extends \advanced_testcase {
    /** @var string Article text. */
    private const TEXT = 'Tom was reluctant to leave. He had lived there for years.';

    /**
     * Build a valid question array.
     *
     * @param string $stem The question text.
     * @return array
     */
    private static function question(string $stem = 'What does "reluctant" mean?'): array {
        return [
            'type' => 'multichoice',
            'stem' => $stem,
            'answers' => [
                ['text' => 'unwilling', 'correct' => true, 'feedback' => 'Yes.'],
                ['text' => 'eager', 'correct' => false, 'feedback' => 'No.'],
                ['text' => 'angry', 'correct' => false, 'feedback' => 'No.'],
                ['text' => 'tired', 'correct' => false, 'feedback' => 'No.'],
            ],
            'focus' => 'vocabulary',
            'target' => 'reluctant',
            'cefr' => 'B1',
        ];
    }

    /**
     * A fake client that returns scripted replies (strings, or exceptions to throw) in order.
     *
     * @param array $script Replies.
     * @return ai_client
     */
    private function fake(array $script): ai_client {
        return new class ($script) implements ai_client {
            /** @var array Prompts received. */
            public array $prompts = [];

            /**
             * Constructor.
             *
             * @param array $script Replies.
             */
            public function __construct(
                /** @var array Remaining replies. */
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
                return new ai_result($next, 'fake-model', 10);
            }
        };
    }

    /**
     * Create a source and a queued job.
     *
     * @param int $count Questions requested.
     * @return int Job id.
     */
    private function make_job(int $count = 2): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $sourceid = $DB->insert_record('local_aiqg_source', (object) [
            'courseid' => $course->id, 'userid' => $user->id, 'title' => 'T',
            'text' => self::TEXT, 'sourcefileitemid' => 0, 'timecreated' => time(),
        ]);
        return $DB->insert_record('local_aiqg_job', (object) [
            'sourceid' => $sourceid,
            'settings' => json_encode(['cefr' => 'B1', 'count' => $count, 'target' => '', 'language' => 'English']),
            'status' => 'queued', 'tokensused' => 0, 'timecreated' => time(),
        ]);
    }

    /**
     * Valid questions are stored as pending, unflagged drafts.
     */
    public function test_happy_path(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job(2);

        $client = $this->fake([json_encode([self::question('One?'), self::question('Two?')])]);
        (new generator($client))->run($jobid);

        $job = $DB->get_record('local_aiqg_job', ['id' => $jobid]);
        $this->assertSame('done', $job->status);
        $this->assertSame('fake-model', $job->provider);
        $this->assertEquals(10, $job->tokensused);

        $items = $DB->get_records('local_aiqg_item', ['jobid' => $jobid], 'id');
        $this->assertCount(2, $items);
        foreach ($items as $item) {
            $this->assertSame('pending', $item->status);
            $this->assertSame('multichoice', $item->qtype);
            $this->assertNull($item->questionid);
            $this->assertFalse(json_decode($item->payload, true)['flagged']);
        }
        $this->assertCount(1, $client->prompts);
        $this->assertStringContainsString('Tom was reluctant', $client->prompts[0]);
    }

    /**
     * More questions than requested are cut to the requested number.
     */
    public function test_extra_items_are_ignored(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job(1);

        $client = $this->fake([json_encode([self::question('One?'), self::question('Two?')])]);
        (new generator($client))->run($jobid);

        $this->assertEquals(1, $DB->count_records('local_aiqg_item', ['jobid' => $jobid]));
    }

    /**
     * A failing item is retried once and the replacement is stored.
     */
    public function test_item_retry_succeeds(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job(1);

        $broken = self::question('Broken?');
        $broken['answers'][1]['correct'] = true;
        $client = $this->fake([json_encode([$broken]), json_encode([self::question('Fixed?')])]);
        (new generator($client))->run($jobid);

        $item = $DB->get_record('local_aiqg_item', ['jobid' => $jobid], '*', MUST_EXIST);
        $payload = json_decode($item->payload, true);
        $this->assertSame('Fixed?', $payload['stem']);
        $this->assertFalse($payload['flagged']);
        $this->assertCount(2, $client->prompts);
        $this->assertStringContainsString('Broken?', $client->prompts[1]);
        $this->assertStringContainsString('not exactly one correct answer', $client->prompts[1]);
    }

    /**
     * An item that still fails after the retry is kept, flagged, not dropped.
     */
    public function test_item_retry_fails_is_flagged(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job(1);

        $broken = self::question('Broken?');
        $broken['answers'][1]['correct'] = true;
        $client = $this->fake([json_encode([$broken]), json_encode([$broken])]);
        (new generator($client))->run($jobid);

        $this->assertSame('done', $DB->get_field('local_aiqg_job', 'status', ['id' => $jobid]));
        $item = $DB->get_record('local_aiqg_item', ['jobid' => $jobid], '*', MUST_EXIST);
        $payload = json_decode($item->payload, true);
        $this->assertSame('Broken?', $payload['stem']);
        $this->assertTrue($payload['flagged']);
        $this->assertSame(['correctcount'], $payload['issues']);
    }

    /**
     * An AI error during the item retry still leaves the flagged original.
     */
    public function test_item_retry_exception_is_flagged(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job(1);

        $broken = self::question('Broken?');
        $broken['stem'] = '';
        $client = $this->fake([json_encode([$broken]), new ai_exception('boom')]);
        (new generator($client))->run($jobid);

        $item = $DB->get_record('local_aiqg_item', ['jobid' => $jobid], '*', MUST_EXIST);
        $this->assertTrue(json_decode($item->payload, true)['flagged']);
    }

    /**
     * An unreadable reply is retried once, then the job fails with a clear message.
     */
    public function test_unparseable_twice_fails_job(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job();

        $client = $this->fake(['Sorry, I cannot.', 'Still no.']);
        (new generator($client))->run($jobid);

        $job = $DB->get_record('local_aiqg_job', ['id' => $jobid]);
        $this->assertSame('failed', $job->status);
        $this->assertSame(get_string('err_unparseable', 'local_aiquestiongen'), $job->error);
        $this->assertEquals(0, $DB->count_records('local_aiqg_item', ['jobid' => $jobid]));
    }

    /**
     * An unreadable first reply followed by a good one succeeds.
     */
    public function test_unparseable_then_ok(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job(1);

        $client = $this->fake(['nope', json_encode([self::question()])]);
        (new generator($client))->run($jobid);

        $this->assertSame('done', $DB->get_field('local_aiqg_job', 'status', ['id' => $jobid]));
    }

    /**
     * An AI backend failure fails the job with the provider's message.
     */
    public function test_ai_exception_fails_job(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job();

        (new generator($this->fake([new ai_exception('quota exceeded')])))->run($jobid);

        $job = $DB->get_record('local_aiqg_job', ['id' => $jobid]);
        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('quota exceeded', $job->error);
    }

    /**
     * An unexpected error never leaves the job stuck in "running" or leaks internals.
     */
    public function test_unexpected_error_fails_job(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job();

        (new generator($this->fake([new \RuntimeException('secret internals')])))->run($jobid);

        $job = $DB->get_record('local_aiqg_job', ['id' => $jobid]);
        $this->assertSame('failed', $job->status);
        $this->assertStringNotContainsString('secret', $job->error);
        $this->assertDebuggingCalled();
    }

    /**
     * The prompt keeps article text unescaped and carries the settings.
     */
    public function test_prompt_contents(): void {
        $this->resetAfterTest();
        $prompt = prompt_builder::multichoice('He said "no" & left <fast>.', [
            'cefr' => 'A2', 'count' => 3, 'target' => 'reluctant', 'language' => 'Japanese',
        ]);
        $this->assertStringContainsString('He said "no" & left <fast>.', $prompt);
        $this->assertStringContainsString('CEFR A2', $prompt);
        $this->assertStringContainsString('Write 3 multiple-choice', $prompt);
        $this->assertStringContainsString('reluctant', $prompt);
        $this->assertStringContainsString('Japanese', $prompt);
    }

    /**
     * A true/false question array.
     *
     * @param string $stem The statement.
     * @return array
     */
    private static function tf_question(string $stem = 'Tom stayed.'): array {
        return [
            'type' => 'truefalse', 'stem' => $stem, 'correct' => false, 'feedbacktrue' => 'No.',
            'feedbackfalse' => 'Right.', 'focus' => 'comprehension', 'cefr' => 'B1',
        ];
    }

    /**
     * An essay question array.
     *
     * @param string $stem The question.
     * @return array
     */
    private static function essay_question(string $stem = 'Why did Tom leave?'): array {
        return [
            'type' => 'essay', 'stem' => $stem, 'modelanswer' => 'He had to go.',
            'rubric' => 'Gives a reason.', 'focus' => 'comprehension', 'cefr' => 'B1',
        ];
    }

    /**
     * Create a queued job with explicit settings.
     *
     * @param array $settings Job settings.
     * @return int Job id.
     */
    private function make_job_with(array $settings): int {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $sourceid = $DB->insert_record('local_aiqg_source', (object) [
            'courseid' => $course->id, 'userid' => $user->id, 'title' => 'T',
            'text' => self::TEXT, 'sourcefileitemid' => 0, 'timecreated' => time(),
        ]);
        return $DB->insert_record('local_aiqg_job', (object) [
            'sourceid' => $sourceid,
            'settings' => json_encode($settings + ['cefr' => 'B1', 'target' => '', 'language' => 'English']),
            'status' => 'queued', 'tokensused' => 0, 'timecreated' => time(),
        ]);
    }

    /**
     * One job can produce all three types, one AI call per type, stored in order.
     */
    public function test_all_three_types(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job_with(['count' => 1, 'tfcount' => 2, 'essaycount' => 1]);

        $client = $this->fake([
            json_encode([self::question()]),
            json_encode([self::tf_question('One.'), self::tf_question('Two.')]),
            json_encode([self::essay_question()]),
        ]);
        (new generator($client))->run($jobid);

        $this->assertSame('done', $DB->get_field('local_aiqg_job', 'status', ['id' => $jobid]));
        $items = array_values($DB->get_records('local_aiqg_item', ['jobid' => $jobid], 'id'));
        $this->assertSame(['multichoice', 'truefalse', 'truefalse', 'essay'], array_column($items, 'qtype'));
        foreach ($items as $item) {
            $this->assertFalse(json_decode($item->payload, true)['flagged']);
        }
        $this->assertCount(3, $client->prompts);
        $this->assertStringContainsString('true/false statements', $client->prompts[1]);
        $this->assertStringContainsString('Write 2 true/false', $client->prompts[1]);
        $this->assertStringContainsString('open-ended questions', $client->prompts[2]);
        $this->assertEquals(30, $DB->get_field('local_aiqg_job', 'tokensused', ['id' => $jobid]));
    }

    /**
     * Types that were not requested are not generated.
     */
    public function test_only_requested_types(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job_with(['count' => 0, 'essaycount' => 1]);

        $client = $this->fake([json_encode([self::essay_question()])]);
        (new generator($client))->run($jobid);

        $this->assertCount(1, $client->prompts);
        $items = $DB->get_records('local_aiqg_item', ['jobid' => $jobid]);
        $this->assertSame(['essay'], array_values(array_column($items, 'qtype')));
    }

    /**
     * If one type fails, the drafts of the other types are kept and the job is done with a note.
     */
    public function test_one_type_failing_keeps_the_others(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job_with(['count' => 1, 'tfcount' => 1]);

        // Multiple choice works; the true/false reply is junk twice.
        $client = $this->fake([json_encode([self::question()]), 'junk', 'still junk']);
        (new generator($client))->run($jobid);

        $job = $DB->get_record('local_aiqg_job', ['id' => $jobid]);
        $this->assertSame('done', $job->status);
        $this->assertSame(get_string('err_unparseable', 'local_aiquestiongen'), $job->error);
        $this->assertEquals(1, $DB->count_records('local_aiqg_item', ['jobid' => $jobid]));
    }

    /**
     * If every type fails, the job fails.
     */
    public function test_all_types_failing_fails_job(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job_with(['count' => 1, 'tfcount' => 1]);

        $client = $this->fake(['a', 'b', 'c', 'd']);
        (new generator($client))->run($jobid);

        $this->assertSame('failed', $DB->get_field('local_aiqg_job', 'status', ['id' => $jobid]));
    }

    /**
     * A failing essay is retried once with its own prompt, and is flagged if it still fails.
     */
    public function test_essay_retry_and_flag(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job_with(['count' => 0, 'essaycount' => 1]);

        $bad = self::essay_question('No guide?');
        $bad['rubric'] = '';
        $client = $this->fake([json_encode([$bad]), json_encode([$bad])]);
        (new generator($client))->run($jobid);

        $this->assertCount(2, $client->prompts);
        $this->assertStringContainsString('marking guide is missing', $client->prompts[1]);
        $item = $DB->get_record('local_aiqg_item', ['jobid' => $jobid], '*', MUST_EXIST);
        $payload = json_decode($item->payload, true);
        $this->assertTrue($payload['flagged']);
        $this->assertSame(['norubric'], $payload['issues']);
    }

    /**
     * A true/false item with a string "true" is retried, because only booleans are accepted.
     */
    public function test_tf_string_boolean_is_retried(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job_with(['count' => 0, 'tfcount' => 1]);

        $bad = self::tf_question('Stringy.');
        $bad['correct'] = 'true';
        $client = $this->fake([json_encode([$bad]), json_encode([self::tf_question('Fixed.')])]);
        (new generator($client))->run($jobid);

        $payload = json_decode($DB->get_field('local_aiqg_item', 'payload', ['jobid' => $jobid]), true);
        $this->assertSame('Fixed.', $payload['stem']);
        $this->assertFalse($payload['flagged']);
    }

    /**
     * A typed fill-in-the-blank question array.
     *
     * @param string $stem The sentence with a blank.
     * @return array
     */
    private static function cloze_question(string $stem = 'Tom was ____ to leave.'): array {
        return [
            'type' => 'multianswer', 'stem' => $stem, 'answer' => 'reluctant', 'alternatives' => [],
            'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1',
        ];
    }

    /**
     * A chosen fill-in-the-blank question array.
     *
     * @return array
     */
    private static function gap_question(): array {
        return [
            'type' => 'gapselect', 'stem' => 'Tom was ____ to leave.', 'answer' => 'reluctant',
            'distractors' => ['eager', 'angry', 'tired'], 'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1',
        ];
    }

    /**
     * Both fill-in-the-blank types can be generated in one job alongside the others.
     */
    public function test_fill_in_the_blank_types(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job_with(['count' => 1, 'clozecount' => 2, 'gapcount' => 1]);

        $client = $this->fake([
            json_encode([self::question()]),
            json_encode([self::cloze_question('One ____ here.'), self::cloze_question()]),
            json_encode([self::gap_question()]),
        ]);
        (new generator($client))->run($jobid);

        $this->assertSame('done', $DB->get_field('local_aiqg_job', 'status', ['id' => $jobid]));
        $items = array_values($DB->get_records('local_aiqg_item', ['jobid' => $jobid], 'id'));
        $this->assertSame(['multichoice', 'multianswer', 'multianswer', 'gapselect'], array_column($items, 'qtype'));
        $this->assertSame([false], array_values(array_unique(array_map(
            fn($i) => json_decode($i->payload, true)['flagged'],
            array_slice($items, 2)
        ))));
        $this->assertStringContainsString('TYPES the missing word', $client->prompts[1]);
        $this->assertStringContainsString('CHOOSES the missing word', $client->prompts[2]);
    }

    /**
     * A sentence with no blank is retried with the problem described, then flagged if still wrong.
     */
    public function test_cloze_without_blank_is_retried_then_flagged(): void {
        global $DB;
        $this->resetAfterTest();
        $jobid = $this->make_job_with(['count' => 0, 'clozecount' => 1]);

        $bad = self::cloze_question('Tom was reluctant to leave.');
        $client = $this->fake([json_encode([$bad]), json_encode([$bad])]);
        (new generator($client))->run($jobid);

        $this->assertCount(2, $client->prompts);
        $this->assertStringContainsString('exactly one blank', $client->prompts[1]);
        $payload = json_decode($DB->get_field('local_aiqg_item', 'payload', ['jobid' => $jobid]), true);
        $this->assertTrue($payload['flagged']);
        $this->assertSame(['noblank'], $payload['issues']);
    }
}
