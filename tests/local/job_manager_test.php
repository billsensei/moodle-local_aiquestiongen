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
 * Tests for job creation and limits.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(job_manager::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_aiquestiongen\task\generate_questions::class)]
final class job_manager_test extends \advanced_testcase {
    /**
     * Create a source owned by a new user.
     *
     * @return \stdClass The source record.
     */
    private function make_source(): \stdClass {
        global $DB;
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $record = (object) [
            'courseid' => $course->id, 'userid' => $user->id, 'title' => 'T',
            'text' => 'Some text.', 'sourcefileitemid' => 0, 'timecreated' => time(),
        ];
        $record->id = $DB->insert_record('local_aiquestiongen_source', $record);
        return $record;
    }

    /**
     * Valid settings create a queued job and queue one ad hoc task.
     */
    public function test_create_job_queues_task(): void {
        global $DB;
        $this->resetAfterTest();
        $source = $this->make_source();

        $jobid = job_manager::create_job($source, $source->userid, [
            'cefr' => 'B2', 'count' => 4, 'target' => ' reluctant ', 'language' => 'Japanese',
        ]);

        $job = $DB->get_record('local_aiquestiongen_job', ['id' => $jobid], '*', MUST_EXIST);
        $this->assertSame('queued', $job->status);
        $settings = json_decode($job->settings, true);
        $this->assertSame('B2', $settings['cefr']);
        $this->assertSame(4, $settings['count']);
        $this->assertSame('reluctant', $settings['target']);

        $tasks = \core\task\manager::get_adhoc_tasks(\local_aiquestiongen\task\generate_questions::class);
        $this->assertCount(1, $tasks);
        $task = reset($tasks);
        $this->assertEquals($jobid, $task->get_custom_data()->jobid);
        $this->assertEquals($source->userid, $task->get_userid());
    }

    /**
     * The count must be within the admin maximum.
     */
    public function test_count_limits(): void {
        $this->resetAfterTest();
        set_config('maxquestionsperjob', 5, 'local_aiquestiongen');
        $source = $this->make_source();

        foreach ([0, 6, -1] as $count) {
            try {
                job_manager::create_job($source, $source->userid, ['cefr' => 'B1', 'count' => $count]);
                $this->fail("Count $count should be rejected");
            } catch (\moodle_exception $e) {
                $this->assertSame('err_count', $e->errorcode);
            }
        }
        $this->assertGreaterThan(0, job_manager::create_job($source, $source->userid, ['cefr' => 'B1', 'count' => 5]));
    }

    /**
     * An unknown CEFR level is rejected.
     */
    public function test_bad_cefr(): void {
        $this->resetAfterTest();
        $source = $this->make_source();
        $this->expectException(\moodle_exception::class);
        job_manager::create_job($source, $source->userid, ['cefr' => 'Z9', 'count' => 3]);
    }

    /**
     * The daily limit stops further jobs for that user only, and 0 means no limit.
     */
    public function test_daily_limit(): void {
        $this->resetAfterTest();
        set_config('dailyjoblimit', 2, 'local_aiquestiongen');
        $source = $this->make_source();
        $other = $this->make_source();
        $settings = ['cefr' => 'B1', 'count' => 3];

        job_manager::create_job($source, $source->userid, $settings);
        job_manager::create_job($source, $source->userid, $settings);
        try {
            job_manager::create_job($source, $source->userid, $settings);
            $this->fail('Third job should be rejected');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_dailylimit', $e->errorcode);
        }

        // Another user is unaffected.
        $this->assertGreaterThan(0, job_manager::create_job($other, $other->userid, $settings));

        set_config('dailyjoblimit', 0, 'local_aiquestiongen');
        $this->assertGreaterThan(0, job_manager::create_job($source, $source->userid, $settings));
    }

    /**
     * The task ignores a missing job and a job that is not queued.
     */
    public function test_task_ignores_missing_and_handled_jobs(): void {
        global $DB;
        $this->resetAfterTest();

        $task = new \local_aiquestiongen\task\generate_questions();
        $task->set_custom_data(['jobid' => 99999]);
        $task->execute();

        $source = $this->make_source();
        $jobid = job_manager::create_job($source, $source->userid, ['cefr' => 'B1', 'count' => 3]);
        $DB->set_field('local_aiquestiongen_job', 'status', 'done', ['id' => $jobid]);
        $task = new \local_aiquestiongen\task\generate_questions();
        $task->set_custom_data(['jobid' => $jobid]);
        $task->execute();

        $this->assertSame('done', $DB->get_field('local_aiquestiongen_job', 'status', ['id' => $jobid]));
    }

    /**
     * The question limit covers all types together, and a job needs at least one question.
     */
    public function test_total_limit_across_types(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('maxquestionsperjob', 5, 'local_aiquestiongen');
        $source = $this->make_source();

        $rejected = [
            ['count' => 3, 'tfcount' => 3],
            ['count' => 0, 'tfcount' => 0, 'essaycount' => 0],
            ['essaycount' => 6],
        ];
        foreach ($rejected as $counts) {
            try {
                job_manager::create_job($source, $source->userid, ['cefr' => 'B1'] + $counts);
                $this->fail('These counts should be rejected: ' . json_encode($counts));
            } catch (\moodle_exception $e) {
                $this->assertSame('err_count', $e->errorcode);
            }
        }

        // A job with only true/false and essay is fine, and the settings record all three counts.
        $jobid = job_manager::create_job($source, $source->userid, [
            'cefr' => 'B1', 'count' => 0, 'tfcount' => 3, 'essaycount' => 2,
        ]);
        $settings = json_decode($DB->get_field('local_aiquestiongen_job', 'settings', ['id' => $jobid]), true);
        $this->assertSame(0, $settings['count']);
        $this->assertSame(3, $settings['tfcount']);
        $this->assertSame(2, $settings['essaycount']);
        $this->assertSame(['truefalse', 'essay'], $settings['qtypes']);
        $this->assertSame(0, $settings['clozecount']);
        $this->assertSame(0, $settings['gapcount']);
    }

    /**
     * A job that is waiting when the plugin is switched off fails without any AI request being made.
     */
    public function test_task_does_nothing_when_plugin_is_disabled(): void {
        global $DB;
        $this->resetAfterTest();
        $source = $this->make_source();
        $jobid = job_manager::create_job($source, $source->userid, ['cefr' => 'B1', 'count' => 3]);
        set_config('enabled', 0, 'local_aiquestiongen');

        $task = new \local_aiquestiongen\task\generate_questions();
        $task->set_custom_data(['jobid' => $jobid]);
        $task->execute();

        $job = $DB->get_record('local_aiquestiongen_job', ['id' => $jobid]);
        $this->assertSame('failed', $job->status);
        $this->assertSame(get_string('disabled', 'local_aiquestiongen'), $job->error);
        $this->assertEquals(0, $DB->count_records('local_aiquestiongen_item', ['jobid' => $jobid]));
        // No AI request was made, so no action was logged by core AI.
        $this->assertEquals(0, $DB->count_records('ai_action_register'));
    }
}
