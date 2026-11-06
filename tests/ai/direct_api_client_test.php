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
 * Tests for the direct-API client and the client factory, using a fake transport (no network).
 *
 * @package    local_aiquestiongen
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\Group('local_aiquestiongen')]
#[\PHPUnit\Framework\Attributes\CoversClass(direct_api_client::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(client_factory::class)]
final class direct_api_client_test extends \advanced_testcase {
    /** @var string A key that must never appear in anything shown to a teacher. */
    private const KEY = 'sk-secret-key-123';

    /**
     * A transport that records the request and returns a canned reply.
     *
     * @param int $status HTTP status.
     * @param string $body Response body.
     * @return http_transport
     */
    private function transport(int $status, string $body): http_transport {
        return new class ($status, $body) implements http_transport {
            /** @var array The last request: url, headers, body, timeout. */
            public array $last = [];

            /**
             * Constructor.
             *
             * @param int $status HTTP status.
             * @param string $body Response body.
             */
            public function __construct(
                /** @var int Status. */
                private int $status,
                /** @var string Body. */
                private string $body
            ) {
            }

            #[\Override]
            public function post(string $url, array $headers, string $body, int $timeout): array {
                $this->last = compact('url', 'headers', 'body', 'timeout');
                return [$this->status, $this->body];
            }
        };
    }

    /**
     * A Claude client with the given transport.
     *
     * @param http_transport $transport The transport.
     * @param string $endpoint Endpoint override.
     * @return direct_api_client
     */
    private function claude(http_transport $transport, string $endpoint = ''): direct_api_client {
        return new direct_api_client('claude', $endpoint, self::KEY, 'claude-sonnet-5-5', 4000, 60, $transport);
    }

    /**
     * The Anthropic request has the right URL, headers and body, and the reply is read.
     */
    public function test_claude_request_and_response(): void {
        $transport = $this->transport(200, json_encode([
            'model' => 'claude-sonnet-5-5',
            'content' => [['type' => 'text', 'text' => '[{"a":1}]'], ['type' => 'text', 'text' => ' more']],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 30],
        ]));
        $result = $this->claude($transport)->generate_text('Write questions');

        $this->assertSame('[{"a":1}] more', $result->text);
        $this->assertSame('claude-sonnet-5-5', $result->model);
        $this->assertSame(150, $result->tokens);

