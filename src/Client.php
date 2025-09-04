<?php
namespace OpenRouter;

use GuzzleHttp\Client as HttpClient;

class Client {
    private HttpClient $http;

    public function __construct(string $apiKey) {
        $this->http = new HttpClient([
            'base_uri' => 'https://openrouter.ai/api/v1/',
            'headers' => [
                'Authorization' => "Bearer {$apiKey}",
                'Content-Type'  => 'application/json'
            ]
        ]);
    }

    public function chat(array $messages, string $model = "openai/gpt-4o-mini"): string {
        $response = $this->http->post("chat/completions", [
            'json' => [
                'model' => $model,
                'messages' => $messages
            ]
        ]);

        $data = json_decode((string) $response->getBody(), true);
        return $data['choices'][0]['message']['content'] ?? '';
    }

    /**
     * Convenience-Wrapper: nur API-Key, Modell und Prompt übergeben.
     */
    public static function OpenRouterChat(string $apiKey, string $model, string $prompt): string {
        $client = new self($apiKey);
        return $client->chat([['role' => 'user', 'content' => $prompt]], $model);
    }
}
