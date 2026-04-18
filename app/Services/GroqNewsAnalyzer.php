<?php

namespace App\Services;

use App\Contracts\NewsAnalyzer;
use App\Services\Concerns\NormalizesNewsAnalysisResult;
use App\Services\Concerns\UsesNewsHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class GroqNewsAnalyzer implements NewsAnalyzer
{
    use NormalizesNewsAnalysisResult;
    use UsesNewsHttpClient;

    private const string GROQ_CHAT = 'https://api.groq.com/openai/v1/chat/completions';

    public function analyze(string $mode, ?string $text, ?string $url, ?UploadedFile $image): array
    {
        $key = config('services.groq.key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('GROQ_API_KEY is not configured. Add it to your .env file (get a key at https://console.groq.com/).');
        }

        $textModel = config('services.groq.model', 'llama-3.1-8b-instant');
        $visionModel = config('services.groq.vision_model', 'llama-3.2-11b-vision-preview');
        $model = $mode === 'image' ? $visionModel : $textModel;
        $maxTokens = (int) config('services.groq.max_tokens', 2048);

        $userContent = $this->buildUserContent($mode, $text, $url, $image);

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => NewsAnalysisPrompts::system(),
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            'temperature' => 0.2,
            'max_tokens' => max(256, min(8192, $maxTokens)),
            'response_format' => [
                'type' => 'json_object',
            ],
        ];

        try {
            $response = $this->httpClient()
                ->withToken($key)
                ->post(self::GROQ_CHAT, $body);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach Groq. Check your network and try again.', 0, $e);
        }

        if (! $response->successful()) {
            $status = $response->status();
            $apiMsg = $response->json('error.message');

            if ($status === 429) {
                throw new RuntimeException(
                    'Groq rate limit (429). Wait a few minutes or check limits at https://console.groq.com/'
                );
            }

            $msg = is_string($apiMsg) ? $apiMsg : $response->body();
            $msg = strlen($msg) > 500 ? substr($msg, 0, 500).'…' : $msg;

            throw new RuntimeException('Groq request failed ('.$status.'): '.$msg);
        }

        $textOut = $this->extractResponseText($response->json());
        $decoded = $this->parseJsonObject($textOut);

        return $this->normalizeResult($decoded);
    }

    /**
     * @return string|list<array{type: string, text?: string, image_url?: array{url: string}}>
     */
    private function buildUserContent(string $mode, ?string $text, ?string $url, ?UploadedFile $image): array|string
    {
        if ($mode === 'text') {
            return "Analyze the following news content or claim.\n---\n".($text ?? '');
        }

        if ($mode === 'url') {
            $lines = [
                'Analyze the following news content. The user provided a URL.',
                'URL: '.($url ?? ''),
                '---',
            ];
            $fetched = $this->tryFetchUrlText($url ?? '');
            if ($fetched !== null) {
                $lines[] = 'Fetched page text (truncated):';
                $lines[] = $fetched;
            } else {
                $lines[] = 'The page could not be fetched automatically. Base your assessment on the URL cautiously; prefer UNCERTAIN if unknown.';
            }

            return implode("\n", $lines);
        }

        $mime = $image?->getMimeType() ?? 'image/jpeg';
        $raw = $image ? file_get_contents($image->getRealPath()) : '';
        if ($raw === false || $raw === '') {
            throw new RuntimeException('Could not read the uploaded image.');
        }

        $b64 = base64_encode($raw);
        $dataUrl = 'data:'.$mime.';base64,'.$b64;

        return [
            [
                'type' => 'text',
                'text' => 'Analyze the attached image. It may be a screenshot of news or social media. Assess visible text; if unreadable, explain uncertainty.',
            ],
            [
                'type' => 'image_url',
                'image_url' => [
                    'url' => $dataUrl,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractResponseText(?array $json): string
    {
        $content = $json['choices'][0]['message']['content'] ?? null;
        if (! is_string($content)) {
            throw new RuntimeException('Unexpected Groq response shape.');
        }

        $content = trim($content);
        if ($content === '') {
            throw new RuntimeException('Empty AI response.');
        }

        return $content;
    }
}
