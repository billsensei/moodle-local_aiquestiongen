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
 * Tests for the daily regeneration limit.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(regen_budget::class)]
final class regen_budget_test extends \advanced_testcase {
    /**
     * Until an administrator saves a value the default applies; 0 means no limit.
     */
    public function test_limit_setting(): void {
        $this->resetAfterTest();
        unset_config('dailyregenlimit', 'local_aiquestiongen');
        $this->assertSame(regen_budget::DEFAULT_LIMIT, regen_budget::limit());
        set_config('dailyregenlimit', '5', 'local_aiquestiongen');
        $this->assertSame(5, regen_budget::limit());
        set_config('dailyregenlimit', '0', 'local_aiquestiongen');
        $this->assertSame(0, regen_budget::limit());
        set_config('dailyregenlimit', '-3', 'local_aiquestiongen');
        $this->assertSame(0, regen_budget::limit());
    }

    /**
     * Each use is counted, the limit stops the next one, and users are counted separately.
     */
    public function test_consume_and_limit(): void {
        $this->resetAfterTest();
        set_config('dailyregenlimit', '2', 'local_aiquestiongen');
        $one = $this->getDataGenerator()->create_user();
        $two = $this->getDataGenerator()->create_user();

        $this->assertSame(0, regen_budget::used_today($one->id));
        regen_budget::consume($one->id);
        regen_budget::consume($one->id);
        $this->assertSame(2, regen_budget::used_today($one->id));

        try {
            regen_budget::consume($one->id);
            $this->fail('The third use must be refused');
        } catch (\moodle_exception $e) {
            $this->assertSame('err_regendaily', $e->errorcode);
            $this->assertStringContainsString('2', $e->getMessage());
        }
        // A refused request is not counted.
        $this->assertSame(2, regen_budget::used_today($one->id));

        // Another user has their own allowance.
        regen_budget::consume($two->id);
        $this->assertSame(1, regen_budget::used_today($two->id));
    }

    /**
     * With no limit nothing is refused.
     */
    public function test_no_limit(): void {
        $this->resetAfterTest();
        set_config('dailyregenlimit', '0', 'local_aiquestiongen');
        $user = $this->getDataGenerator()->create_user();
        for ($i = 0; $i < 40; $i++) {
            regen_budget::consume($user->id);
        }
        $this->assertSame(40, regen_budget::used_today($user->id));
    }

    /**
     * The count starts again on a new day, and a damaged preference counts as zero.
     */
    public function test_new_day_and_bad_value(): void {
        $this->resetAfterTest();
        set_config('dailyregenlimit', '1', 'local_aiquestiongen');
        $user = $this->getDataGenerator()->create_user();

        set_user_preference('local_aiquestiongen_regen', (usergetmidnight(time()) - DAYSECS) . ':99', $user->id);
        $this->assertSame(0, regen_budget::used_today($user->id));
        regen_budget::consume($user->id);
        $this->assertSame(1, regen_budget::used_today($user->id));

        set_user_preference('local_aiquestiongen_regen', 'garbage', $user->id);
        $this->assertSame(0, regen_budget::used_today($user->id));
        set_user_preference('local_aiquestiongen_regen', usergetmidnight(time()) . ':-5', $user->id);
        $this->assertSame(0, regen_budget::used_today($user->id));
    }
}
