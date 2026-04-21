# OpenRouter PHP Client

A lightweight PHP client for the [OpenRouter.ai](https://openrouter.ai) API.  
Supports simple single-line calls as well as advanced features like tool calling, structured outputs, SSE streaming, plugins, provider routing, and reasoning models.

## Requirements

* PHP >= 8.1
* Composer
* `guzzlehttp/guzzle` (installed automatically)

## Installation

```bash
composer require cheinisch/openrouter-php-client
```

## Usage

### 1) Static convenience method (minimal)

```php
<?php
require __DIR__.'/vendor/autoload.php';
use cheinisch\OpenRouterClient;

$apiKey = getenv('OPENROUTER_API_KEY');

echo OpenRouterClient::OpenRouterChat($apiKey, 'openai/gpt-4o-mini', 'Say only: OK');
```

### 2) With optional attribution headers

```php
echo OpenRouterClient::OpenRouterChat(
    $apiKey,
    'mistralai/mistral-small',
    'Give me one short fun fact about PHP.',
    'https://my-app.com',  // optional – HTTP-Referer
    'My App'               // optional – X-Title
);
```

### 3) Client instance – simple chat

```php
$client = new OpenRouterClient($apiKey);

$answer = $client->chat(
    [['role' => 'user', 'content' => 'What is the capital of France?']],
    'openai/gpt-4o-mini'
);

echo $answer; // plain string
```

The optional 4th parameter `$options` allows passing any API parameter without breaking existing calls:

```php
$answer = $client->chat(
    [['role' => 'user', 'content' => 'Explain recursion briefly.']],
    'openai/gpt-4o',
    [],   // headers
    [
        'max_completion_tokens' => 256,
        'temperature'           => 0.5,
        'plugins'               => [['id' => 'web']],
    ]
);
```

### 4) Extended chat – `chatEx()`

Returns a full result array with `content`, `usage`, `finish_reason`, `tool_calls`, `reasoning`, and `id`.

```php
$result = $client->chatEx(
    [['role' => 'user', 'content' => 'Summarize todays AI news.']],
    'openai/gpt-4o',
    ['plugins' => [['id' => 'web']]]
);

echo $result['content'];
echo $result['usage']['total_tokens'];
```

### 5) Tool calling

```php
$result = $client->chatEx(
    [['role' => 'user', 'content' => "What's the weather in Berlin?"]],
    'openai/gpt-4o',
    [
        'tools' => [[
            'type'     => 'function',
            'function' => [
                'name'        => 'get_weather',
                'description' => 'Returns the current weather for a city',
                'parameters'  => [
                    'type'       => 'object',
                    'properties' => ['city' => ['type' => 'string']],
                    'required'   => ['city'],
                ],
            ],
        ]],
        'tool_choice' => 'auto',
    ]
);

if (!empty($result['tool_calls'])) {
    $fn   = $result['tool_calls'][0]['function']['name'];
    $args = json_decode($result['tool_calls'][0]['function']['arguments'], true);
}
```

### 6) Structured outputs

```php
$data = $client->structuredChat(
    [['role' => 'user', 'content' => 'List 3 cities in Germany']],
    [
        'name'   => 'cities',
        'schema' => [
            'type'       => 'object',
            'properties' => [
                'cities' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['cities'],
        ],
    ]
);

print_r($data['cities']); // ['Berlin', 'Hamburg', 'Munich']
```

### 7) Streaming

```php
$client->stream(
    [['role' => 'user', 'content' => 'Write a short poem about the ocean.']],
    function(string $delta, bool $done): void {
        if ($done) { echo PHP_EOL; return; }
        echo $delta;
        flush();
    }
);
```

### 8) Reasoning models (Claude 3.7+, o1/o3)

```php
$result = $client->chatEx(
    [['role' => 'user', 'content' => 'Solve this step by step: 3x + 7 = 22']],
    'anthropic/claude-3-7-sonnet',
    ['reasoning' => ['effort' => 'high']]
);

echo $result['reasoning']; // internal thinking text
echo $result['content'];   // final answer
```

### 9) Provider routing

```php
$result = $client->chatEx(
    [['role' => 'user', 'content' => 'Hello']],
    'openai/gpt-4o',
    [
        'provider' => [
            'sort'            => 'price',
            'data_collection' => 'deny',
        ],
    ]
);
```

### 10) Generation stats (cost & token counts)

```php
$result = $client->chatEx($messages, 'openai/gpt-4o');
$stats  = $client->getGenerationStats($result['id']);

echo 'Cost: $' . $stats['usage']['cost'];
```

## Available options (`chat()` / `chatEx()`)

| Parameter | Type | Description |
|---|---|---|
| `max_completion_tokens` | int | Max tokens in the response |
| `temperature` | float | Creativity 0–2, default 1.0 |
| `top_p` / `top_k` | float / int | Sampling parameters |
| `frequency_penalty` | float | Repetition penalty by frequency |
| `presence_penalty` | float | Repetition penalty by presence |
| `stop` | array | Stop sequences |
| `seed` | int | Reproducible output |
| `tools` | array | Tool / function definitions |
| `tool_choice` | string | `auto` \| `none` \| `required` |
| `parallel_tool_calls` | bool | Call multiple tools in parallel |
| `response_format` | array | `json_object` or `json_schema` |
| `plugins` | array | `web`, `file-parser`, `response-healing`, `context-compression` |
| `provider` | array | Routing: `sort`, `allow`, `ignore`, `data_collection` |
| `reasoning` | array | `['effort' => 'low\|medium\|high']` |
| `modalities` | array | `text`, `audio`, `image` |
| `session_id` | string | Session group for observability |

## Available models

* `openai/gpt-4o-mini`
* `openai/gpt-4o`
* `anthropic/claude-3.5-sonnet`
* `anthropic/claude-3-7-sonnet`
* `google/gemini-2.5-flash`
* `mistralai/mistral-small`
* `x-ai/grok-3-mini`
* … and [400+ more on OpenRouter](https://openrouter.ai/models)