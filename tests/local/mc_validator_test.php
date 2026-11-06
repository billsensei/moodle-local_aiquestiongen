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
 * Tests for the multiple-choice validator.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(mc_validator::class)]
final class mc_validator_test extends \advanced_testcase {
    /** @var string Source article used by the tests. */
    private const SOURCE = 'Tom was reluctant to leave. He had lived there for years.';

    /**
     * A well-formed vocabulary question.
     *
     * @return array
     */
    private static function good(): array {
        return [
            'type' => 'multichoice',
            'stem' => 'What does "reluctant" mean?',
            'answers' => [
                ['text' => 'unwilling', 'correct' => true, 'feedback' => 'Yes.'],
                ['text' => 'eager', 'correct' => false, 'feedback' => 'No.'],
                ['text' => 'angry', 'correct' => false, 'feedback' => 'No.'],
                ['text' => 'tired', 'correct' => false, 'feedback' => 'No.'],
            ],
            'focus' => 'vocabulary',
            'target' => 'Reluctant',
            'cefr' => 'B1',
        ];
    }

    /**
     * A valid question has no issues, and the target check ignores case.
     */
    public function test_valid(): void {
        $this->assertSame([], mc_validator::validate(self::good(), self::SOURCE));
    }

    /**
     * Each defect is reported with its own code.
     */
    public function test_issue_codes(): void {
        $bad = self::good();
        $bad['type'] = 'essay';
        $this->assertContains('badtype', mc_validator::validate($bad, self::SOURCE));

        $bad = self::good();
        $bad['stem'] = '  ';
        $this->assertContains('nostem', mc_validator::validate($bad, self::SOURCE));

        $bad = self::good();
        $bad['focus'] = 'spelling';
        $this->assertContains('badfocus', mc_validator::validate($bad, self::SOURCE));

        $bad = self::good();
        $bad['answers'][1]['correct'] = true;
        $this->assertContains('correctcount', mc_validator::validate($bad, self::SOURCE));

        $bad = self::good();
        $bad['answers'][0]['correct'] = false;
        $this->assertContains('correctcount', mc_validator::validate($bad, self::SOURCE));

        $bad = self::good();
        $bad['answers'][2]['text'] = ' EAGER ';
        $this->assertContains('duplicate', mc_validator::validate($bad, self::SOURCE));

        $bad = self::good();
        $bad['answers'][3]['text'] = '';
        $this->assertContains('blankanswer', mc_validator::validate($bad, self::SOURCE));

        $bad = self::good();
        $bad['target'] = 'ambitious';
        $this->assertContains('targetmissing', mc_validator::validate($bad, self::SOURCE));
    }

    /**
     * The wrong number of answers stops further checks and does not crash.
     */
    public function test_answer_count(): void {
        $bad = self::good();
        array_pop($bad['answers']);
        $this->assertSame(['answercount'], mc_validator::validate($bad, self::SOURCE));

        unset($bad['answers']);
        $this->assertContains('answercount', mc_validator::validate($bad, self::SOURCE));
    }

    /**
     * "correct" must be a real boolean, so the string "false" is not a correct answer.
     */
    public function test_correct_must_be_boolean(): void {
        $bad = self::good();
        $bad['answers'][1]['correct'] = 'false';
        $this->assertSame([], mc_validator::validate($bad, self::SOURCE));
        $bad['answers'][0]['correct'] = 'true';
        $this->assertContains('correctcount', mc_validator::validate($bad, self::SOURCE));
    }

    /**
     * A grammar question does not need its target to appear in the article.
     */
    public function test_grammar_target_not_checked(): void {
        $item = self::good();
        $item['focus'] = 'grammar';
        $item['target'] = 'past perfect';
        $this->assertSame([], mc_validator::validate($item, self::SOURCE));
    }
}