        $this->assertSame('https://api.anthropic.com/v1/messages', $transport->last['url']);
        $this->assertContains('x-api-key: ' . self::KEY, $transport->last['headers']);
        $this->assertContains('anthropic-version: 2023-06-01', $transport->last['headers']);
        $this->assertSame(60, $transport->last['timeout']);
        $body = json_decode($transport->last['body'], true);
        $this->assertSame('claude-sonnet-5-5', $body['model']);
        $this->assertSame(4000, $body['max_tokens']);
        $this->assertSame([['role' => 'user', 'content' => 'Write questions']], $body['messages']);
    }

    /**
     * A custom endpoint is used for Claude, with a trailing slash tolerated.
     */
    public function test_claude_custom_endpoint(): void {
        $transport = $this->transport(200, json_encode(['content' => [['type' => 'text', 'text' => 'x']]]));
        $this->claude($transport, 'https://proxy.example.org/')->generate_text('p');
        $this->assertSame('https://proxy.example.org/v1/messages', $transport->last['url']);
    }

    /**
     * An OpenAI-compatible request and reply.
     */
    public function test_openai_request_and_response(): void {
        $transport = $this->transport(200, json_encode([
            'choices' => [['message' => ['content' => '  [{"b":2}]  ']]],
            'usage' => ['total_tokens' => 77],
        ]));
        $client = new direct_api_client('openai', 'https://api.example.com/v1/', self::KEY, 'gpt-x', 2000, 30, $transport);
        $result = $client->generate_text('Hi');

        $this->assertSame('[{"b":2}]', $result->text);
        $this->assertSame(77, $result->tokens);
        $this->assertSame('gpt-x', $result->model);
        $this->assertSame('https://api.example.com/v1/chat/completions', $transport->last['url']);
        $this->assertContains('Authorization: Bearer ' . self::KEY, $transport->last['headers']);
        $body = json_decode($transport->last['body'], true);
        $this->assertSame('gpt-x', $body['model']);
        $this->assertSame('Hi', $body['messages'][0]['content']);
    }

    /**
     * Failures become ai_exceptions with a useful message that never contains the API key.
     */
    public function test_errors_are_clear_and_never_leak_the_key(): void {
        $cases = [
            'auth' => [401, '{"error":{"message":"invalid x-api-key"}}', 'refused the request'],
            'forbidden' => [403, '', 'refused the request'],
            'rate' => [429, '{}', 'too many requests'],
            'server' => [500, '{"error":{"message":"Overloaded <b>now</b>"}}', 'HTTP 500'],
            'server detail' => [503, '{"error":{"message":"Overloaded <b>now</b>"}}', 'Overloaded now'],
            'connect' => [0, '', 'could not be reached'],
            'not json' => [200, 'Bad gateway page', 'could not be read'],
            'empty' => [200, json_encode(['content' => []]), 'empty response'],
        ];
        foreach ($cases as $label => [$status, $body, $expected]) {
            try {
                $this->claude($this->transport($status, $body))->generate_text('p');
                $this->fail("Case '$label' should fail");
            } catch (ai_exception $e) {
                $this->assertStringContainsString($expected, $e->getMessage(), $label);
                $this->assertStringNotContainsString(self::KEY, $e->getMessage() . $e->debuginfo, $label);
                $this->assertStringNotContainsString('<b>', $e->getMessage(), $label);
            }
        }
    }

    /**
     * A very long error message from the service is cut short.
     */
    public function test_long_error_detail_is_truncated(): void {
        $body = json_encode(['error' => ['message' => str_repeat('x', 5000)]]);
        try {
            $this->claude($this->transport(500, $body))->generate_text('p');
            $this->fail('Should fail');
        } catch (ai_exception $e) {
            $this->assertLessThan(500, strlen($e->getMessage()));
        }
    }

    /**
     * Settings are read from the plugin config, and incomplete or unsafe ones are refused.
     */
    public function test_from_config(): void {
        $this->resetAfterTest();
        $transport = $this->transport(200, json_encode(['content' => [['type' => 'text', 'text' => 'ok']]]));

        // Claude needs a key and a model; the endpoint is optional.
        set_config('directprovider', 'claude', 'local_aiquestiongen');
        set_config('directapikey', \core\encryption::encrypt(self::KEY), 'local_aiquestiongen');
        set_config('directmodel', 'claude-sonnet-5-5', 'local_aiquestiongen');
        $this->assertSame('ok', direct_api_client::from_config($transport)->generate_text('p')->text);

        // Missing key, missing model, OpenAI without an endpoint, and a non-http endpoint are all refused.
        $bad = [
            ['directapikey', ''],
            ['directmodel', ''],
            ['directprovider', 'openai'],
            ['directendpoint', 'ftp://example.org'],
            ['directendpoint', 'javascript:alert(1)'],
            ['directendpoint', 'http://api.example.org/v1'],
            ['directendpoint', 'http://localhost.example.org/v1'],
            ['directendpoint', 'https://'],
        ];
        foreach ($bad as [$name, $value]) {
            set_config('directprovider', 'claude', 'local_aiquestiongen');
            set_config('directapikey', \core\encryption::encrypt(self::KEY), 'local_aiquestiongen');
            set_config('directmodel', 'claude-sonnet-5-5', 'local_aiquestiongen');
            set_config('directendpoint', '', 'local_aiquestiongen');
            set_config($name, $value, 'local_aiquestiongen');
            try {
                direct_api_client::from_config($transport);
                $this->fail("Setting $name=$value should be refused");
            } catch (ai_exception $e) {
                $this->assertStringContainsString('not fully set up', $e->getMessage());
            }
        }
    }

    /**
     * The API key is only sent over https, or over http to the same machine.
     */
    public function test_secure_endpoint(): void {
        $good = ['', 'https://api.openai.com/v1', 'http://localhost:11434/v1', 'http://127.0.0.1:8000/v1', 'http://[::1]/v1'];
        foreach ($good as $ok) {
            $this->assertTrue(direct_api_client::is_secure_endpoint($ok), $ok);
        }
        $bad = ['http://api.example.org', 'http://localhost.example.org', 'ftp://example.org', 'example.org', 'https://'];
        foreach ($bad as $notok) {
            $this->assertFalse(direct_api_client::is_secure_endpoint($notok), $notok);
        }
    }

    /**
     * The factory picks the client from the connection mode.
     */
    public function test_factory_mode(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $context = \context_course::instance($course->id);
        $user = $this->getDataGenerator()->create_user();

        $this->assertInstanceOf(core_ai_client::class, client_factory::create($context, $user->id));

        set_config('aimode', 'direct', 'local_aiquestiongen');
        try {
            client_factory::create($context, $user->id);
            $this->fail('Direct mode without settings must refuse');
        } catch (ai_exception $e) {
            $this->assertStringContainsString('not fully set up', $e->getMessage());
        }

        set_config('directapikey', \core\encryption::encrypt(self::KEY), 'local_aiquestiongen');
        set_config('directmodel', 'claude-sonnet-5-5', 'local_aiquestiongen');
        $this->assertInstanceOf(direct_api_client::class, client_factory::create($context, $user->id));
    }
}
