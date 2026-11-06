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
 * Tests for the saved API key.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(api_key::class)]
final class api_key_test extends \advanced_testcase {
    /** @var string A key to use in the tests. */
    private const KEY = 'sk-secret-key-123';

    /**
     * An encrypted key is read back as plain text, and nothing saved contains the plain text.
     */
    public function test_read_encrypted_key(): void {
        global $CFG;
        // Before Moodle 5.3 the admin setting classes are not autoloaded.
        require_once($CFG->libdir . '/adminlib.php');
        $this->resetAfterTest();
        $this->assertSame('', api_key::read());

        // Saved through the real admin setting, as the settings page does.
        $setting = new \admin_setting_encryptedpassword('local_aiquestiongen/directapikey', 'name', 'desc');
        $this->assertSame('', $setting->write_setting(' ' . self::KEY . ' '));

        $stored = get_config('local_aiquestiongen', 'directapikey');
        $this->assertStringNotContainsString(self::KEY, $stored);
        $this->assertStringStartsWith('sodium:', $stored);
        $this->assertSame(self::KEY, api_key::read());
    }

    /**
     * A key saved as plain text by an earlier version is encrypted once, and not again.
     */
    public function test_encrypt_legacy_key(): void {
        $this->resetAfterTest();
        set_config('directapikey', self::KEY, 'local_aiquestiongen');

        $this->assertTrue(api_key::encrypt_legacy());
        $stored = get_config('local_aiquestiongen', 'directapikey');
        $this->assertStringNotContainsString(self::KEY, $stored);
        $this->assertSame(self::KEY, api_key::read());

        // A second run changes nothing.
        $this->assertFalse(api_key::encrypt_legacy());
        $this->assertSame($stored, get_config('local_aiquestiongen', 'directapikey'));

        // Nothing saved, nothing to do.
        set_config('directapikey', '', 'local_aiquestiongen');
        $this->assertFalse(api_key::encrypt_legacy());
    }

    /**
     * A value that cannot be decrypted gives a clear error that does not reveal it.
     */
    public function test_unreadable_key(): void {
        $this->resetAfterTest();
        foreach (['sodium:not-valid-base64!', 'plain-text-key'] as $value) {
            set_config('directapikey', $value, 'local_aiquestiongen');
            try {
                api_key::read();
                $this->fail("'$value' should not be readable");
            } catch (ai_exception $e) {
                $this->assertStringContainsString('could not be read', $e->getMessage());
                $this->assertStringNotContainsString($value, $e->getMessage() . ($e->debuginfo ?? ''));
            }
        }
    }

    /**
     * Direct mode reports an unreadable key instead of sending it.
     */
    public function test_from_config_with_unreadable_key(): void {
        $this->resetAfterTest();
        set_config('directapikey', 'plain-text-key', 'local_aiquestiongen');
        set_config('directmodel', 'claude-sonnet-5-5', 'local_aiquestiongen');
        $this->expectException(ai_exception::class);
        $this->expectExceptionMessage('could not be read');
        direct_api_client::from_config();
    }
}
