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
 * Tests for the AI response parser.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(response_parser::class)]
final class response_parser_test extends \advanced_testcase {
    /**
     * A plain JSON array parses.
     */
    public function test_plain_array(): void {
        $items = response_parser::parse('[{"stem":"A"},{"stem":"B"}]');
        $this->assertCount(2, $items);
        $this->assertSame('B', $items[1]['stem']);
    }

    /**
     * A fenced block with chatter around it parses.
     */
    public function test_fenced_with_prose(): void {
        $items = response_parser::parse("Sure! Here you go:\n\x60\x60\x60json\n[{\"stem\":\"A\"}]\n\x60\x60\x60\nHope that helps.");
        $this->assertSame('A', $items[0]['stem']);
    }

    /**
     * Prose around bare JSON, without a fence, parses.
     */
    public function test_bare_json_in_prose(): void {
        $items = response_parser::parse('Here: [{"stem":"A"}] done');
        $this->assertSame('A', $items[0]['stem']);
    }

    /**
     * An object with a "questions" array, and a single object, both parse.
     */
    public function test_object_shapes(): void {
        $this->assertCount(2, response_parser::parse('{"questions":[{"stem":"A"},{"stem":"B"}]}'));
        $this->assertCount(1, response_parser::parse('{"stem":"A"}'));
    }

    /**
     * Unusable input returns null rather than a guess.
     *
     * @param string $text The model output.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('unusable_provider')]
    public function test_unusable(string $text): void {
        $this->assertNull(response_parser::parse($text));
    }

    /**
     * Inputs that must not parse.
     *
     * @return array
     */
    public static function unusable_provider(): array {
        return [
            'empty' => [''],
            'prose' => ['I cannot help with that.'],
            'truncated' => ['[{"stem":"A","answers":['],
            'list of scalars' => ['["a","b"]'],
            'empty array' => ['[]'],
        ];
    }
}
