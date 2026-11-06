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
 * Tests for the true/false and essay validators and the question type registry.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(tf_validator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(essay_validator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(qtypes::class)]
final class validators_test extends \advanced_testcase {
    /**
     * A good true/false item.
     *
     * @return array
     */
    private static function tf(): array {
        return [
            'type' => 'truefalse', 'stem' => 'Tom stayed.', 'correct' => false,
            'feedbacktrue' => 'No.', 'feedbackfalse' => 'Yes.', 'focus' => 'comprehension', 'cefr' => 'B1',
        ];
    }

    /**
     * A good essay item.
     *
     * @return array
     */
    private static function essay(): array {
        return [
            'type' => 'essay', 'stem' => 'Why did Tom leave?', 'modelanswer' => 'He had to.',
            'rubric' => 'Mentions the reason.', 'focus' => 'comprehension', 'cefr' => 'B1',
        ];
    }

    /**
     * Valid true/false items pass, whether true or false.
     */
    public function test_tf_valid(): void {
        $this->assertSame([], tf_validator::validate(self::tf()));
        $item = self::tf();
        $item['correct'] = true;
        $this->assertSame([], tf_validator::validate($item));
    }

    /**
     * Each true/false defect has its own code, and the string "false" is not a boolean.
     */
    public function test_tf_issues(): void {
        $item = self::tf();
        $item['correct'] = 'false';
        $this->assertContains('nocorrect', tf_validator::validate($item));

        $item = self::tf();
        unset($item['correct']);
        $this->assertContains('nocorrect', tf_validator::validate($item));

        $item = self::tf();
        $item['stem'] = ' ';
        $this->assertContains('nostem', tf_validator::validate($item));

        $item = self::tf();
        $item['type'] = 'multichoice';
        $this->assertContains('badtype', tf_validator::validate($item));

        $item = self::tf();
        $item['focus'] = 'spelling';
        $this->assertContains('badfocus', tf_validator::validate($item));
    }

    /**
     * A valid essay passes and each missing part is reported.
     */
    public function test_essay(): void {
        $this->assertSame([], essay_validator::validate(self::essay()));

        foreach (['stem' => 'nostem', 'modelanswer' => 'nomodelanswer', 'rubric' => 'norubric'] as $field => $code) {
            $item = self::essay();
            $item[$field] = '  ';
            $this->assertSame([$code], essay_validator::validate($item));
            unset($item[$field]);
            $this->assertSame([$code], essay_validator::validate($item));
        }
    }

    /**
     * The registry sends each type to its validator and rejects unknown types.
     */
    public function test_registry(): void {
        $this->assertSame([], qtypes::validate('truefalse', self::tf(), ''));
        $this->assertSame([], qtypes::validate('essay', self::essay(), ''));
        $this->assertSame(['badtype'], qtypes::validate('matching', self::tf(), ''));
        // A true/false item checked as an essay is the wrong type and misses fields.
        $this->assertContains('badtype', qtypes::validate('essay', self::tf(), ''));
    }

    /**
     * Counts come from the right settings and are never negative.
     */
    public function test_counts(): void {
        $this->assertSame(
            ['multichoice' => 3, 'truefalse' => 2, 'essay' => 0, 'multianswer' => 1, 'gapselect' => 0],
            qtypes::counts(['count' => 3, 'tfcount' => '2', 'essaycount' => -4, 'clozecount' => 1])
        );
        $this->assertSame(
            ['multichoice' => 5, 'truefalse' => 0, 'essay' => 0, 'multianswer' => 0, 'gapselect' => 0],
            qtypes::counts(['count' => 5])
        );
    }
}
