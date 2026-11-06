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
 * Builds the prompts sent to the AI, from the editable Mustache templates.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt_builder {
    /** @var string[] CEFR levels the teacher can choose from. */
    public const CEFR_LEVELS = ['A1', 'A2', 'B1', 'B2', 'C1', 'C2'];

    /** @var string[] Template name for each question type. */
    private const TEMPLATES = [
        'multichoice' => 'prompt_mc',
        'truefalse' => 'prompt_tf',
        'essay' => 'prompt_essay',
        'multianswer' => 'prompt_cloze',
        'gapselect' => 'prompt_gap',
    ];

    /**
     * Prompt for a batch of questions of one type.
     *
     * @param string $qtype The question type.
     * @param string $text The article text.
     * @param array $settings The job settings: cefr, target, language.
     * @param int $count How many questions to ask for.
     * @return string
     */
    public static function build(string $qtype, string $text, array $settings, int $count): string {
        return self::render($qtype, $text, $settings, $count);
    }

    /**
     * Prompt to replace one question that failed validation.
     *
     * @param string $qtype The question type.
     * @param string $text The article text.
     * @param array $settings The job settings.
     * @param array $failed The question that failed.
     * @param string[] $problems Human-readable descriptions of what was wrong.
     * @return string
     */
    public static function retry(string $qtype, string $text, array $settings, array $failed, array $problems): string {
        return self::render($qtype, $text, $settings, 1, [
            'retry' => true,
            'previous' => json_encode($failed, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'problems' => implode('; ', $problems),
        ]);
    }

    /**
     * Prompt for a batch of multiple-choice questions.
     *
     * @param string $text The article text.
     * @param array $settings The job settings: cefr, count, target, language.
     * @return string
     */
    public static function multichoice(string $text, array $settings): string {
        return self::build('multichoice', $text, $settings, (int) $settings['count']);
    }

    /**
     * Prompt to replace one multiple-choice question that failed validation.
     *
     * @param string $text The article text.
     * @param array $settings The job settings.
     * @param array $failed The question that failed.
     * @param string[] $problems Human-readable descriptions of what was wrong.
     * @return string
     */
    public static function multichoice_retry(string $text, array $settings, array $failed, array $problems): string {
        return self::retry('multichoice', $text, $settings, $failed, $problems);
    }

    /**
     * Render a prompt template.
     *
     * @param string $qtype The question type.
     * @param string $text The article text.
     * @param array $settings The job settings.
     * @param int $count How many questions to ask for.
     * @param array $extra Extra template context.
     * @return string
     */
    private static function render(string $qtype, string $text, array $settings, int $count, array $extra = []): string {
        global $OUTPUT;

        if (!isset(self::TEMPLATES[$qtype])) {
            throw new \coding_exception("No prompt for question type '$qtype'");
        }

        $context = [
            'count' => $count,
            'cefr' => in_array($settings['cefr'] ?? '', self::CEFR_LEVELS, true) ? $settings['cefr'] : 'B1',
            'target' => trim((string) ($settings['target'] ?? '')),
            'language' => trim((string) ($settings['language'] ?? '')) ?: 'English',
            'text' => $text,
        ] + $extra;

        return trim($OUTPUT->render_from_template('local_aiquestiongen/' . self::TEMPLATES[$qtype], $context));
    }
}
