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
 * Sends one HTTP POST. A separate interface so the direct-API client can be tested without a network.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface http_transport {
    /**
     * POST a body and return the status and response body.
     *
     * @param string $url The URL.
     * @param string[] $headers Header lines, each "Name: value".
     * @param string $body The request body.
     * @param int $timeout Total timeout in seconds.
     * @return array{0: int, 1: string} HTTP status (0 if the request itself failed) and the response body.
     */
    public function post(string $url, array $headers, string $body, int $timeout): array;
}
