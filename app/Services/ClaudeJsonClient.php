<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClaudeJsonClient
{
    protected string $apiKey;
    protected string $model;
    protected int $maxTokens;

    public function __construct()
    {
        $this->apiKey = (string) config('services.claude.api_key');
        $this->model = (string) config('services.claude.model');
        $this->maxTokens = (int) config('services.claude.max_tokens', 4096);
    }

    /**
     * Ask Claude to return JSON only. Returns an array, or [] on parse failure.
     */
    public function promptForJson(string $prompt, int $timeoutSeconds = 120): array
    {
        $text = $this->callClaude([
            [
                'role' => 'user',
                'content' => $prompt,
            ],
        ], $timeoutSeconds);

        return $this->parseJsonResponse($text);
    }

    /**
     * Vision: send an image and ask Claude to return JSON only. Returns an array, or [] on parse failure.
     */
    public function promptForJsonWithImage(string $prompt, string $imageBinary, string $mediaType, int $timeoutSeconds = 180): array
    {
        $b64 = base64_encode($imageBinary);

        $text = $this->callClaude([
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => $prompt],
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => $mediaType,
                            'data' => $b64,
                        ],
                    ],
                ],
            ],
        ], $timeoutSeconds);

        return $this->parseJsonResponse($text);
    }

    protected function callClaude(array $messages, int $timeoutSeconds): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('CLAUDE_API_KEY is not configured');
        }

        $response = Http::withoutVerifying()
            ->withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ])
            ->timeout($timeoutSeconds)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'messages' => $messages,
            ]);

        if (!$response->successful()) {
            Log::error('Claude API request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('Claude API request failed: ' . $response->body());
        }

        $result = $response->json();
        return $result['content'][0]['text'] ?? '';
    }

    /**
     * Parse JSON out of a possibly messy Claude response.
     */
    protected function parseJsonResponse(string $content): array
    {
        $content = trim($content);

        // Extract JSON from code fences if present
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $content, $matches)) {
            $content = trim($matches[1]);
        }

        // Try to find an object/array substring
        if (preg_match('/(\{[\s\S]*\}|\[[\s\S]*\])/', $content, $matches)) {
            $content = trim($matches[1]);
        }

        $data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Attempt light repair for truncation
        $repaired = $this->repairTruncatedJson($content);
        $data = json_decode($repaired, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        Log::warning('Failed to parse Claude JSON response', [
            'error' => json_last_error_msg(),
            'content_preview' => mb_substr($content, 0, 500),
        ]);

        return [];
    }

    protected function repairTruncatedJson(string $json): string
    {
        $json = preg_replace('/,\s*\{[^}]*$/', '', $json);
        $json = preg_replace('/,\s*\[[^\]]*$/', '', $json);

        $openBrackets = substr_count($json, '[');
        $closeBrackets = substr_count($json, ']');
        $openBraces = substr_count($json, '{');
        $closeBraces = substr_count($json, '}');

        $json .= str_repeat('}', max(0, $openBraces - $closeBraces));
        $json .= str_repeat(']', max(0, $openBrackets - $closeBrackets));

        return $json;
    }
}

