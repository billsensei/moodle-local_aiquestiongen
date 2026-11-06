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

namespace local_aiquestiongen;

/**
 * Smoke test: the plugin and its privacy provider load.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(\local_aiquestiongen\privacy\provider::class)]
final class smoke_test extends \advanced_testcase {
    /**
     * The privacy provider class autoloads.
     */
    public function test_plugin_loads(): void {
        $this->resetAfterTest();
        $this->assertTrue(class_exists(\local_aiquestiongen\privacy\provider::class));
    }

    /**
     * The metadata provider declares the three tables and the external AI location.
     */
    public function test_privacy_metadata(): void {
        $collection = privacy\provider::get_metadata(new \core_privacy\local\metadata\collection('local_aiquestiongen'));
        $names = array_map(fn($item) => $item->get_name(), $collection->get_collection());
        $this->assertContains('local_aiquestiongen_source', $names);
        $this->assertContains('local_aiquestiongen_job', $names);
        $this->assertContains('local_aiquestiongen_item', $names);
        $this->assertContains('aiprovider', $names);
    }

    /**
     * The capabilities exist.
     */
    public function test_capabilities_defined(): void {
        $this->resetAfterTest();
        $this->assertNotNull(get_capability_info('local/aiquestiongen:use'));
        $this->assertNotNull(get_capability_info('local/aiquestiongen:publish'));
    }
}
