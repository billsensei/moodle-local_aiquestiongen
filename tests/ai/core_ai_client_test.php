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

namespace local_aiquestiongen\ai;

/**
 * Tests for the core AI client.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(core_ai_client::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(client_factory::class)]
final class core_ai_client_test extends \advanced_testcase {
    /**
     * With no AI provider configured, core AI reports failure and the client turns it into an ai_exception.
     */
    public function test_no_provider_throws(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $client = client_factory::create(\context_course::instance($course->id), $user->id);
        $this->assertInstanceOf(core_ai_client::class, $client);

        $this->expectException(ai_exception::class);
        $client->generate_text('Hello');
    }
}
