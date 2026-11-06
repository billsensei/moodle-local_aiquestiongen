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
 * A teacher published approved draft questions to the question bank.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class questions_published extends \core\event\base {
    #[\Override]
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'local_aiquestiongen_job';
    }

    #[\Override]
    public static function get_name(): string {
        return get_string('event_questionspublished', 'local_aiquestiongen');
    }

    #[\Override]
    public function get_description(): string {
        return "The user with id '{$this->userid}' published {$this->other['count']} AI-generated question(s) from the " .
            "job with id '{$this->objectid}' to the question category with id '{$this->other['categoryid']}'.";
    }

    #[\Override]
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/aiquestiongen/job.php', ['id' => $this->objectid]);
    }

    #[\Override]
    public static function get_objectid_mapping(): array {
        return ['db' => 'local_aiquestiongen_job', 'restore' => \core\event\base::NOT_MAPPED];
    }

    #[\Override]
    protected function validate_data(): void {
        parent::validate_data();
        if (!isset($this->other['count']) || !isset($this->other['categoryid'])) {
            throw new \coding_exception('The \'count\' and \'categoryid\' values must be set in other.');
        }
    }
}
