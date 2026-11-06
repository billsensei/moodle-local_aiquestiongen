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
 * The saved API key for direct API mode.
 *
 * The key is kept encrypted in the plugin settings (see the admin setting in settings.php), using Moodle's
 * encryption, whose key file lives in the site's data directory and not in the database.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api_key {
    /** @var string Prefix of values written by \core\encryption. */
    private const PREFIX = 'sodium:';

    /**
     * The key as plain text, ready to send to the provider.
     *
     * @return string The key, or an empty string if none is saved.
     * @throws ai_exception If a key is saved but cannot be decrypted, for example after a restore on another site.
     */
    public static function read(): string {
        $stored = trim((string) get_config('local_aiquestiongen', 'directapikey'));
        if ($stored === '') {
            return '';
        }
        try {
            return trim(\core\encryption::decrypt($stored));
        } catch (\moodle_exception $e) {
            // The reason can name internals; it is not passed on, and certainly never the key.
            throw new ai_exception(get_string('err_directkey', 'local_aiquestiongen'));
        }
    }

    /**
     * Encrypt a key that an earlier version saved as plain text. Safe to run more than once.
     *
     * @return bool True if a plain text key was found and encrypted.
     */
    public static function encrypt_legacy(): bool {
        $stored = trim((string) get_config('local_aiquestiongen', 'directapikey'));
        if ($stored === '' || str_starts_with($stored, self::PREFIX)) {
            return false;
        }
        set_config('directapikey', \core\encryption::encrypt($stored), 'local_aiquestiongen');
        return true;
    }
}
