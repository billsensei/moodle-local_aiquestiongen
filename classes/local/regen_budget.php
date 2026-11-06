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
 * How many times a user may regenerate drafts per day.
 *
 * Every regenerate request that reaches the AI counts, whether or not it produced a usable question, because it
 * costs the same. The count is kept in a user preference as "midnight:count", so it starts again each day in the
 * user's own timezone and needs no database table. Two requests at the very same moment could both slip under the
 * limit by one; that is accepted for a cost limit.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class regen_budget {
    /** @var int The daily limit used until an administrator saves a different one. */
    public const DEFAULT_LIMIT = 30;

    /** @var string The user preference that holds the count. */
    private const PREFERENCE = 'local_aiquestiongen_regen';

    /**
     * The daily limit.
     *
     * @return int Regenerations per user per day; 0 means no limit.
     */
    public static function limit(): int {
        $value = get_config('local_aiquestiongen', 'dailyregenlimit');
        return $value === false || $value === '' ? self::DEFAULT_LIMIT : max(0, (int) $value);
    }

    /**
     * How many regenerations the user has used today.
     *
     * @param int $userid The user id.
     * @return int
     */
    public static function used_today(int $userid): int {
        $stored = (string) get_user_preferences(self::PREFERENCE, '', $userid);
        [$day, $count] = array_pad(explode(':', $stored, 2), 2, '0');
        return (int) $day === usergetmidnight(time()) ? max(0, (int) $count) : 0;
    }

    /**
     * Use one regeneration, or refuse if the day's limit is already reached.
     *
     * @param int $userid The user id.
     * @throws \moodle_exception If the limit is reached.
     */
    public static function consume(int $userid): void {
        $limit = self::limit();
        $used = self::used_today($userid);
        if ($limit > 0 && $used >= $limit) {
            throw new \moodle_exception('err_regendaily', 'local_aiquestiongen', '', $limit);
        }
        set_user_preference(self::PREFERENCE, usergetmidnight(time()) . ':' . ($used + 1), $userid);
    }
}
