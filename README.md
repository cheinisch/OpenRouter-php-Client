# PHP OpenRouter Client

## Installation

`composer require cheinisch/php-openrouter-client`

## Usage

### Example

Minimal usage, without details

```
<?php
require __DIR__.'/vendor/autoload.php';

use OpenRouter\Client;

$apiKey = getenv('OPENROUTER_API_KEY') ?: 'sk-or-...';
echo Client::OpenRouterChat($apiKey, 'openai/gpt-4o-mini', 'Say only: OK');
```

Usage with App details

## Available Language Models

* openai/gpt-4o-mini
* anthropic/claude-3.5-sonnet
* google/gemini-2.5-flash
* x-ai/grok-3-mini
* mistralai/mistral-small