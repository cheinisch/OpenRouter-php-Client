# PHP OpenRouter Client

## Usage

## Example

```
<?php
require __DIR__.'/vendor/autoload.php';

use OpenRouter\Client;

$apiKey = getenv('OPENROUTER_API_KEY') ?: 'sk-or-...';

echo Client::OpenRouterChat($apiKey, 'openai/gpt-4o-mini', 'Say only: OK');
```

## Available Language Models