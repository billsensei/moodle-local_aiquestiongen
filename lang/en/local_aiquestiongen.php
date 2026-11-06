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

/**
 * Strings for component local_aiquestiongen, language 'en'.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['action_approve'] = 'Approve';
$string['action_approveall'] = 'Approve all pending';
$string['action_cancel'] = 'Cancel';
$string['action_edit'] = 'Edit';
$string['action_regenerate'] = 'Regenerate';
$string['action_regeneratego'] = 'Regenerate this question';
$string['action_reject'] = 'Reject';
$string['action_rejectall'] = 'Reject all pending';
$string['action_save'] = 'Save changes';
$string['action_undo'] = 'Undo';
$string['aiheading'] = 'AI connection';
$string['aiheading_desc'] = 'How the plugin reaches an AI model. Core AI uses the provider set up under Site administration > AI. Direct API calls a provider itself, which allows a specific model, but the key is then stored by this plugin.';
$string['aimode'] = 'Connection mode';
$string['aimode_core'] = 'Moodle AI subsystem (recommended)';
$string['aimode_desc'] = 'Core AI is recommended: it adds central logging, policy acceptance and rate limits.';
$string['aimode_direct'] = 'Direct API (Claude or OpenAI-compatible)';
$string['aiquestiongen:publish'] = 'Publish AI-generated questions to the question bank';
$string['aiquestiongen:use'] = 'Use the AI question generator';
$string['alsoaccepted'] = 'Also accepted:';
$string['answerlabel'] = 'Answer option {$a}';
$string['articletext'] = 'Article text';
$string['articletext_help'] = 'Paste the article here, or upload a file below. Provide one or the other, not both.';
$string['articletitle'] = 'Article title';
$string['blankanswer'] = 'Correct word:';
$string['cefrlevel'] = 'CEFR level';
$string['clozecount'] = 'Number of fill-in-the-blank questions (student types the word)';
$string['clozecount_help'] = 'Each is one sentence with one blank. The student types the missing word. Other correct words can be accepted too.';
$string['confirmrejectall'] = 'Reject all {$a} pending questions in this group? You can undo each one afterwards.';
$string['copyrightnotice'] = 'Only upload or paste texts that you have the right to use. The text is sent to an external AI provider to draft questions, and nothing is added to the question bank until you approve it.';
$string['correctanswer'] = 'Correct';
$string['correctlegend'] = 'Answer options. Select the correct one.';
$string['countsintro'] = 'Choose how many questions of each type to draft. The total must not be more than {$a}.';
$string['dailyjoblimit'] = 'Daily jobs per user';
$string['dailyjoblimit_desc'] = 'Maximum number of generation jobs each user may start per day. Use 0 for no limit.';
$string['dailyregenlimit'] = 'Daily regenerations per user';
$string['dailyregenlimit_desc'] = 'Maximum number of times each user may regenerate a draft question per day, across all drafts. Every request counts, even if the AI cannot write a usable replacement. Each draft can also be regenerated at most 5 times. Use 0 for no daily limit.';
$string['directapikey'] = 'API key';
$string['directapikey_desc'] = 'Stored encrypted in the Moodle database. The encryption key is a file in the secret folder of the site data directory, so back that folder up with the database. The saved key is never shown again; to change it, enter a new one.';
$string['directendpoint'] = 'Endpoint base URL';
$string['directendpoint_desc'] = 'For Claude, leave empty to use https://api.anthropic.com. For OpenAI-compatible services, enter the base URL up to and including /v1, for example https://api.openai.com/v1. The address must start with https://; http:// is accepted only for localhost, for a model running on the same server. Requests go through the HTTP security settings of Moodle, which block local and private addresses by default.';
$string['directmaxtokens'] = 'Maximum reply tokens';
$string['directmaxtokens_desc'] = 'The longest reply to ask for. Raise it if long jobs come back cut off.';
$string['directmodel'] = 'Model';
$string['directmodel_desc'] = 'The model name sent with each request.';
$string['directprovider'] = 'Direct API type';
$string['directprovider_desc'] = 'Which request format to use.';
$string['directtimeout'] = 'Request timeout (seconds)';
$string['directtimeout_desc'] = 'How long to wait for one reply.';
$string['disabled'] = 'The AI question generator is disabled on this site.';
$string['draftsheading'] = '{$a} draft questions';
$string['enabled'] = 'Enable AI question generator';
$string['enabled_desc'] = 'When disabled, the course menu link is hidden, no new articles can be added, and no more AI requests are made (including jobs already waiting and regenerating a question). Teachers can still review and publish drafts they already have.';
$string['err_aiempty'] = 'The AI provider returned an empty response.';
$string['err_aifailed'] = 'The AI provider could not generate text: {$a}';
$string['err_badquiz'] = 'That quiz cannot be used. Choose one from the list.';
$string['err_badstatus'] = 'That status cannot be set.';
$string['err_badtarget'] = 'That question bank or category cannot be used. Choose one from the list.';
$string['err_cannotapprove'] = 'This question cannot be approved yet: {$a}. Edit it first.';
$string['err_cefr'] = 'Choose a valid CEFR level.';
$string['err_count'] = 'The total number of questions must be between 1 and {$a}.';
$string['err_dailylimit'] = 'You have reached the daily limit of {$a} generation jobs. Try again tomorrow.';
$string['err_directauth'] = 'The AI service refused the request (HTTP {$a}). Check the API key and that it may use this model.';
$string['err_directbadresponse'] = 'The AI service sent a reply that could not be read.';
$string['err_directconnect'] = 'The AI service could not be reached. Check the endpoint, the network and the HTTP security settings of the site.';
$string['err_directhttp'] = 'The AI service returned an error (HTTP {$a->status}). {$a->detail}';
$string['err_directkey'] = 'The saved API key could not be read. This can happen after the site was moved or restored. An administrator needs to enter the API key again.';
$string['err_directnotconfigured'] = 'Direct API mode is selected but not fully set up. An administrator needs to enter the API key and model (and the endpoint for OpenAI-compatible services).';
$string['err_directrate'] = 'The AI service says there have been too many requests or the quota is used up. Try again later.';
$string['err_docxinvalid'] = 'The .docx file could not be read. It may be corrupt or not a Word document.';
$string['err_emptytext'] = 'No text could be found in the article.';
$string['err_fieldlength'] = 'A question field is too long. Each field can have up to {$a} characters.';
$string['err_nobanks'] = 'This course has no question bank where you can add questions. Create a question bank in the course first, then come back.';
$string['err_noitems'] = 'The AI did not return any usable questions.';
$string['err_notextorfile'] = 'Paste some text or upload a file.';
$string['err_nothingapproved'] = 'There are no approved questions to publish. Approve some drafts first.';
$string['err_notowner'] = 'You can only generate questions from articles you added yourself.';
$string['err_pdffailed'] = 'The PDF could not be converted to text.';
$string['err_pdfnotconfigured'] = 'PDF extraction is not available because the pdftotext path is not configured by the administrator.';
$string['err_published'] = 'This question has already been published and can no longer be changed here.';
$string['err_quizcreate'] = 'You cannot add a quiz to this course.';
$string['err_regendaily'] = 'You have reached the daily limit of {$a} regenerations. Edit the question by hand, or try again tomorrow.';
$string['err_regenfailed'] = 'The AI could not write a valid replacement, so the original question was kept. Try again, or edit it by hand.';
$string['err_regenlimit'] = 'This question has already been regenerated {$a} times. Edit it by hand instead.';
$string['err_textlong'] = 'The article is too long. The maximum is {$a} characters.';
$string['err_textorfile'] = 'Provide either pasted text or a file, not both.';
$string['err_unexpected'] = 'An unexpected error stopped the generation. Ask your administrator to check the site logs.';
$string['err_unparseable'] = 'The AI reply could not be read as questions, even after a second try.';
$string['err_unreadable'] = 'The uploaded file could not be read.';
$string['err_unsupportedtype'] = 'Files of type "{$a}" are not supported. Use .txt, .docx, .pdf or .html.';
$string['essaycount'] = 'Number of open-ended (essay) questions';
$string['essaycount_help'] = 'How many open-ended writing questions to draft. Each comes with a model answer and a marking guide for you.';
$string['event_itemapproved'] = 'Draft question approved';
$string['event_questionspublished'] = 'AI-generated questions published';
$string['feedbackiffalse'] = 'Feedback if the student answers False';
$string['feedbackiftrue'] = 'Feedback if the student answers True';
$string['feedbacklabel'] = 'Feedback for answer {$a}';
$string['feedbacklanguage'] = 'Language of answer feedback';
$string['gapcount'] = 'Number of fill-in-the-blank questions (student chooses from a list)';
$string['gapcount_help'] = 'Each is one sentence with one blank. The student picks the missing word from a drop-down list of four. This suits lower levels.';
$string['generatedrafts'] = 'Generate draft questions';
$string['generateheading'] = 'Generate questions';
$string['generatequestions'] = 'AI question generator';
$string['generatingfrom'] = 'Article: {$a}';
$string['group_essay'] = 'Open-ended (essay)';
$string['group_gapselect'] = 'Fill in the blank (choose from a list)';
$string['group_multianswer'] = 'Fill in the blank (typed)';
$string['group_multichoice'] = 'Multiple choice';
$string['group_truefalse'] = 'True/false';
$string['includepassage'] = 'Include the reading passage';
$string['includepassage_help'] = 'Adds the article to the question bank as a description item, tagged reading-comprehension, so that a quiz can show it above the comprehension questions. It is added once per job.';
$string['issue_answercount'] = 'the question does not have exactly 4 answer options';
$string['issue_badalternatives'] = 'the list of other correct words is not valid';
$string['issue_badanswerchars'] = 'an answer contains ~ or *, or a line break';
$string['issue_badfocus'] = 'the focus is not vocabulary, grammar or comprehension';
$string['issue_badstemchars'] = 'the sentence contains { } or [[ ]], which clash with the question format';
$string['issue_badtype'] = 'the question type is not multichoice';
$string['issue_blankanswer'] = 'an answer option is empty';
$string['issue_clozesyntax'] = 'the typed answer could not be turned into a valid fill-in-the-blank question';
$string['issue_correctcount'] = 'there is not exactly one correct answer';
$string['issue_distractorcount'] = 'there are not exactly 3 wrong choices';
$string['issue_duplicate'] = 'two answer options are the same';
$string['issue_noanswer'] = 'the correct word is missing';
$string['issue_noblank'] = 'the sentence does not have exactly one blank (written as ____)';
$string['issue_nocorrect'] = 'the correct answer (true or false) is missing';
$string['issue_nomodelanswer'] = 'the model answer is missing';
$string['issue_norubric'] = 'the marking guide is missing';
$string['issue_nostem'] = 'the question text is missing';
$string['issue_targetmissing'] = 'the target word does not appear in the article';
$string['jobheading'] = 'Question generation';
$string['jobstatus'] = 'Status: {$a}';
$string['jobwaiting'] = 'Generating in the background. This page refreshes by itself.';
$string['lang_english'] = 'English';
$string['lang_japanese'] = 'Japanese';
$string['live_approved'] = 'Question {$a} approved.';
$string['live_bulk'] = '{$a->changed} approved or rejected. {$a->skipped} skipped because they need checking.';
$string['live_pending'] = 'Question {$a} returned to pending.';
$string['live_regenerated'] = 'Question {$a} was replaced and is pending.';
$string['live_regenerating'] = 'Regenerating question {$a}.';
$string['live_rejected'] = 'Question {$a} rejected.';
$string['live_saved'] = 'Question {$a} saved.';
$string['markcorrect'] = 'Option {$a} is correct';
$string['markingguide'] = 'Marking guide';
$string['maxarticlelength'] = 'Maximum article length';
$string['maxarticlelength_desc'] = 'Maximum number of characters of article text accepted per upload.';
$string['maxquestionsperjob'] = 'Maximum questions per job';
$string['maxquestionsperjob_desc'] = 'Maximum number of questions a single generation job may request.';
$string['mccount'] = 'Number of multiple-choice questions';
$string['mccount_help'] = 'How many multiple-choice vocabulary and grammar questions to draft. The administrator sets the maximum.';
$string['modelanswer'] = 'Model answer';
$string['needschecking'] = 'Needs checking';
$string['onewordperline'] = 'One word or phrase per line.';
$string['optionnewcategory'] = '{$a->bank}: new category "{$a->name}"';
$string['partialfailure'] = 'Some questions could not be generated: {$a}';
$string['pdftotextpath'] = 'Path to pdftotext';
$string['pdftotextpath_desc'] = 'Full path to the pdftotext executable (from poppler-utils), for example /usr/bin/pdftotext. Leave empty to disable PDF uploads.';
$string['pluginname'] = 'AI Question Generator';
$string['privacy:metadata:aiprovider'] = 'Article text is sent to the configured external AI provider to generate draft questions.';
$string['privacy:metadata:aiprovider:articletext'] = 'The text of the article, or a section of it.';
$string['privacy:metadata:aiprovider:prompt'] = 'The generation instructions, such as CEFR level and question types.';
$string['privacy:metadata:core_files'] = 'Uploaded source article files are stored in the Moodle file system.';
$string['privacy:metadata:local_aiquestiongen_item'] = 'Draft questions generated from a source article.';
$string['privacy:metadata:local_aiquestiongen_item:jobid'] = 'The generation job the draft belongs to.';
$string['privacy:metadata:local_aiquestiongen_item:payload'] = 'The draft question content as JSON.';
$string['privacy:metadata:local_aiquestiongen_item:qtype'] = 'The question type of the draft.';
$string['privacy:metadata:local_aiquestiongen_item:questionid'] = 'The question bank id once the draft has been published.';
$string['privacy:metadata:local_aiquestiongen_item:status'] = 'The review status of the draft.';
$string['privacy:metadata:local_aiquestiongen_item:timemodified'] = 'The time the draft was last modified.';
$string['privacy:metadata:local_aiquestiongen_job'] = 'Generation runs started for a source article.';
$string['privacy:metadata:local_aiquestiongen_job:error'] = 'Any error message from the run.';
$string['privacy:metadata:local_aiquestiongen_job:provider'] = 'The AI provider used for the run.';
$string['privacy:metadata:local_aiquestiongen_job:settings'] = 'The generation settings as JSON.';
$string['privacy:metadata:local_aiquestiongen_job:sourceid'] = 'The source article the run belongs to.';
$string['privacy:metadata:local_aiquestiongen_job:status'] = 'The status of the run.';
$string['privacy:metadata:local_aiquestiongen_job:timecreated'] = 'The time the run was created.';
$string['privacy:metadata:local_aiquestiongen_job:tokensused'] = 'The number of AI tokens used by the run.';
$string['privacy:metadata:local_aiquestiongen_source'] = 'Source articles uploaded or pasted by users.';
$string['privacy:metadata:local_aiquestiongen_source:courseid'] = 'The course the article was added to.';
$string['privacy:metadata:local_aiquestiongen_source:sourcefileitemid'] = 'The item id of the original uploaded file.';
$string['privacy:metadata:local_aiquestiongen_source:text'] = 'The extracted text of the article.';
$string['privacy:metadata:local_aiquestiongen_source:timecreated'] = 'The time the article was added.';
$string['privacy:metadata:local_aiquestiongen_source:title'] = 'The title of the article.';
$string['privacy:metadata:local_aiquestiongen_source:userid'] = 'The id of the user who added the article.';
$string['provider_claude'] = 'Anthropic Claude (Messages API)';
$string['provider_openai'] = 'OpenAI-compatible (chat completions)';
$string['publishbutton'] = 'Publish {$a} approved questions';
$string['publishdone'] = '{$a} questions were published to the question bank.';
$string['publishfailed'] = '{$a} could not be published. They are still approved, so you can try again.';
$string['publishheading'] = 'Publish to the question bank';
$string['publishintro'] = '{$a} approved questions will be added to the question bank. Questions are tagged ai-generated, with the CEFR level and the article title.';
$string['publishlink'] = 'Publish approved questions';
$string['publishnote'] = 'Approved questions wait here until you publish them to the question bank.';
$string['publishpassagedone'] = 'The article was also added as a reading passage.';
$string['publishquizdone'] = '{$a->count} questions were added to the quiz "{$a->name}".';
$string['publishquizfailed'] = 'The questions were published, but they could not be added to the quiz: {$a}';
$string['publishtarget'] = 'Question bank and category';
$string['publishtarget_help'] = 'Choose an existing category, or have a new category named after the article created in a bank.';
$string['questionnumber'] = 'Edit question {$a}';
$string['quizchoice'] = 'Quiz';
$string['quizchoice_help'] = 'Optionally put the published questions in a quiz. A new quiz is created hidden, with every question on one page, so you can check it before students see it. The reading passage, if included, comes first. Quizzes that already have attempts are not offered.';
$string['quizexisting'] = 'Add to the existing quiz "{$a}"';
$string['quiznew'] = 'Create a new quiz named "{$a}"';
$string['quiznone'] = 'Do not add to a quiz';
$string['readingpassage'] = 'Reading passage';
$string['regencomment'] = 'What should change? (optional)';
$string['regenerateask'] = 'The teacher wants this question replaced with a different one.';
$string['regenerateaskcomment'] = 'The teacher wants this question replaced with a different one. Teacher comment: {$a}';
$string['regenhelp'] = 'For example: make it easier, use a different word, or ask about a different part of the article. The old version is replaced and goes back to pending.';
$string['savesource'] = 'Save article';
$string['sourcefile'] = 'Article file';
$string['sourcefile_help'] = 'Upload a .txt, .docx, .pdf or .html file. PDF support requires the administrator to configure pdftotext.';
$string['sourcepassage'] = 'Source article';
$string['sourcesaved'] = 'The article was saved.';
$string['status_approved'] = 'Approved';
$string['status_done'] = 'Done';
$string['status_failed'] = 'Failed';
$string['status_pending'] = 'Pending';
$string['status_published'] = 'Published';
$string['status_queued'] = 'Waiting to start';
$string['status_rejected'] = 'Rejected';
$string['status_running'] = 'Running';
$string['stemlabel'] = 'Question text';
$string['targetpoints'] = 'Target vocabulary or grammar points';
$string['targetpoints_help'] = 'Optional. List words or grammar points to focus on, separated by commas. Vocabulary targets must appear in the article.';
$string['task_generatequestions'] = 'Generate AI questions for an article';
$string['tfcorrectis'] = 'Correct answer:';
$string['tfcorrectlegend'] = 'The statement is';
$string['tfcount'] = 'Number of true/false questions';
$string['tfcount_help'] = 'How many true/false reading comprehension statements to draft.';
$string['tryagain'] = 'Try again';
$string['uploadheading'] = 'Upload an article';
$string['wrongchoices'] = 'Wrong choices:';
