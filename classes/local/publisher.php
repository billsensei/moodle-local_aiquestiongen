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

use local_aiquestiongen\event\questions_published;

/**
 * Publishes approved drafts (multiple choice, true/false and essay) to the question bank.
 *
 * Questions are created with the question type's own save_question(), so they get versions,
 * bank entries and options exactly as if they had been made in the question editor.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class publisher {
    /** @var string Tag put on reading comprehension questions and their passage. */
    public const COMPREHENSION_TAG = 'reading-comprehension';

    /**
     * Publish every approved draft of a job.
     *
     * @param \stdClass $job The job record.
     * @param \stdClass $source The source record.
     * @param int $userid The acting user.
     * @param string $target "cat:<categoryid>" for an existing category or "new:<bank contextid>" to create one.
     * @param bool $includepassage Also publish the article as a description item for reading comprehension.
     * @param string $quiz "none", "new" for a new quiz named after the article, or "quiz:<id>" for an existing one.
     * @return array{published: int, failed: int, categoryid: int, bankcmid: int, passage: bool,
     *     quizcmid: int, quizname: string, quizadded: int, quizerror: string}
     */
    public static function publish(
        \stdClass $job,
        \stdClass $source,
        int $userid,
        string $target,
        bool $includepassage = false,
        string $quiz = 'none'
    ): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/questionlib.php');

        $coursecontext = \context_course::instance($source->courseid);
        require_capability('local/aiquestiongen:publish', $coursecontext, $userid);
        if ((int) $source->userid !== $userid) {
            throw new \moodle_exception('err_notowner', 'local_aiquestiongen');
        }

        $approved = $DB->get_records('local_aiqg_item', ['jobid' => $job->id, 'status' => 'approved'], 'id ASC');
        if (!$approved) {
            throw new \moodle_exception('err_nothingapproved', 'local_aiquestiongen');
        }

        // Check the quiz choice now, so a bad one stops everything before any question is made.
        $existingquiz = self::resolve_quiz($quiz, $source, $userid);

        $category = self::resolve_category($target, $source, $userid);
        $bankcontext = \context::instance_by_id($category->contextid);
        require_capability('moodle/question:add', $bankcontext, $userid);

        $settings = json_decode((string) $job->settings, true) ?: [];
        $tags = self::tags_for($source, $settings);

        $passage = false;
        if ($includepassage) {
            $passage = self::ensure_passage($job, $settings, $source, $category, $bankcontext, $tags);
        }

        $questionids = [];
        if ($passage || ($includepassage && !empty($settings['passagequestionid']))) {
            $saved = json_decode((string) $DB->get_field('local_aiqg_job', 'settings', ['id' => $job->id]), true) ?: [];
            if (!empty($saved['passagequestionid'])) {
                $questionids[] = (int) $saved['passagequestionid'];
            }
        }

        $published = 0;
        $failed = 0;
        foreach ($approved as $item) {
            $transaction = null;
            try {
                $transaction = $DB->start_delegated_transaction();
                $question = self::create_question($item, $category);
                $itemtags = $tags;
                if (self::is_comprehension($item)) {
                    $itemtags[] = self::COMPREHENSION_TAG;
                }
                \core_tag_tag::set_item_tags('core_question', 'question', $question->id, $bankcontext, $itemtags);
                $DB->update_record('local_aiqg_item', (object) [
                    'id' => $item->id,
                    'status' => 'published',
                    'questionid' => $question->id,
                    'timemodified' => time(),
                ]);
                $transaction->allow_commit();
                $questionids[] = (int) $question->id;
                $published++;
            } catch (\Throwable $e) {
                // The transaction rolls back; the draft stays approved so the teacher can try again.
                if ($transaction && !$transaction->is_disposed()) {
                    try {
                        $transaction->rollback($e);
                    } catch (\Throwable $rethrown) {
                        // Rolling back rethrows the original error, which is handled here.
                        unset($rethrown);
                    }
                }
                debugging($e->getMessage(), DEBUG_DEVELOPER);
                $failed++;
            }
        }

        if ($published > 0) {
            questions_published::create([
                'objectid' => $job->id,
                'context' => $coursecontext,
                'other' => ['count' => $published, 'categoryid' => (int) $category->id],
            ])->trigger();
        }

        $quizresult = ['quizcmid' => 0, 'quizname' => '', 'quizadded' => 0, 'quizerror' => ''];
        if ($quiz !== 'none' && $questionids) {
            try {
                $quizrecord = $existingquiz ?? quiz_builder::create_quiz(get_course($source->courseid), $source->title, $userid);
                $quizresult = [
                    'quizcmid' => (int) $quizrecord->cmid,
                    'quizname' => format_string($quizrecord->name),
                    'quizadded' => quiz_builder::add_questions($quizrecord, $questionids),
                    'quizerror' => '',
                ];
            } catch (\moodle_exception $e) {
                // The questions are already in the bank; report the quiz problem without undoing them.
                $quizresult['quizerror'] = $e->getMessage();
            }
        }

        $bankcmid = (int) $bankcontext->instanceid;
        return [
            'published' => $published,
            'failed' => $failed,
            'categoryid' => (int) $category->id,
            'bankcmid' => $bankcmid,
            'passage' => $passage,
        ] + $quizresult;
    }

    /**
     * Check the quiz choice and load the quiz if an existing one was chosen.
     *
     * @param string $quiz "none", "new" or "quiz:<id>".
     * @param \stdClass $source The source record.
     * @param int $userid The acting user.
     * @return \stdClass|null The existing quiz, or null for "none" and "new".
     */
    private static function resolve_quiz(string $quiz, \stdClass $source, int $userid): ?\stdClass {
        if ($quiz === 'none') {
            return null;
        }
        if ($quiz === 'new') {
            if (!quiz_builder::can_create($source->courseid, $userid)) {
                throw new \moodle_exception('err_quizcreate', 'local_aiquestiongen');
            }
            return null;
        }
        if (preg_match('/^quiz:(\d+)$/', $quiz, $matches)) {
            return quiz_builder::load_quiz((int) $matches[1], $source->courseid, $userid);
        }
        throw new \moodle_exception('err_badquiz', 'local_aiquestiongen');
    }

    /**
     * Work out the target category, creating it if asked to.
     *
     * The category must be in a mod_qbank bank of the article's own course.
     *
     * @param string $target "cat:<id>" or "new:<contextid>".
     * @param \stdClass $source The source record.
     * @param int $userid The acting user.
     * @return \stdClass The question_categories record.
     */
    private static function resolve_category(string $target, \stdClass $source, int $userid): \stdClass {
        global $DB;

        $allowed = bank_finder::get_banks($source->courseid, $userid);
        $contextids = array_column($allowed, 'contextid');

        if (preg_match('/^cat:(\d+)$/', $target, $matches)) {
            $category = $DB->get_record('question_categories', ['id' => (int) $matches[1]]);
            if (!$category || $category->parent == 0 || !in_array((int) $category->contextid, $contextids, true)) {
                throw new \moodle_exception('err_badtarget', 'local_aiquestiongen');
            }
            return $category;
        }

        if (preg_match('/^new:(\d+)$/', $target, $matches)) {
            $contextid = (int) $matches[1];
            if (!in_array($contextid, $contextids, true)) {
                throw new \moodle_exception('err_badtarget', 'local_aiquestiongen');
            }
            $context = \context::instance_by_id($contextid);
            $top = question_get_top_category($context->id, true);
            $manager = new \core_question\category_manager();
            $name = \core_text::substr(trim($source->title), 0, 255);
            $id = $manager->add_category($top->id . ',' . $context->id, $name, '');
            return $DB->get_record('question_categories', ['id' => $id], '*', MUST_EXIST);
        }

        throw new \moodle_exception('err_badtarget', 'local_aiquestiongen');
    }

    /**
     * Create one question from an approved draft, using the question type's own save_question().
     *
     * Teacher text is plain text, so it is escaped before being stored as HTML.
     *
     * @param \stdClass $item The item record.
     * @param \stdClass $category The target category.
     * @return \stdClass The saved question.
     */
    private static function create_question(\stdClass $item, \stdClass $category): \stdClass {
        global $USER;

        $draft = json_decode((string) $item->payload, true) ?: [];

        $form = new \stdClass();
        $form->category = $category->id . ',' . $category->contextid;
        $form->name = shorten_text(trim((string) $draft['stem']), 60, true);
        $form->questiontext = ['text' => self::html((string) $draft['stem']), 'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 1;
        $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $form->idnumber = null;

        switch ($item->qtype) {
            case 'multichoice':
                self::fill_multichoice($form, $draft);
                break;
            case 'truefalse':
                self::fill_truefalse($form, $draft);
                break;
            case 'essay':
                self::fill_essay($form, $draft);
                break;
            case 'multianswer':
                self::fill_cloze($form, $draft);
                break;
            case 'gapselect':
                self::fill_gapselect($form, $draft);
                break;
            default:
                throw new \coding_exception("Cannot publish question type '{$item->qtype}'");
        }

        $question = new \stdClass();
        $question->qtype = $item->qtype;
        $question->createdby = $USER->id;
        $question->idnumber = null;
        $question->status = $form->status;

        return \question_bank::get_qtype($item->qtype)->save_question($question, $form);
    }

    /**
     * Add the multiple-choice fields to the form data.
     *
     * @param \stdClass $form The form data.
     * @param array $draft The draft.
     */
    private static function fill_multichoice(\stdClass $form, array $draft): void {
        $answers = array_values($draft['answers'] ?? []);

        $form->penalty = 0.3333333;
        $form->noanswers = count($answers);
        $form->numhints = 0;
        $form->single = 1;
        $form->shuffleanswers = 1;
        $form->answernumbering = 'abc';
        $form->showstandardinstruction = 0;
        $form->shownumcorrect = 1;
        $form->correctfeedback = ['text' => get_string('correctfeedbackdefault', 'question'), 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => get_string('partiallycorrectfeedbackdefault', 'question'), 'format' => FORMAT_HTML,
        ];
        $form->incorrectfeedback = ['text' => get_string('incorrectfeedbackdefault', 'question'), 'format' => FORMAT_HTML];

        $form->answer = [];
        $form->fraction = [];
        $form->feedback = [];
        foreach ($answers as $answer) {
            $form->answer[] = ['text' => self::html((string) $answer['text']), 'format' => FORMAT_HTML];
            $form->fraction[] = !empty($answer['correct']) ? '1.0' : '0.0';
            $form->feedback[] = ['text' => self::html((string) ($answer['feedback'] ?? '')), 'format' => FORMAT_HTML];
        }
    }

    /**
     * Add the true/false fields to the form data.
     *
     * @param \stdClass $form The form data.
     * @param array $draft The draft.
     */
    private static function fill_truefalse(\stdClass $form, array $draft): void {
        $form->penalty = 1;
        $form->correctanswer = ($draft['correct'] ?? false) === true ? '1' : '0';
        $form->feedbacktrue = ['text' => self::html((string) ($draft['feedbacktrue'] ?? '')), 'format' => FORMAT_HTML];
        $form->feedbackfalse = ['text' => self::html((string) ($draft['feedbackfalse'] ?? '')), 'format' => FORMAT_HTML];
    }

    /**
     * Add the essay fields to the form data. The model answer and marking guide go in the grader information.
     *
     * @param \stdClass $form The form data.
     * @param array $draft The draft.
     */
    private static function fill_essay(\stdClass $form, array $draft): void {
        $form->penalty = 0;
        $form->responseformat = 'editor';
        $form->responserequired = 1;
        $form->responsefieldlines = 15;
        $form->attachments = 0;
        $form->attachmentsrequired = 0;
        $form->maxbytes = 0;
        $form->filetypeslist = '';
        $form->responsetemplate = ['text' => '', 'format' => FORMAT_HTML];

        $info = '<p><strong>' . s(get_string('modelanswer', 'local_aiquestiongen')) . '</strong></p>'
            . self::html((string) ($draft['modelanswer'] ?? ''))
            . '<p><strong>' . s(get_string('markingguide', 'local_aiquestiongen')) . '</strong></p>'
            . self::html((string) ($draft['rubric'] ?? ''));
        $form->graderinfo = ['text' => $info, 'format' => FORMAT_HTML];
    }

    /**
     * Add the Cloze (typed fill-in-the-blank) fields to the form data.
     *
     * @param \stdClass $form The form data.
     * @param array $draft The draft.
     */
    private static function fill_cloze(\stdClass $form, array $draft): void {
        $cloze = fill_builder::cloze_text($draft);
        if ($cloze === null) {
            throw new \moodle_exception('err_badtarget', 'local_aiquestiongen');
        }
        $form->name = shorten_text(trim(preg_replace(fill_builder::BLANK, '____', (string) $draft['stem'])), 60, true);
        $form->questiontext = ['text' => $cloze, 'format' => FORMAT_HTML];
        $form->penalty = 0.3333333;
        $form->numhints = 0;
    }

    /**
     * Add the gapselect (choose-from-a-list fill-in-the-blank) fields to the form data.
     *
     * @param \stdClass $form The form data.
     * @param array $draft The draft.
     */
    private static function fill_gapselect(\stdClass $form, array $draft): void {
        $text = fill_builder::gapselect_text($draft);
        if ($text === null) {
            throw new \moodle_exception('err_badtarget', 'local_aiquestiongen');
        }
        $form->name = shorten_text(trim(preg_replace(fill_builder::BLANK, '____', (string) $draft['stem'])), 60, true);
        $form->questiontext = ['text' => $text, 'format' => FORMAT_HTML];
        $form->choices = fill_builder::gapselect_choices($draft);
        $form->penalty = 0.3333333;
        $form->numhints = 0;
        $form->shuffleanswers = 1;
        $form->shownumcorrect = 1;
        $form->correctfeedback = ['text' => get_string('correctfeedbackdefault', 'question'), 'format' => FORMAT_HTML];
        $form->partiallycorrectfeedback = [
            'text' => get_string('partiallycorrectfeedbackdefault', 'question'), 'format' => FORMAT_HTML,
        ];
        $form->incorrectfeedback = ['text' => get_string('incorrectfeedbackdefault', 'question'), 'format' => FORMAT_HTML];
    }

    /**
     * Is this draft a reading comprehension question?
     *
     * True/false statements always are; other types are when the model said their focus is comprehension.
     *
     * @param \stdClass $item The item record.
     * @return bool
     */
    private static function is_comprehension(\stdClass $item): bool {
        if ($item->qtype === 'truefalse') {
            return true;
        }
        $draft = json_decode((string) $item->payload, true) ?: [];
        return ($draft['focus'] ?? '') === 'comprehension';
    }

    /**
     * Make sure the article is in the bank as a description item, once per job.
     *
     * A description question shows text with nothing to answer, so a quiz can put it above the reading
     * comprehension questions. The id is remembered in the job settings so publishing again does not
     * add a second copy.
     *
     * @param \stdClass $job The job record.
     * @param array $settings The job settings.
     * @param \stdClass $source The source record.
     * @param \stdClass $category The target category.
     * @param \context $bankcontext The bank context.
     * @param string[] $tags Tags for the job's questions.
     * @return bool True if a passage was created now.
     */
    private static function ensure_passage(
        \stdClass $job,
        array $settings,
        \stdClass $source,
        \stdClass $category,
        \context $bankcontext,
        array $tags
    ): bool {
        global $DB, $USER;

        $existing = (int) ($settings['passagequestionid'] ?? 0);
        if ($existing && $DB->record_exists('question', ['id' => $existing])) {
            return false;
        }

        $paragraphs = preg_split('/\R{2,}/u', trim($source->text));
        $html = implode('', array_map(fn($p) => self::html($p), $paragraphs));

        $form = new \stdClass();
        $form->category = $category->id . ',' . $category->contextid;
        $form->name = shorten_text(trim($source->title), 40, true) . ' - ' . get_string('readingpassage', 'local_aiquestiongen');
        $form->questiontext = ['text' => $html, 'format' => FORMAT_HTML];
        $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
        $form->defaultmark = 0;
        $form->penalty = 0;
        $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
        $form->idnumber = null;

        $question = new \stdClass();
        $question->qtype = 'description';
        $question->createdby = $USER->id;
        $question->idnumber = null;
        $question->status = $form->status;

        $transaction = $DB->start_delegated_transaction();
        $saved = \question_bank::get_qtype('description')->save_question($question, $form);
        \core_tag_tag::set_item_tags(
            'core_question',
            'question',
            $saved->id,
            $bankcontext,
            array_merge($tags, [self::COMPREHENSION_TAG])
        );
        $settings['passagequestionid'] = (int) $saved->id;
        $DB->set_field('local_aiqg_job', 'settings', json_encode($settings, JSON_UNESCAPED_UNICODE), ['id' => $job->id]);
        $transaction->allow_commit();
        return true;
    }

    /**
     * Escape plain text for storage as HTML. Empty text stays empty.
     *
     * @param string $text Plain text.
     * @return string
     */
    private static function html(string $text): string {
        $text = trim($text);
        return $text === '' ? '' : '<p>' . nl2br(s($text), false) . '</p>';
    }

    /**
     * Tags for every published question: ai-generated, the CEFR level and the article title.
     *
     * @param \stdClass $source The source record.
     * @param array $settings The job settings.
     * @return string[]
     */
    private static function tags_for(\stdClass $source, array $settings): array {
        $tags = ['ai-generated'];
        if (!empty($settings['cefr'])) {
            $tags[] = 'cefr-' . \core_text::strtolower($settings['cefr']);
        }
        $title = \core_text::substr(trim(strip_tags($source->title)), 0, 100);
        if ($title !== '') {
            $tags[] = $title;
        }
        return $tags;
    }
}
