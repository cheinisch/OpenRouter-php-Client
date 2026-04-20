<?php

namespace cheinisch;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Exception\RequestException;
use RuntimeException;

/**
 * OpenRouterClient – as of April 2025
 *
 * Supported API features:
 *  - Chat Completions (sync & streaming)
 *  - Tool Calling (functions)
 *  - Structured Outputs (json_object / json_schema)
 *  - Plugins (web, file-parser, response-healing, context-compression)
 *  - Provider Routing
 *  - Reasoning Tokens (Claude 3.7+, o1/o3)
 *  - max_completion_tokens (max_tokens is deprecated)
 *  - Session tracking via x-session-id
 *  - Generation stats via /api/v1/generation
 *
 * Backwards compatibility:
 *  - chat()            → identical signature to original, returns string
 *  - OpenRouterChat()  → identical signature to original
 */
class OpenRouterClient
{
    private HttpClient $http;
    private array $defaultHeaders;

    // =========================================================================
    // Constructor
    // =========================================================================

    /**
     * @param string $apiKey          OpenRouter API key
     * @param array  $defaultHeaders  Optional default headers for all requests
     *                                (e.g. ['HTTP-Referer' => '...', 'X-Title' => '...'])
     */
    public function __construct(string $apiKey, array $defaultHeaders = [])
    {
        $this->defaultHeaders = array_merge([
            'Authorization' => "Bearer {$apiKey}",
            'Content-Type'  => 'application/json',
        ], $defaultHeaders);

        $this->http = new HttpClient([
            'base_uri' => 'https://openrouter.ai/api/v1/',
            'headers'  => $this->defaultHeaders,
        ]);
    }

    // =========================================================================
    // chat() – backwards compatible, returns string
    // =========================================================================

    /**
     * Simple chat completion.
     * Fully backwards compatible – existing calls with 1–3 arguments work unchanged.
     *
     * @param  array  $messages  Conversation history in OpenAI format
     * @param  string $model     Model slug, e.g. "openai/gpt-4o-mini"
     * @param  array  $headers   Request-specific headers
     *                           (HTTP-Referer, X-Title, x-session-id, ...)
     * @param  array  $options   Optional body parameters (see chatEx())
     * @return string            Response text from the model
     *
     * Examples:
     *   // Legacy – works unchanged
     *   $client->chat($messages);
     *   $client->chat($messages, 'openai/gpt-4o');
     *   $client->chat($messages, 'openai/gpt-4o', ['HTTP-Referer' => 'https://my-app.com']);
     *
     *   // New – with optional features
     *   $client->chat($messages, 'openai/gpt-4o', [], [
     *       'max_completion_tokens' => 2048,
     *       'temperature'           => 0.7,
     *       'plugins'               => [['id' => 'web']],
     *   ]);
     */
    public function chat(
        array  $messages,
        string $model   = 'openai/gpt-4o-mini',
        array  $headers = [],
        array  $options = []
    ): string {
        return $this->chatEx($messages, $model, $options, $headers)['content'];
    }

    // =========================================================================
    // chatEx() – all features, returns array
    // =========================================================================

