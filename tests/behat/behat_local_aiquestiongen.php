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

// NOTE: no MOODLE_INTERNAL test, because this file is included directly by Behat.

// Find Moodle's behat_base.php. $CFG is set while Behat's configuration is built, but not when the Behat runner loads
// this file, and __DIR__ is the real path even for a symlinked plugin, so fall back to the working directory (the
// Moodle root, where vendor/bin/behat is run from). Both the Moodle 5.1+ (public/) and older layouts are tried.
global $CFG;
$behatlibdirs = [];
if (!empty($CFG->libdir)) {
    $behatlibdirs[] = $CFG->libdir;
}
$behatlibdirs[] = getcwd() . '/public/lib';
$behatlibdirs[] = getcwd() . '/lib';
$behatlibdirs[] = __DIR__ . '/../../../../lib';
foreach ($behatlibdirs as $behatlibdir) {
    if (is_readable($behatlibdir . '/behat/behat_base.php')) {
        require_once($behatlibdir . '/behat/behat_base.php');
        break;
    }
}
unset($behatlibdirs, $behatlibdir);

/**
 * Behat steps and named pages for local_aiquestiongen.
 *
 * @package    local_aiquestiongen
 * @category   test
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_aiquestiongen extends behat_base {
    /**
     * Named pages for "I am on the "X" "local_aiquestiongen > type" page".
     *
     * | upload   | Course full name | Where an article is pasted or uploaded       |
     * | generate | Article title    | The settings for a saved article             |
     * | review   | Article title    | The status and review page of its latest job |
     * | publish  | Article title    | Publish approved drafts to the question bank |
     *
     * @param string $type The page type.
     * @param string $identifier The course or article name.
     * @return moodle_url
     */
    protected function resolve_page_instance_url(string $type, string $identifier): moodle_url {
        global $DB;

        switch (strtolower($type)) {
            case 'upload':
                $course = $DB->get_record('course', ['fullname' => $identifier], '*', MUST_EXIST);
                return new moodle_url('/local/aiquestiongen/index.php', ['id' => $course->id]);
            case 'generate':
                $source = $DB->get_record('local_aiqg_source', ['title' => $identifier], '*', MUST_EXIST);
                return new moodle_url('/local/aiquestiongen/generate.php', ['sourceid' => $source->id]);
            case 'review':
                return new moodle_url('/local/aiquestiongen/job.php', ['id' => $this->latest_job($identifier)]);
            case 'publish':
                return new moodle_url('/local/aiquestiongen/publish.php', ['id' => $this->latest_job($identifier)]);
        }
        throw new Exception("Unrecognised local_aiquestiongen page type '$type'.");
    }

    /**
     * The newest job of an article.
     *
     * @param string $title The article title.
     * @return int The job id.
     */
    private function latest_job(string $title): int {
        global $DB;
        $source = $DB->get_record('local_aiqg_source', ['title' => $title], '*', MUST_EXIST);
        $jobs = $DB->get_records('local_aiqg_job', ['sourceid' => $source->id], 'id DESC', 'id', 0, 1);
        return (int) reset($jobs)->id;
    }

    /**
     * Click a button on one draft's card, found by the question text on it.
     *
     * @When /^I click "(?P<button_string>[^"]*)" on the draft "(?P<stem_string>[^"]*)"$/
     * @param string $button The visible button text.
     * @param string $stem Text that appears on the card.
     */
    public function i_click_on_the_draft(string $button, string $stem): void {
        $card = "//div[@data-region='item'][contains(., " . behat_context_helper::escape($stem) . ")]";
        $this->execute('behat_general::i_click_on_in_the', [$button, 'button', $card, 'xpath_element']);
    }

    /**
     * Click a button in one question type's group, found by the group heading.
     *
     * @When /^I click "(?P<button_string>[^"]*)" in the group "(?P<heading_string>[^"]*)"$/
     * @param string $button The visible button text.
     * @param string $heading The group heading, such as "Multiple choice".
     */
    public function i_click_in_the_group(string $button, string $heading): void {
        $group = "//section[@data-region='group'][.//h3[normalize-space(.) = " . behat_context_helper::escape($heading) . "]]";
        $this->execute('behat_general::i_click_on_in_the', [$button, 'button', $group, 'xpath_element']);
    }

    /**
     * Check that a draft's card shows a status.
     *
     * @Then /^the draft "(?P<stem_string>[^"]*)" should be "(?P<status_string>pending|approved|rejected|published)"$/
     * @param string $stem Text that appears on the card.
     * @param string $status The status.
     */
    public function the_draft_should_be(string $stem, string $status): void {
        $xpath = "//div[@data-region='item'][@data-status=" . behat_context_helper::escape($status) . "]"
            . "[contains(., " . behat_context_helper::escape($stem) . ")]";
        $this->execute('behat_general::should_exist', [$xpath, 'xpath_element']);
    }

    /**
     * Record that a user has already regenerated some drafts today, to test the daily limit without calling an AI.
     *
     * @Given /^"(?P<username_string>[^"]*)" has already used (?P<count_number>\d+) regenerations? today$/
     * @param string $username The username.
     * @param int $count How many regenerations have been used.
     */
    public function user_has_used_regenerations(string $username, int $count): void {
        global $DB;

        $userid = $DB->get_field('user', 'id', ['username' => $username], MUST_EXIST);
        set_user_preference('local_aiquestiongen_regen', usergetmidnight(time()) . ':' . $count, $userid);
    }

    /**
     * Click a button on a draft that is expected to end in Moodle's error dialog, check the message, and close it.
     *
     * Behat fails any step that leaves an error dialog on the page, so an error that is the point of the test has to
     * be read and closed inside the same step.
     *
     * @When /^I click "(?P<button>[^"]*)" on the draft "(?P<stem>[^"]*)" and see the error "(?P<error>[^"]*)"$/
     * @param string $button The button text.
     * @param string $stem Text that identifies the draft.
     * @param string $error Text the error must contain.
     */
    public function i_click_on_the_draft_and_see_the_error(string $button, string $stem, string $error): void {
        // Click directly: execute() looks for error dialogs after every step it runs, which would fail this test.
        $buttonxpath = "//div[@data-region='item'][contains(., " . behat_context_helper::escape($stem) . ")]"
            . "//button[normalize-space(.) = " . behat_context_helper::escape($button) . "]";
        $element = $this->spin(function () use ($buttonxpath) {
            $found = $this->getSession()->getPage()->find('xpath', $buttonxpath);
            if (!$found) {
                throw new \Exception('The button was not found');
            }
            return $found;
        }, [], behat_base::get_extended_timeout());
        $element->click();

        $dialogxpath = "//div[@data-rel='fatalerror']";
        $dialog = $this->spin(function () use ($dialogxpath) {
            $found = $this->getSession()->getPage()->find('xpath', $dialogxpath);
            if (!$found) {
                throw new \Exception('No error dialog appeared');
            }
            return $found;
        }, [], behat_base::get_extended_timeout());
        $text = $dialog->getText();

        // Close it, so that the check Behat makes after every step does not fail the test. Moodle shows the error in a
        // YUI dialogue (class moodle-dialogue) with a close button.
        $this->getSession()->executeScript(
            "document.querySelectorAll(\"[data-rel='fatalerror']\").forEach(function(e) {
                var dialogue = e.closest('.moodle-dialogue');
                var close = dialogue ? dialogue.querySelector('.closebutton') : null;
                if (close) { close.click(); } else if (dialogue) { dialogue.remove(); }
            });"
        );
        $this->getSession()->wait(1000);

        if (stripos($text, $error) === false) {
            throw new \Exception("The error dialog said \"$text\", which does not contain \"$error\".");
        }
    }
}
