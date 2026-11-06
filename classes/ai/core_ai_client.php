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
 * AI client backed by Moodle's core AI subsystem (the generate_text action).
 *
 * The administrator chooses and configures the provider in Site administration > AI, so this
 * plugin holds no API keys. Core AI also does the usage logging and rate limiting.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_ai_client implements ai_client {
    /** @var \core_ai\manager The core AI manager. */
    private \core_ai\manager $manager;

    /**
     * Constructor.
     *
     * @param int $contextid The context the request is made in (the course context).
     * @param int $userid The user the request is made for.
     * @param \core_ai\manager|null $manager A manager, or null to use the site one.
     */
    public function __construct(
        /** @var int The context id. */
        private readonly int $contextid,
        /** @var int The user id. */
        private readonly int $userid,
        ?\core_ai\manager $manager = null,
    ) {
        $this->manager = $manager ?? \core\di::get(\core_ai\manager::class);
    }

    #[\Override]
    public function generate_text(string $prompt): ai_result {
        $action = new \core_ai\aiactions\generate_text($this->contextid, $this->userid, $prompt);
        $response = $this->manager->process_action($action);

        if (!$response->get_success()) {
            throw new ai_exception($response->get_errormessage());
        }

        $data = $response->get_response_data();
        $text = trim((string) ($data['generatedcontent'] ?? ''));
        if ($text === '') {
            throw new ai_exception(get_string('err_aiempty', 'local_aiquestiongen'));
        }

        return new ai_result(
            $text,
            (string) ($data['model'] ?? ''),
            (int) ($data['prompttokens'] ?? 0) + (int) ($data['completiontokens'] ?? 0),
        );
    }
}