    /**
     * Extended chat completion with access to all current API features.
     *
     * @param  array  $messages  Conversation history in OpenAI format
     * @param  string $model     Model slug
     * @param  array  $options   Optional body parameters:
     *
     *   General:
     *     max_completion_tokens  int     Max tokens in the response (replaces deprecated max_tokens)
     *     temperature            float   Creativity 0–2, default 1.0
     *     top_p                  float   Nucleus sampling (0–1)
     *     top_k                  int     Top-K sampling
     *     frequency_penalty      float   Repetition penalty by frequency (-2 to 2)
     *     presence_penalty       float   Repetition penalty by presence (-2 to 2)
     *     repetition_penalty     float   General repetition penalty
     *     stop                   array   Stop sequences, e.g. ["\n"]
     *     seed                   int     Reproducible output
     *     logprobs               bool    Return log probabilities
     *     top_logprobs           int     Number of top logprobs (0–20, requires logprobs=true)
     *
     *   Tool Calling:
     *     tools                  array   Tool / function definitions (OpenAI format)
     *     tool_choice            string  "auto" | "none" | "required" | specific tool
     *     parallel_tool_calls    bool    Allow multiple tools to be called in parallel
     *
     *   Structured Output:
     *     response_format        array   ['type' => 'json_object']
     *                                 or ['type' => 'json_schema', 'json_schema' => [...]]
     *
     *   Plugins:
     *     plugins                array   [
     *                                       ['id' => 'web'],                  // real-time web search
     *                                       ['id' => 'file-parser'],          // PDF processing
     *                                       ['id' => 'response-healing'],     // automatic JSON repair
     *                                       ['id' => 'context-compression'],  // prompt compression
     *                                    ]
     *
     *   Provider Routing:
     *     provider               array   [
     *                                       'sort'            => 'price' | 'throughput' | 'latency',
     *                                       'data_collection' => 'deny',  // disable provider logging
     *                                       'allow'           => ['openai', 'anthropic'],
     *                                       'ignore'          => ['deepinfra'],
     *                                    ]
     *
     *   Reasoning (Claude 3.7+, o1/o3):
     *     reasoning              array   ['effort' => 'low' | 'medium' | 'high']
     *
     *   Observability:
     *     session_id             string  Session group for requests (max. 256 characters)
     *
     *   Output modalities:
     *     modalities             array   ['text'] | ['text', 'audio'] | ['image']
     *
     * @param  array  $headers  Additional request headers
     *                          (HTTP-Referer, X-Title, x-session-id, x-anthropic-beta, ...)
     * @return array            [
     *                            'id'            => string|null,   // generation ID (for getGenerationStats)
     *                            'content'       => string,        // response text
     *                            'finish_reason' => string|null,   // 'stop'|'length'|'tool_calls'|...
     *                            'tool_calls'    => array,         // tool calls requested by the model
     *                            'reasoning'     => string|null,   // reasoning / thinking text
     *                            'usage'         => array,         // prompt_tokens, completion_tokens, ...
     *                          ]
     *
     * Examples:
     *   // With web plugin
     *   $result = $client->chatEx($messages, 'openai/gpt-4o', [
     *       'plugins' => [['id' => 'web']],
     *   ]);
     *
     *   // With tool calling
     *   $result = $client->chatEx($messages, 'openai/gpt-4o', [
     *       'tools' => [[
     *           'type'     => 'function',
     *           'function' => [
     *               'name'        => 'get_weather',
     *               'description' => 'Returns the current weather for a city',
     *               'parameters'  => [
     *                   'type'       => 'object',
     *                   'properties' => ['city' => ['type' => 'string']],
     *                   'required'   => ['city'],
     *               ],
     *           ],
     *       ]],
     *       'tool_choice' => 'auto',
     *   ]);
     *   if (!empty($result['tool_calls'])) {
     *       $fn   = $result['tool_calls'][0]['function']['name'];
     *       $args = json_decode($result['tool_calls'][0]['function']['arguments'], true);
     *   }
     *
     *   // Structured output with JSON schema
     *   $result = $client->chatEx($messages, 'openai/gpt-4o', [
     *       'response_format' => [
     *           'type'        => 'json_schema',
     *           'json_schema' => [
     *               'name'   => 'cities',
     *               'schema' => [
     *                   'type'       => 'object',
     *                   'properties' => ['cities' => ['type' => 'array', 'items' => ['type' => 'string']]],
     *               ],
     *           ],
     *       ],
     *   ]);
     *
     *   // Provider routing (cheapest providers only)
     *   $result = $client->chatEx($messages, 'openai/gpt-4o', [
     *       'provider' => ['sort' => 'price', 'data_collection' => 'deny'],
     *   ]);
     *
     *   // Reasoning model (Claude 3.7 / o3)
     *   $result = $client->chatEx($messages, 'anthropic/claude-3-7-sonnet', [
     *       'reasoning' => ['effort' => 'high'],
     *   ]);
     *   echo $result['reasoning'];  // thinking text
     *   echo $result['content'];    // final answer
     *
     *   // Fetch generation stats afterwards
     *   $stats = $client->getGenerationStats($result['id']);
     *   echo $stats['usage']['cost'];  // cost in USD
     */
    public function chatEx(
        array  $messages,
        string $model   = 'openai/gpt-4o-mini',
        array  $options = [],
        array  $headers = []
    ): array {
        // session_id can be passed as an option or directly as a header
        $sessionId = $options['session_id'] ?? null;
        unset($options['session_id']);

        $requestHeaders = array_merge($this->defaultHeaders, $headers);
        if ($sessionId) {
            $requestHeaders['x-session-id'] = $sessionId;
        }

        $body = array_merge([
            'model'                 => $model,
            'messages'              => $messages,
            'max_completion_tokens' => 1024,
        ], $options);

        try {
            $response = $this->http->post('chat/completions', [
                'headers' => $requestHeaders,
                'json'    => $body,
            ]);
        } catch (RequestException $e) {
            $raw       = $e->hasResponse() ? (string) $e->getResponse()->getBody() : $e->getMessage();
            $errorData = json_decode($raw, true);
            $message   = $errorData['error']['message'] ?? $raw;
            $code      = $errorData['error']['code']    ?? $e->getCode();
            throw new RuntimeException(
                "OpenRouter API error [{$code}]: {$message}",
                (int) $e->getCode(),
                $e
            );
        }

        $data   = json_decode((string) $response->getBody(), true);
        $choice = $data['choices'][0] ?? null;

        if ($choice === null) {
            throw new RuntimeException(
                'Unexpected OpenRouter response: ' . json_encode($data)
            );
        }

        $msg = $choice['message'] ?? [];

        return [
            'id'            => $data['id']              ?? null,
            'content'       => $msg['content']          ?? '',
            'finish_reason' => $choice['finish_reason'] ?? null,
            'tool_calls'    => $msg['tool_calls']        ?? [],
            'reasoning'     => $msg['reasoning']         ?? ($msg['reasoning_content'] ?? null),
            'usage'         => $data['usage']            ?? [],
        ];
    }

