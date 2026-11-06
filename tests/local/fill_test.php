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
 * Tests for fill-in-the-blank building and validation.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(fill_builder::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(fill_validator::class)]
final class fill_test extends \advanced_testcase {
    /** @var string The article. */
    private const SOURCE = 'Tom was reluctant to leave the café. He paid 5% extra for and/or cheese # 1 at {home}.';

    /**
     * A good typed (Cloze) item.
     *
     * @return array
     */
    private static function typed(): array {
        return [
            'type' => 'multianswer', 'stem' => 'Tom was ____ to leave.', 'answer' => 'reluctant',
            'alternatives' => ['unwilling'], 'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1',
        ];
    }

    /**
     * A good chosen (gapselect) item.
     *
     * @return array
     */
    private static function select(): array {
        return [
            'type' => 'gapselect', 'stem' => 'Tom was ____ to leave.', 'answer' => 'reluctant',
            'distractors' => ['eager', 'angry', 'tired'], 'focus' => 'vocabulary', 'target' => 'reluctant', 'cefr' => 'B1',
        ];
    }

    /**
     * The stem is split around exactly one blank of three or more underscores.
     */
    public function test_split_stem(): void {
        $this->assertSame(['Tom was ', ' to leave.'], fill_builder::split_stem('Tom was ____ to leave.'));
        $this->assertSame(['A ', ' b'], fill_builder::split_stem('A ___ b'));
        $this->assertNull(fill_builder::split_stem('No blank here.'));
        $this->assertNull(fill_builder::split_stem('Two ____ blanks ____ here.'));
        $this->assertNull(fill_builder::split_stem('Too short __ blank.'));
    }

    /**
     * The Cloze text escapes the stem as HTML and builds a SHORTANSWER with every accepted word.
     */
    public function test_cloze_text(): void {
        $item = self::typed();
        $item['stem'] = 'Is 3 < 5 & ____ "ok"?';
        $this->assertSame(
            '<p>Is 3 &lt; 5 &amp; {1:SHORTANSWER:=reluctant~=unwilling} &quot;ok&quot;?</p>',
            fill_builder::cloze_text($item)
        );
        $item['stem'] = 'none';
        $this->assertNull(fill_builder::cloze_text($item));
    }

    /**
     * Moodle's own Cloze parser reads back exactly the words we wrote, including awkward characters.
     */
    public function test_cloze_round_trip(): void {
        $this->resetAfterTest();
        $words = ['reluctant', 'and/or', 'a}b', 'a#b', 'a\\b', 'say "hi"', "it's", 'a:b', '{x}', 'a=b', 'café', '日本語', 'a b', '5%'];
        foreach ($words as $word) {
            $item = self::typed();
            $item['answer'] = $word;
            $item['alternatives'] = ['other'];
            $cloze = fill_builder::cloze_text($item);
            $this->assertTrue(fill_builder::cloze_parses($cloze, [$word, 'other']), "Round trip failed for '$word'");
        }
        // A tilde splits the answer in Moodle's parser, which is why the validator refuses it.
        $item = self::typed();
        $item['answer'] = 'a~b';
        $this->assertFalse(fill_builder::cloze_parses(fill_builder::cloze_text($item), ['a~b', 'unwilling']));
    }

    /**
     * The gapselect text uses [[1]] and the correct word is the first choice.
     */
    public function test_gapselect_text_and_choices(): void {
        $item = self::select();
        $item['stem'] = 'Tom <b>was</b> ____ to leave.';
        $this->assertSame('<p>Tom &lt;b&gt;was&lt;/b&gt; [[1]] to leave.</p>', fill_builder::gapselect_text($item));
        $choices = fill_builder::gapselect_choices($item);
        $this->assertSame(['reluctant', 'eager', 'angry', 'tired'], array_column($choices, 'answer'));
        $this->assertSame(['1'], array_values(array_unique(array_column($choices, 'choicegroup'))));
    }

    /**
     * Valid typed items pass, including awkward answers that exist in the article.
     */
    public function test_typed_valid(): void {
        $this->resetAfterTest();
        $this->assertSame([], fill_validator::validate_typed(self::typed(), self::SOURCE));

        $item = self::typed();
        $item['alternatives'] = [];
        $this->assertSame([], fill_validator::validate_typed($item, self::SOURCE));

        $item = self::typed();
        $item['focus'] = 'grammar';
        $item['answer'] = 'for';
        $this->assertSame([], fill_validator::validate_typed($item, ''));
    }

    /**
     * Each typed defect gets its own issue code.
     */
    public function test_typed_issues(): void {
        $this->resetAfterTest();
        $cases = [
            'noblank' => ['stem' => 'No blank here.'],
            'noblank two' => ['stem' => 'A ____ and ____ here.'],
            'badstemchars brace' => ['stem' => 'Tom {1:SA:=x} ____ left.'],
            'badstemchars bracket' => ['stem' => 'Tom [[1]] ____ left.'],
            'noanswer' => ['answer' => '  '],
            'badanswerchars tilde' => ['answer' => 'a~b'],
            'badanswerchars star' => ['alternatives' => ['re*']],
            'badanswerchars newline' => ['answer' => "a\nb"],
            'targetmissing' => ['answer' => 'ambitious'],
            'badalternatives' => ['alternatives' => 'unwilling'],
            'badtype' => ['type' => 'gapselect'],
            'nostem' => ['stem' => ''],
            'badfocus' => ['focus' => 'spelling'],
        ];
        foreach ($cases as $label => $change) {
            $expected = explode(' ', $label)[0];
            $issues = fill_validator::validate_typed($change + self::typed(), self::SOURCE);
            $this->assertContains($expected, $issues, "Case '$label' gave: " . implode(',', $issues));
        }
    }

    /**
     * Valid select items pass; wrong numbers or repeats of words are issues.
     */
    public function test_select(): void {
        $this->assertSame([], fill_validator::validate_select(self::select(), self::SOURCE));

        foreach ([['eager', 'angry'], ['eager', 'angry', 'tired', 'sad'], ['eager', '', 'tired']] as $list) {
            $issues = fill_validator::validate_select(['distractors' => $list] + self::select(), self::SOURCE);
            $this->assertContains('distractorcount', $issues);
        }
        $issues = fill_validator::validate_select(['distractors' => 'eager'] + self::select(), self::SOURCE);
        $this->assertContains('distractorcount', $issues);

        $same = ['distractors' => ['Reluctant', 'angry', 'tired']] + self::select();
        $this->assertContains('duplicate', fill_validator::validate_select($same, self::SOURCE));
        $twice = ['distractors' => ['eager', 'EAGER', 'tired']] + self::select();
        $this->assertContains('duplicate', fill_validator::validate_select($twice, self::SOURCE));

        $this->assertContains('noblank', fill_validator::validate_select(['stem' => 'nothing'] + self::select(), self::SOURCE));
        $issues = fill_validator::validate_select(['answer' => 'ambitious'] + self::select(), self::SOURCE);
        $this->assertContains('targetmissing', $issues);
    }

    /**
     * The registry sends the new types to the right validators.
     */
    public function test_registry(): void {
        $this->resetAfterTest();
        $this->assertSame([], qtypes::validate('multianswer', self::typed(), self::SOURCE));
        $this->assertSame([], qtypes::validate('gapselect', self::select(), self::SOURCE));
        $this->assertContains('badtype', qtypes::validate('gapselect', self::typed(), self::SOURCE));
    }
}
