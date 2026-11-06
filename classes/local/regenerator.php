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

use local_aiquestiongen\ai\ai_client;

/**
 * Replaces one draft question with a fresh one, using the teacher's comment.
 *
 * This is a single re-prompt (the same prompt template as the automatic retry), so only that one
 * draft is touched. If the AI cannot produce a valid replacement the existing draft is left as it was.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class regenerator {
    /** @var int How many times one draft may be regenerated, to keep AI cost in check. */
    public const MAX_PER_ITEM = 5;

    /** @var int Longest teacher comment sent to the AI, in characters. */
    public const MAX_COMMENT = 500;

    /**
     * Constructor.
     *
     * @param ai_client $client The AI backend.
     */
    public function __construct(
        /** @var ai_client The AI backend. */
        private readonly ai_client $client,
    ) {
    }

    /**
     * Whether a draft may be regenerated.
     *
     * @param \stdClass $item The item record.
     * @return bool
     */
    public static function can_regenerate(\stdClass $item): bool {
        if ($item->status === 'published') {
            return false;
        }
        $payload = json_decode((string) $item->payload, true) ?: [];
        return (int) ($payload['regenerations'] ?? 0) < self::MAX_PER_ITEM;
    }

    /**
     * Replace a draft.
     *
     * @param \stdClass $item The item record.
     * @param \stdClass $job The job record.
     * @param \stdClass $source The source record.
     * @param string $comment What the teacher wants changed; may be empty.
     * @param int|null $userid The user asking. When given, the user's daily regeneration limit applies.
     * @return \stdClass The updated item record.
     * @throws \moodle_exception If the draft is published, a limit is reached, or no valid replacement came back.
     */
    public function regenerate(
        \stdClass $item,
        \stdClass $job,
        \stdClass $source,
        string $comment = '',
        ?int $userid = null
    ): \stdClass {
        global $DB;

        if ($item->status === 'published') {
            throw new \moodle_exception('err_published', 'local_aiquestiongen');
        }
        if (!self::can_regenerate($item)) {
            throw new \moodle_exception('err_regenlimit', 'local_aiquestiongen', '', self::MAX_PER_ITEM);
        }

        // Only a request that will really call the AI uses up the day's allowance.
        if ($userid !== null) {
            regen_budget::consume($userid);
        }

        $payload = json_decode((string) $item->payload, true) ?: [];
        $settings = json_decode((string) $job->settings, true) ?: [];
        $comment = \core_text::substr(trim(preg_replace('/\s+/u', ' ', $comment)), 0, self::MAX_COMMENT);

        // What the model is told was wrong: the teacher's comment, and any problems the validator found.
        $problems = [get_string($comment === '' ? 'regenerateask' : 'regenerateaskcomment', 'local_aiquestiongen', $comment)];
        foreach ((array) ($payload['issues'] ?? []) as $code) {
            $problems[] = get_string('issue_' . $code, 'local_aiquestiongen');
        }

        $old = array_diff_key($payload, array_flip(['flagged', 'issues', 'regenerations']));
        $tokens = 0;
        $new = null;
        for ($attempt = 0; $attempt < 2 && $new === null; $attempt++) {
            $result = $this->client->generate_text(prompt_builder::retry($item->qtype, $source->text, $settings, $old, $problems));
            $tokens += $result->tokens;
            $candidate = (response_parser::parse($result->text) ?? [])[0] ?? null;
            if ($candidate !== null && qtypes::validate($item->qtype, $candidate, $source->text) === []) {
                $new = $candidate;
            }
        }

        if ($tokens > 0) {
            $DB->set_field('local_aiqg_job', 'tokensused', (int) $job->tokensused + $tokens, ['id' => $job->id]);
        }
        if ($new === null) {
            throw new \moodle_exception('err_regenfailed', 'local_aiquestiongen');
        }

        $new['flagged'] = false;
        $new['issues'] = [];
        $new['regenerations'] = (int) ($payload['regenerations'] ?? 0) + 1;

        $item->payload = json_encode($new, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $item->status = 'pending';
        $item->timemodified = time();
        $DB->update_record('local_aiqg_item', $item);
        return $item;
    }
}