    // =========================================================================
    // stream() – SSE streaming
    // =========================================================================

    /**
     * Streams a chat response via Server-Sent Events (SSE).
     * The $callback is invoked for every received token delta.
     *
     * @param  array    $messages
     * @param  callable $callback  fn(string $delta, bool $done): void
     *                             $delta … next text fragment
     *                             $done  … true on the final call ([DONE])
     * @param  string   $model
     * @param  array    $options   Same keys as chatEx(); 'stream' is set automatically
     * @param  array    $headers
     *
     * Example:
     *   $client->stream(
     *       [['role' => 'user', 'content' => 'Write a poem']],
     *       function(string $delta, bool $done): void {
     *           if ($done) { echo PHP_EOL; return; }
     *           echo $delta;
     *           flush();
     *       }
     *   );
     */
    public function stream(
        array    $messages,
        callable $callback,
        string   $model   = 'openai/gpt-4o-mini',
        array    $options = [],
        array    $headers = []
    ): void {
        $options['stream'] = true;

        $body = array_merge([
            'model'                 => $model,
            'messages'              => $messages,
            'max_completion_tokens' => 1024,
        ], $options);

        try {
            $response = $this->http->post('chat/completions', [
                'headers' => array_merge($this->defaultHeaders, $headers),
                'json'    => $body,
                'stream'  => true,
            ]);
        } catch (RequestException $e) {
            throw new RuntimeException(
                'OpenRouter stream error: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        $stream = $response->getBody();

        while (!$stream->eof()) {
            $line = trim($this->readLine($stream));

            if ($line === '' || !str_starts_with($line, 'data: ')) {
                continue;
            }

            $payload = substr($line, 6);

            if ($payload === '[DONE]') {
                $callback('', true);
                break;
            }

            $chunk = json_decode($payload, true);
            $delta = $chunk['choices'][0]['delta']['content'] ?? '';
            $callback($delta, false);
        }
    }

    // =========================================================================
    // structuredChat() – helper for structured outputs
    // =========================================================================

    /**
     * Returns a JSON-schema-validated response as a decoded PHP array.
     *
     * @param  array  $messages
     * @param  array  $schema    Full json_schema object:
     *                           ['name' => '...', 'schema' => ['type' => 'object', ...]]
     * @param  string $model
     * @param  array  $options
     * @return array             Decoded PHP array
     *
     * Example:
     *   $data = $client->structuredChat(
     *       [['role' => 'user', 'content' => 'List 3 cities in Germany']],
     *       [
     *           'name'   => 'cities',
     *           'schema' => [
     *               'type'       => 'object',
     *               'properties' => [
     *                   'cities' => ['type' => 'array', 'items' => ['type' => 'string']],
     *               ],
     *               'required' => ['cities'],
     *           ],
     *       ]
     *   );
     *   // $data['cities'] → ['Berlin', 'Hamburg', 'Munich']
     */
    public function structuredChat(
        array  $messages,
        array  $schema,
        string $model   = 'openai/gpt-4o-mini',
        array  $options = []
    ): array {
        $options['response_format'] = [
            'type'        => 'json_schema',
            'json_schema' => $schema,
        ];

        $result  = $this->chatEx($messages, $model, $options);
        $decoded = json_decode($result['content'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Failed to parse structured output: ' . $result['content']
            );
        }

        return $decoded;
    }

    // =========================================================================
    // getGenerationStats() – fetch token counts & cost after the request
    // =========================================================================

    /**
     * Fetches token counts and cost for a completed generation.
     * Useful when usage was not available in the response body (e.g. after streaming).
     *
     * @param  string $generationId  Value from chatEx()['id']
     * @return array                 ['usage' => [...], 'model' => '...', 'total_cost' => float, ...]
     *
     * Example:
     *   $result = $client->chatEx($messages, 'openai/gpt-4o');
     *   $stats  = $client->getGenerationStats($result['id']);
     *   echo 'Cost: $' . $stats['usage']['cost'];
     */
    public function getGenerationStats(string $generationId): array
    {
        try {
            $response = $this->http->get("generation?id={$generationId}", [
                'headers' => $this->defaultHeaders,
            ]);
        } catch (RequestException $e) {
            throw new RuntimeException(
                'Generation stats error: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }

        return json_decode((string) $response->getBody(), true)['data'] ?? [];
    }

    // =========================================================================
    // OpenRouterChat() – static convenience wrapper (backwards compatible)
    // =========================================================================

    /**
     * Static convenience wrapper for simple single-turn prompts.
     * Identical signature to the original version.
     *
     * @param  string      $apiKey
     * @param  string      $model
     * @param  string      $prompt
     * @param  string|null $referer  Source URL for OpenRouter attribution
     * @param  string|null $title    App title for OpenRouter attribution
     * @return string
     *
     * Example:
     *   $text = OpenRouterClient::OpenRouterChat(
     *       $_ENV['OPENROUTER_API_KEY'],
     *       'openai/gpt-4o-mini',
     *       'What is the capital of France?',
     *       'https://my-app.com',
     *       'My App'
     *   );
     */
    public static function OpenRouterChat(
        string  $apiKey,
        string  $model,
        string  $prompt,
        ?string $referer = null,
        ?string $title   = null
    ): string {
        $headers = [];
        if ($referer) { $headers['HTTP-Referer'] = $referer; }
        if ($title)   { $headers['X-Title']      = $title;   }

        $client = new self($apiKey, $headers);
        return $client->chat([['role' => 'user', 'content' => $prompt]], $model);
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * Reads a single line from a PSR-7 stream (used for SSE parsing).
     */
    private function readLine($stream): string
    {
        $buffer = '';
        while (!$stream->eof()) {
            $byte = $stream->read(1);
            if ($byte === "\n") {
                break;
            }
            $buffer .= $byte;
        }
        return $buffer;
    }
}