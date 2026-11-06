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

namespace local_aiquestiongen\event;

/**
 * A teacher approved a draft question.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class item_approved extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'local_aiquestiongen_item';
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('event_itemapproved', 'local_aiquestiongen');
    }

    #[\Override]
    public function get_description(): string {
        return "The user with id '{$this->userid}' approved the draft question with id '{$this->objectid}'.";
    }

    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/aiquestiongen/job.php', ['id' => $this->other['jobid']]);
    }

    #[\Override]
    public static function get_objectid_mapping(): array {
        return ['db' => 'local_aiquestiongen_item', 'restore' => \core\event\base::NOT_MAPPED];
    }

    #[\Override]
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['jobid'])) {
            throw new \coding_exception('The \'jobid\' value must be set in other.');
        }
    }
}
