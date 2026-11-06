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
 * AI client that calls a model provider's HTTP API directly (mode B).
 *
 * Supports the Anthropic Messages API and OpenAI-compatible chat completions. The administrator
 * supplies the key, model and (for OpenAI-compatible services) the endpoint in the plugin settings.
 * Core AI (mode A) stays the default; this mode is for sites that want a specific model or provider
 * that core AI does not offer.
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class direct_api_client implements ai_client {
    /** @var string Provider type: the Anthropic Messages API. */
    public const PROVIDER_CLAUDE = 'claude';

    /** @var string Provider type: an OpenAI-compatible chat completions API. */
    public const PROVIDER_OPENAI = 'openai';

    /** @var string Default endpoint base for the Anthropic API. */
    public const CLAUDE_BASE = 'https://api.anthropic.com';

    /** @var string The Anthropic API version header value. */
    private const CLAUDE_VERSION = '2023-06-01';

    /** @var http_transport The transport. */
    private http_transport $transport;

    /**
     * Constructor.
     *
     * @param string $provider PROVIDER_CLAUDE or PROVIDER_OPENAI.
     * @param string $endpoint Base URL; empty means the Anthropic default for Claude.
     * @param string $apikey The API key.
     * @param string $model The model name.
     * @param int $maxtokens Maximum tokens in the reply.
     * @param int $timeout Request timeout in seconds.
     * @param http_transport|null $transport A transport, or null for Moodle's curl.
     */
    public function __construct(
        /** @var string The provider type. */
        private readonly string $provider,
        /** @var string The endpoint base URL. */
        private readonly string $endpoint,
        /** @var string The API key. */
        private readonly string $apikey,
        /** @var string The model. */
        private readonly string $model,
        /** @var int The reply token limit. */
        private readonly int $maxtokens = 8000,
        /** @var int The timeout in seconds. */
        private readonly int $timeout = 120,
        ?http_transport $transport = null,
    ) {
        $this->transport = $transport ?? new curl_transport();
    }

    /**
     * Build a client from the plugin settings.
     *
     * @param http_transport|null $transport A transport, or null for Moodle's curl.
     * @return self
     * @throws ai_exception If the settings are incomplete.
     */
    public static function from_config(?http_transport $transport = null): self {
        $config = get_config('local_aiquestiongen');
        $provider = ($config->directprovider ?? '') === self::PROVIDER_OPENAI ? self::PROVIDER_OPENAI : self::PROVIDER_CLAUDE;
        $endpoint = trim((string) ($config->directendpoint ?? ''));
        $apikey = api_key::read();
        $model = trim((string) ($config->directmodel ?? ''));

        $scheme = $endpoint === '' ? 'https' : strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
        $complete = $apikey !== '' && $model !== ''
            && in_array($scheme, ['http', 'https'], true)
            && ($provider === self::PROVIDER_CLAUDE || $endpoint !== '');
        if (!$complete) {
            throw new ai_exception(get_string('err_directnotconfigured', 'local_aiquestiongen'));
        }

        return new self(
            $provider,
            $endpoint,
            $apikey,
            $model,
            max(256, (int) ($config->directmaxtokens ?? 8000)),
            max(10, (int) ($config->directtimeout ?? 120)),
            $transport
        );
    }

    #[\Override]
    public function generate_text(string $prompt): ai_result {
        [$url, $headers, $body] = $this->build_request($prompt);

        [$status, $response] = $this->transport->post($url, $headers, json_encode($body), $this->timeout);

        if ($status === 0) {
            throw new ai_exception(get_string('err_directconnect', 'local_aiquestiongen'));
        }
        $decoded = json_decode($response, true);
        if ($status >= 400) {
            throw new ai_exception($this->error_message($status, is_array($decoded) ? $decoded : []));
        }
        if (!is_array($decoded)) {
            throw new ai_exception(get_string('err_directbadresponse', 'local_aiquestiongen'));
        }

        [$text, $tokens] = $this->provider === self::PROVIDER_CLAUDE
            ? $this->read_claude($decoded)
            : $this->read_openai($decoded);

        if (trim($text) === '') {
            throw new ai_exception(get_string('err_aiempty', 'local_aiquestiongen'));
        }
        return new ai_result(trim($text), (string) ($decoded['model'] ?? $this->model), $tokens);
    }

    /**
     * Work out the URL, headers and body for the provider.
     *
     * @param string $prompt The prompt.
     * @return array{0: string, 1: string[], 2: array} URL, header lines and body.
     */
    private function build_request(string $prompt): array {
        $messages = [['role' => 'user', 'content' => $prompt]];

        if ($this->provider === self::PROVIDER_CLAUDE) {
            $base = $this->endpoint !== '' ? $this->endpoint : self::CLAUDE_BASE;
            return [
                rtrim($base, '/') . '/v1/messages',
                [
                    'Content-Type: application/json',
                    'x-api-key: ' . $this->apikey,
                    'anthropic-version: ' . self::CLAUDE_VERSION,
                ],
                ['model' => $this->model, 'max_tokens' => $this->maxtokens, 'messages' => $messages],
            ];
        }

        return [
            rtrim($this->endpoint, '/') . '/chat/completions',
            ['Content-Type: application/json', 'Authorization: Bearer ' . $this->apikey],
            ['model' => $this->model, 'messages' => $messages],
        ];
    }

    /**
     * Read text and token use from an Anthropic response.
     *
     * @param array $data The decoded response.
     * @return array{0: string, 1: int} Text and total tokens.
     */
    private function read_claude(array $data): array {
        $text = '';
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }
        $usage = (array) ($data['usage'] ?? []);
        return [$text, (int) ($usage['input_tokens'] ?? 0) + (int) ($usage['output_tokens'] ?? 0)];
    }

    /**
     * Read text and token use from an OpenAI-compatible response.
     *
     * @param array $data The decoded response.
     * @return array{0: string, 1: int} Text and total tokens.
     */
    private function read_openai(array $data): array {
        $text = (string) ($data['choices'][0]['message']['content'] ?? '');
        return [$text, (int) ($data['usage']['total_tokens'] ?? 0)];
    }

    /**
     * Describe an HTTP error for the teacher. Never includes the key or the request.
     *
     * @param int $status The HTTP status.
     * @param array $decoded The decoded error body, if any.
     * @return string
     */
    private function error_message(int $status, array $decoded): string {
        $detail = $decoded['error']['message'] ?? $decoded['error'] ?? '';
        $detail = is_string($detail) ? \core_text::substr(trim(strip_tags($detail)), 0, 300) : '';

        if ($status === 401 || $status === 403) {
            return get_string('err_directauth', 'local_aiquestiongen', $status);
        }
        if ($status === 429) {
            return get_string('err_directrate', 'local_aiquestiongen');
        }
        return get_string('err_directhttp', 'local_aiquestiongen', ['status' => $status, 'detail' => $detail]);
    }
}
