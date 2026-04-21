# Changelog

## v0.3

### Changed
* `chat()` is now a thin wrapper around `chatEx()` – no duplicated logic
* `chat()` signature extended with optional 4th parameter `$options []` – fully backwards compatible
* Replaced deprecated `max_tokens` with `max_completion_tokens` throughout
* Error handling now parses structured API error body (`error.message`, `error.code`) instead of raw response
* Runtime exception messages translated to English

### New
* `chatEx()` – extended chat completion returning `array` with `id`, `content`, `finish_reason`, `tool_calls`, `reasoning`, `usage`
* `stream()` – SSE streaming support with delta callback `fn(string $delta, bool $done): void`
* `structuredChat()` – helper for JSON Schema validated responses, returns decoded PHP array
* `getGenerationStats()` – fetches token counts and cost asynchronously via `/api/v1/generation` using the `id` from `chatEx()`
* Support for `tools`, `tool_choice`, `parallel_tool_calls` (Tool Calling)
* Support for `response_format` with `json_object` and `json_schema` modes (Structured Outputs)
* Support for `plugins` – `web`, `file-parser`, `response-healing`, `context-compression`
* Support for `provider` routing object – `sort`, `data_collection`, `allow`, `ignore`
* Support for `reasoning` parameter – `effort` levels for Claude 3.7+, o1/o3 models
* Support for `session_id` observability tracking (passed as `x-session-id` header)
* Support for `modalities` output parameter

## v0.2.2

### Changes

* Change description from german to english

## v0.2.1

### Changes

* Change Folder structure

## v0.2.0

### Changes

* Change Namespace from OpenROuter\Client to cheinisch\OpenRouterClient
* Change Client::OpenRouterChat to OpenRouterClient::OpenRouterChat

## v0.0.0.4

### New

* Modify Composer description 

## v0.0.0.3

### New

* Add App Title and Website
