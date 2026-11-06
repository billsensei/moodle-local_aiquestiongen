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
 * Language pack checks: English and Japanese match, every string the code uses exists, and none is dead.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversNothing]
final class lang_test extends \advanced_testcase {
    /**
     * Load a language file.
     *
     * @param string $lang The language code.
     * @return string[] Key to text.
     */
    private function load(string $lang): array {
        global $CFG;
        $string = [];
        include($CFG->dirroot . "/local/aiquestiongen/lang/$lang/local_aiquestiongen.php");
        return $string;
    }

    /**
     * Placeholders such as {$a} and {$a->name} in a string.
     *
     * @param string $text The string.
     * @return string[] Sorted, unique.
     */
    private function placeholders(string $text): array {
        preg_match_all('/\{\$a(?:->\w+)?\}/', $text, $matches);
        $found = array_unique($matches[0]);
        sort($found);
        return $found;
    }

    /**
     * The plugin's own source files, without tests and language packs.
     *
     * @return string[] Paths.
     */
    private function source_files(): array {
        global $CFG;
        $root = $CFG->dirroot . '/local/aiquestiongen';
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $path = $file->getPathname();
            $relative = substr($path, strlen($root) + 1);
            $skip = str_starts_with($relative, 'tests/') || str_starts_with($relative, 'lang/')
                || str_starts_with($relative, 'amd/build/');
            if (!$skip && preg_match('/\.(php|mustache|js)$/', $path)) {
                $files[] = $path;
            }
        }
        return $files;
    }

    /**
     * Every string key the plugin's code refers to, including the ones built from a prefix.
     *
     * @return string[] Keys.
     */
    private function used_keys(): array {
        $keys = [];
        $component = 'local_aiquestiongen';
        foreach ($this->source_files() as $path) {
            $code = file_get_contents($path);
            // PHP code: a string request or an exception whose first argument is the key.
            preg_match_all("/(?:get_string|moodle_exception|__construct)\(\s*'([A-Za-z0-9_:]+)'\s*,\s*'$component'/", $code, $m);
            array_push($keys, ...$m[1]);
            // Form help buttons use the key and key_help.
            preg_match_all("/addHelpButton\([^,]+,\s*'([A-Za-z0-9_]+)'\s*,\s*'$component'/", $code, $m);
            foreach ($m[1] as $key) {
                array_push($keys, $key, $key . '_help');
            }
            // Mustache: {{#str}} key, local_aiquestiongen ... {{/str}}.
            preg_match_all('/\{\{#str\}\}\s*([A-Za-z0-9_:]+)\s*,\s*' . $component . '/', $code, $m);
            array_push($keys, ...$m[1]);
            // JavaScript: getString('key', COMPONENT).
            preg_match_all("/getString\(\s*'([A-Za-z0-9_]+)'\s*,\s*COMPONENT/", $code, $m);
            array_push($keys, ...$m[1]);
        }
        // Settings pages use name and name_desc pairs.
        foreach (array_keys($this->load('en')) as $key) {
            if (str_ends_with($key, '_desc')) {
                $keys[] = $key;
            }
        }
        return array_values(array_unique($keys));
    }

    /**
     * Strings that are looked up by a built name, not written out in the code.
     *
     * @return string[] Keys.
     */
    private function dynamic_keys(): array {
        global $CFG;
        $keys = ['pluginname'];
        foreach (['aiquestiongen:use', 'aiquestiongen:publish'] as $capability) {
            $keys[] = $capability;
        }
        foreach (['queued', 'running', 'done', 'failed', 'pending', 'approved', 'rejected', 'published'] as $status) {
            $keys[] = 'status_' . $status;
        }
        foreach (array_keys(local\qtypes::COUNT_KEYS) as $qtype) {
            $keys[] = 'group_' . $qtype;
        }
        foreach (['approved', 'rejected', 'pending', 'saved', 'bulk', 'regenerating', 'regenerated'] as $action) {
            $keys[] = 'live_' . $action;
        }
        // The generate form names one count string per question type (see generate_form::STRINGS).
        foreach (['mccount', 'tfcount', 'essaycount', 'clozecount', 'gapcount'] as $count) {
            array_push($keys, $count, $count . '_help');
        }
        // The regenerate prompt picks one of two strings depending on whether there is a comment.
        array_push($keys, 'regenerateask', 'regenerateaskcomment');
        // Issue codes: every code a validator can return needs a message.
        foreach (glob($CFG->dirroot . '/local/aiquestiongen/classes/local/*validator.php') as $path) {
            $code = file_get_contents($path);
            // Codes added with $issues[] = 'code', and codes in a field => code table.
            preg_match_all('/issues\[\] = \'([a-z]+)\'/', $code, $m);
            preg_match_all('/\'\w+\' => \'(no[a-z]+)\'/', $code, $table);
            foreach (array_merge($m[1], $table[1]) as $issue) {
                $keys[] = 'issue_' . $issue;
            }
        }
        // The privacy strings are checked against the metadata in the privacy test.
        foreach (array_keys($this->load('en')) as $key) {
            if (str_starts_with($key, 'privacy:')) {
                $keys[] = $key;
            }
        }
        return array_values(array_unique($keys));
    }

    /**
     * English and Japanese have exactly the same keys.
     */
    public function test_languages_have_the_same_keys(): void {
        $en = $this->load('en');
        $ja = $this->load('ja');
        $this->assertSame([], array_values(array_diff(array_keys($en), array_keys($ja))), 'Missing in Japanese');
        $this->assertSame([], array_values(array_diff(array_keys($ja), array_keys($en))), 'Only in Japanese');
    }

    /**
     * A translation uses the same placeholders as the English, so no value goes missing in either language.
     */
    public function test_placeholders_match(): void {
        $en = $this->load('en');
        $ja = $this->load('ja');
        foreach ($en as $key => $text) {
            $this->assertSame($this->placeholders($text), $this->placeholders($ja[$key] ?? ''), "Placeholders differ for '$key'");
        }
    }

    /**
     * No Japanese string is an untranslated copy of the English.
     */
    public function test_japanese_is_translated(): void {
        $en = $this->load('en');
        $ja = $this->load('ja');
        // Strings with no real words to translate, such as a bare placeholder.
        $allowed = [];
        foreach ($en as $key => $text) {
            if (in_array($key, $allowed, true)) {
                continue;
            }
            $this->assertNotSame($text, $ja[$key] ?? null, "'$key' is still English in the Japanese pack");
        }
    }

    /**
     * Every string the code refers to exists in both languages.
     */
    public function test_used_strings_exist(): void {
        $en = $this->load('en');
        $ja = $this->load('ja');
        $needed = array_unique(array_merge($this->used_keys(), $this->dynamic_keys()));
        $missing = array_values(array_filter($needed, fn($key) => !isset($en[$key])));
        $this->assertSame([], $missing, 'Used in code but missing from the English pack');
        $missingja = array_values(array_filter($needed, fn($key) => !isset($ja[$key])));
        $this->assertSame([], $missingja, 'Used in code but missing from the Japanese pack');
    }

    /**
     * No string is left in the language pack that nothing uses.
     */
    public function test_no_unused_strings(): void {
        $en = $this->load('en');
        $used = array_unique(array_merge($this->used_keys(), $this->dynamic_keys()));
        $unused = array_values(array_diff(array_keys($en), $used));
        $this->assertSame([], $unused, 'In the language pack but never used');
    }

    /**
     * Strings are in alphabetical order, as Moodle requires.
     */
    public function test_strings_are_sorted(): void {
        foreach (['en', 'ja'] as $lang) {
            $keys = array_keys($this->load($lang));
            $sorted = $keys;
            sort($sorted, SORT_STRING);
            $this->assertSame($sorted, $keys, "The $lang strings are not in alphabetical order");
        }
    }
}
