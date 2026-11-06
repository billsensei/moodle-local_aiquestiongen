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
 * Sends requests with Moodle's curl wrapper, so the site's proxy settings and the blocked-host
 * and allowed-port lists (Site administration > General > Security > HTTP security) apply.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class curl_transport implements http_transport {
    #[\Override]
    public function post(string $url, array $headers, string $body, int $timeout): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl();
        $curl->setHeader($headers);
        $response = $curl->post($url, $body, [
            'CURLOPT_TIMEOUT' => $timeout,
            'CURLOPT_CONNECTTIMEOUT' => 15,
            'CURLOPT_RETURNTRANSFER' => true,
        ]);

        if ($curl->get_errno() || $response === false) {
            return [0, '']; // The reason is deliberately not passed on; it can contain the URL.
        }
        $info = $curl->get_info();
        return [(int) ($info['http_code'] ?? 0), (string) $response];
    }
}
