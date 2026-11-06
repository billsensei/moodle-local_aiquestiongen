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

namespace local_aiquestiongen\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider tests: what is reported, exported and deleted.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Create an article with a job, an item and an uploaded file.
     *
     * @param \stdClass $user The owner.
     * @param \stdClass $course The course.
     * @param string $title The article title.
     * @return \stdClass The source record, with jobid, itemid and fileid.
     */
    private function make_article(\stdClass $user, \stdClass $course, string $title = 'An article'): \stdClass {
        global $DB;
        $source = (object) [
            'courseid' => $course->id, 'userid' => $user->id, 'title' => $title,
            'text' => 'Text of ' . $title, 'sourcefileitemid' => 0, 'timecreated' => 1700000000,
        ];
        $source->id = $DB->insert_record('local_aiquestiongen_source', $source);
        $source->jobid = $DB->insert_record('local_aiquestiongen_job', (object) [
            'sourceid' => $source->id, 'settings' => '{"cefr":"B1"}', 'status' => 'done', 'error' => null,
            'provider' => 'test-model', 'tokensused' => 42, 'timecreated' => 1700000100,
        ]);
        $source->itemid = $DB->insert_record('local_aiquestiongen_item', (object) [
            'jobid' => $source->jobid, 'qtype' => 'truefalse', 'status' => 'pending',
            'payload' => '{"stem":"Draft of ' . $title . '"}', 'timemodified' => 1700000200,
        ]);
        $file = get_file_storage()->create_file_from_string([
            'contextid' => \context_course::instance($course->id)->id, 'component' => 'local_aiquestiongen',
            'filearea' => 'source', 'itemid' => $source->id, 'filepath' => '/', 'filename' => 'article.txt',
        ], 'Original upload of ' . $title);
        $source->fileid = $file->get_id();
        return $source;
    }

    /**
     * Count the plugin's rows for a course.
     *
     * @param int $courseid The course id.
     * @return int[] Sources, jobs, items and files.
     */
    private function counts(int $courseid): array {
        global $DB;
        $sourceids = $DB->get_fieldset_select('local_aiquestiongen_source', 'id', 'courseid = ?', [$courseid]);
        $jobids = [];
        if ($sourceids) {
            $jobids = $DB->get_fieldset_select('local_aiquestiongen_job', 'id', 'sourceid IN (' . implode(',', $sourceids) . ')');
        }
        $items = $jobids ? $DB->count_records_select('local_aiquestiongen_item', 'jobid IN (' . implode(',', $jobids) . ')') : 0;
        $files = $DB->count_records_select(
            'files',
            "component = 'local_aiquestiongen' AND contextid = ? AND filename <> '.'",
            [\context_course::instance($courseid)->id]
        );
        return [count($sourceids), count($jobids), $items, $files];
    }

    /**
     * Every column the plugin stores is described in the privacy metadata, so adding a column without
     * declaring it fails this test.
     */
    public function test_metadata_covers_every_column(): void {
        global $DB;
        $this->resetAfterTest();
        $collection = provider::get_metadata(new collection('local_aiquestiongen'));
        $declared = [];
        foreach ($collection->get_collection() as $item) {
            $declared[$item->get_name()] = array_keys($item->get_privacy_fields());
        }

        foreach (['local_aiquestiongen_source', 'local_aiquestiongen_job', 'local_aiquestiongen_item'] as $table) {
            $columns = array_diff(array_keys($DB->get_columns($table)), ['id']);
            $this->assertEqualsCanonicalizing($columns, $declared[$table], "Privacy metadata for $table is out of date");
        }
        $this->assertArrayHasKey('aiprovider', $declared);
        $this->assertArrayHasKey('core_files', $declared);
    }

    /**
     * Every privacy string the metadata points at exists in English.
     */
    public function test_metadata_strings_exist(): void {
        $this->resetAfterTest();
        $collection = provider::get_metadata(new collection('local_aiquestiongen'));
        $sm = get_string_manager();
        foreach ($collection->get_collection() as $item) {
            $this->assertTrue($sm->string_exists($item->get_summary(), 'local_aiquestiongen'), $item->get_summary());
            foreach ($item->get_privacy_fields() as $key) {
                $this->assertTrue($sm->string_exists($key, 'local_aiquestiongen'), $key);
            }
        }
    }

    /**
     * A user has data in each course where they added an article, and only there.
     */
    public function test_contexts_for_user(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $other = $gen->create_user();
        $course1 = $gen->create_course();
        $course2 = $gen->create_course();
        $course3 = $gen->create_course();
        $this->make_article($user, $course1);
        $this->make_article($user, $course2);
        $this->make_article($other, $course3);

        $contextids = $this->get_contexts_for_userid($user->id, 'local_aiquestiongen')->get_contextids();
        $this->assertEqualsCanonicalizing(
            [\context_course::instance($course1->id)->id, \context_course::instance($course2->id)->id],
            array_map('intval', $contextids)
        );
        $this->assertSame([], $this->get_contexts_for_userid($gen->create_user()->id, 'local_aiquestiongen')->get_contextids());
    }

    /**
     * The users in a course context are the owners of articles there.
     */
    public function test_users_in_context(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $this->make_article($a, $course);
        $this->make_article($b, $course);
        $this->make_article($b, $course, 'Second');
        $gen->create_user();

        $context = \context_course::instance($course->id);
        $userlist = new userlist($context, 'local_aiquestiongen');
        provider::get_users_in_context($userlist);
        $this->assertEqualsCanonicalizing([$a->id, $b->id], array_map('intval', $userlist->get_userids()));

        // Other context levels are ignored.
        $systemlist = new userlist(\context_system::instance(), 'local_aiquestiongen');
        provider::get_users_in_context($systemlist);
        $this->assertSame([], $systemlist->get_userids());
    }

    /**
     * Export contains the article, its jobs and drafts, and the original file, and nothing of other users.
     */
    public function test_export(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $other = $gen->create_user();
        $course = $gen->create_course();
        $mine = $this->make_article($user, $course, 'Mine');
        $this->make_article($other, $course, 'Theirs');

        $context = \context_course::instance($course->id);
        $this->export_context_data_for_user($user->id, $context, 'local_aiquestiongen');
        $writer = writer::with_context($context);
        $this->assertTrue($writer->has_any_data());

        $subcontext = [get_string('pluginname', 'local_aiquestiongen'), $mine->id . '-Mine'];
        $data = $writer->get_data($subcontext);
        $this->assertSame('Mine', $data->title);
        $this->assertSame('Text of Mine', $data->text);
        $this->assertCount(1, $data->jobs);
        $this->assertSame('test-model', $data->jobs[0]->provider);
        $this->assertSame(42, (int) $data->jobs[0]->tokensused);
        $this->assertCount(1, $data->jobs[0]->items);
        $this->assertStringContainsString('Draft of Mine', $data->jobs[0]->items[0]->payload);

        $files = $writer->get_files($subcontext);
        $this->assertSame(['article.txt'], array_keys($files));

        // The other user's article is not in the export.
        $everything = json_encode($writer->get_data($subcontext)) . json_encode(array_keys($files));
        $this->assertStringNotContainsString('Theirs', $everything);
        $this->assertEmpty($writer->get_data([get_string('pluginname', 'local_aiquestiongen'), 'Theirs']));
    }

    /**
     * Deleting a whole context removes every user's articles there, with jobs, drafts and files, and nothing elsewhere.
     */
    public function test_delete_all_users_in_context(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $course1 = $gen->create_course();
        $course2 = $gen->create_course();
        $this->make_article($a, $course1);
        $this->make_article($b, $course1);
        $this->make_article($a, $course2);

        provider::delete_data_for_all_users_in_context(\context_course::instance($course1->id));

        $this->assertSame([0, 0, 0, 0], $this->counts($course1->id));
        $this->assertSame([1, 1, 1, 1], $this->counts($course2->id));

        // Wrong context level: nothing happens.
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame([1, 1, 1, 1], $this->counts($course2->id));
    }

    /**
     * Deleting a user removes their articles in the approved contexts only, and no one else's.
     */
    public function test_delete_data_for_user(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $other = $gen->create_user();
        $course1 = $gen->create_course();
        $course2 = $gen->create_course();
        $this->make_article($user, $course1);
        $this->make_article($other, $course1);
        $this->make_article($user, $course2);

        $approved = new approved_contextlist($user, 'local_aiquestiongen', [\context_course::instance($course1->id)->id]);
        provider::delete_data_for_user($approved);

        $this->assertSame([1, 1, 1, 1], $this->counts($course1->id), "Only the other user's article should remain");
        $this->assertSame([1, 1, 1, 1], $this->counts($course2->id), 'A context that was not approved is untouched');
        global $DB;
        $owners = $DB->get_fieldset_select('local_aiquestiongen_source', 'userid', 'courseid = ?', [$course1->id]);
        $this->assertEquals([$other->id], $owners);
    }

    /**
     * Deleting a list of users in one context removes only those users' articles.
     */
    public function test_delete_data_for_users(): void {
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $a = $gen->create_user();
        $b = $gen->create_user();
        $c = $gen->create_user();
        $course = $gen->create_course();
        foreach ([$a, $b, $c] as $user) {
            $this->make_article($user, $course);
        }

        $context = \context_course::instance($course->id);
        provider::delete_data_for_users(new approved_userlist($context, 'local_aiquestiongen', [$a->id, $b->id]));
        $this->assertSame([1, 1, 1, 1], $this->counts($course->id));

        // An empty list does nothing.
        provider::delete_data_for_users(new approved_userlist($context, 'local_aiquestiongen', []));
        $this->assertSame([1, 1, 1, 1], $this->counts($course->id));
    }

    /**
     * Published questions stay in the question bank when a user's drafts are deleted; only the plugin's records go.
     */
    public function test_published_questions_are_not_deleted(): void {
        global $DB;
        $this->resetAfterTest();
        $gen = $this->getDataGenerator();
        $user = $gen->create_user();
        $course = $gen->create_course();
        $qbank = $gen->create_module('qbank', ['course' => $course->id]);
        $category = $gen->get_plugin_generator('core_question')
            ->create_question_category(['contextid' => \context_module::instance($qbank->cmid)->id]);
        $question = $gen->get_plugin_generator('core_question')->create_question('truefalse', null, ['category' => $category->id]);

        $article = $this->make_article($user, $course);
        $DB->set_field('local_aiquestiongen_item', 'questionid', $question->id, ['id' => $article->itemid]);
        $DB->set_field('local_aiquestiongen_item', 'status', 'published', ['id' => $article->itemid]);

        $approved = new approved_contextlist($user, 'local_aiquestiongen', [\context_course::instance($course->id)->id]);
        provider::delete_data_for_user($approved);

        $this->assertSame([0, 0, 0, 0], $this->counts($course->id));
        $this->assertTrue($DB->record_exists('question', ['id' => $question->id]));
    }
}
