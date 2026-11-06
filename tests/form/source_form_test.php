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

/**
 * Tests for the upload form, submitted the way a browser submits it.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(source_form::class)]
final class source_form_test extends \advanced_testcase {
    /**
     * Pasted text with no file is accepted.
     */
    public function test_pasted_text_is_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        source_form::mock_submit([
            'id' => $course->id,
            'title' => 'Tom moves house',
            'articletext' => 'Tom was reluctant to leave.',
            'sourcefile' => file_get_unused_draft_itemid(),
        ]);
        $form = new source_form(new \moodle_url('/local/aiquestiongen/index.php'), ['courseid' => $course->id]);
        $data = $form->get_data();

        $this->assertNotNull($data);
        $this->assertSame('Tom moves house', $data->title);
        $this->assertSame('Tom was reluctant to leave.', $data->articletext);
    }

    /**
     * Nothing to read is refused with a message on the text box.
     */
    public function test_empty_submission_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();

        source_form::mock_submit([
            'id' => $course->id,
            'title' => 'Empty article',
            'articletext' => '',
            'sourcefile' => file_get_unused_draft_itemid(),
        ]);
        $form = new source_form(new \moodle_url('/local/aiquestiongen/index.php'), ['courseid' => $course->id]);

        $this->assertNull($form->get_data());
        $this->assertTrue($form->is_submitted());
        $this->assertFalse($form->is_validated());
    }

    /**
     * Put a small file in a new draft area for the current user.
     *
     * @return int The draft item id.
     */
    private function make_draft_file(): int {
        global $USER;
        $draftid = file_get_unused_draft_itemid();
        get_file_storage()->create_file_from_string([
            'contextid' => \context_user::instance($USER->id)->id, 'component' => 'user', 'filearea' => 'draft',
            'itemid' => $draftid, 'filepath' => '/', 'filename' => 'article.txt',
        ], 'Tom was reluctant to leave.');
        return $draftid;
    }

    /**
     * Build the form after submitting values.
     *
     * @param array $values Submitted values.
     * @return source_form
     */
    private function submit(array $values): source_form {
        $course = $this->getDataGenerator()->create_course();
        source_form::mock_submit(['id' => $course->id, 'title' => 'Article'] + $values);
        return new source_form(new \moodle_url('/local/aiquestiongen/index.php'), ['courseid' => $course->id]);
    }

    /**
     * A file with no pasted text is accepted.
     */
    public function test_file_only_is_accepted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $form = $this->submit(['articletext' => '', 'sourcefile' => $this->make_draft_file()]);
        $this->assertNotNull($form->get_data());
    }

    /**
     * Both a file and pasted text is refused, so it is never unclear which one is used.
     */
    public function test_file_and_text_together_are_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $form = $this->submit(['articletext' => 'Some text.', 'sourcefile' => $this->make_draft_file()]);
        $this->assertNull($form->get_data());
        $this->assertFalse($form->is_validated());
    }

    /**
     * A draft area with no file in it counts as no file.
     */
    public function test_empty_draft_area_counts_as_no_file(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $form = $this->submit(['articletext' => 'Some text.', 'sourcefile' => file_get_unused_draft_itemid()]);
        $this->assertNotNull($form->get_data());
    }
}
