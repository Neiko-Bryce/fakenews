<?php

namespace App\Services;

use App\Contracts\NewsAnalyzer;
use App\Services\Concerns\NormalizesNewsAnalysisResult;
use App\Services\Concerns\UsesNewsHttpClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class GeminiNewsAnalyzer implements NewsAnalyzer
{
    use NormalizesNewsAnalysisResult;
    use UsesNewsHttpClient;

    private const string GEMINI_BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function analyze(string $mode, ?string $text, ?string $url, ?UploadedFile $image): array
    {
        $key = config('services.gemini.key');
        if (! is_string($key) || $key === '') {
            throw new RuntimeException('GEMINI_API_KEY is not configured. Add it to your .env file.');
        }

        $model = config('services.gemini.model', 'gemini-2.5-flash-lite');
        $maxOut = (int) config('services.gemini.max_output_tokens', 2048);
        $parts = $this->buildParts($mode, $text, $url, $image);

        $endpoint = $this->endpoint($model, $key);

        $body = [
            'systemInstruction' => [
                'parts' => [
                    [
                        'text' => NewsAnalysisPrompts::system(),
                    ],
                ],
            ],
            'contents' => [
                [
                    'role' => 'user',
                    'parts' => $parts,
                ],
            ],
            'generationConfig' => [
                'temperature' => 0.2,
                // Capped via config to limit TPM; raise GEMINI_MAX_OUTPUT_TOKENS if JSON truncates.
                'maxOutputTokens' => max(256, min(8192, $maxOut)),
                'responseMimeType' => 'application/json',
            ],
        ];

        try {
            $response = $this->httpClient()
                ->post($endpoint, $body);
        } catch (ConnectionException $e) {
            throw new RuntimeException('Could not reach the AI service. Check your network and try again.', 0, $e);
        }

        if (! $response->successful()) {
            $status = $response->status();
            $apiMsg = $response->json('error.message');

            if ($status === 429) {
                throw new RuntimeException(
                    'Gemini quota exceeded (429): too many requests or free tier limit. Wait a few minutes, try again later, or check quotas in Google AI Studio. You can try NEWS_ANALYZER_DRIVER=groq and GROQ_API_KEY in .env for a different provider.'
                );
            }

            $msg = is_string($apiMsg) ? $apiMsg : $response->body();
            $msg = strlen($msg) > 500 ? substr($msg, 0, 500).'…' : $msg;

            throw new RuntimeException('AI request failed ('.$status.'): '.$msg);
        }

        $textOut = $this->extractResponseText($response->json());
        $decoded = $this->parseJsonObject($textOut);

        return $this->normalizeResult($decoded);
    }

    /**
     * @return list<array{text: string}|array{inline_data: array{mime_type: string, data: string}}>
     */
    private function buildParts(string $mode, ?string $text, ?string $url, ?UploadedFile $image): array
    {
        $lines = [];

        if ($mode === 'text') {
            $lines[] = 'Analyze the following news content or claim.';
            $lines[] = '---';
            $lines[] = $text ?? '';
        } elseif ($mode === 'url') {
            $lines[] = 'Analyze the following news content. The user provided a URL.';
            $lines[] = 'URL: '.($url ?? '');
            $lines[] = '---';
            $fetched = $this->tryFetchUrlText($url ?? '');
            if ($fetched !== null) {
                $lines[] = 'Fetched page text (truncated):';
                $lines[] = $fetched;
            } else {
                $lines[] = 'The page could not be fetched automatically. Base your assessment on the URL and any public knowledge cautiously, prefer UNCERTAIN if unknown.';
            }
        } else {
            $lines[] = 'Analyze the attached image. It may be a screenshot of news or social media. Assess text visible in the image if readable; otherwise explain uncertainty.';
            $mime = $image?->getMimeType() ?? 'image/jpeg';
            $raw = $image ? file_get_contents($image->getRealPath()) : '';
            if ($raw === false || $raw === '') {
                throw new RuntimeException('Could not read the uploaded image.');
            }

            return [
                ['text' => implode("\n", $lines)],
                [
                    'inline_data' => [
                        'mime_type' => $mime,
                        'data' => base64_encode($raw),
                    ],
                ],
            ];
        }

        return [
            ['text' => implode("\n", $lines)],
        ];
    }

    private function endpoint(string $model, string $key): string
    {
        $model = rawurlencode($model);

        return self::GEMINI_BASE.'/'.$model.':generateContent?key='.rawurlencode($key);
    }

    /**
     * @param  array<string, mixed>|null  $json
     */
    private function extractResponseText(?array $json): string
    {
        $parts = $json['candidates'][0]['content']['parts'] ?? null;
        if (! is_array($parts)) {
            throw new RuntimeException('Unexpected AI response shape.');
        }

        $text = '';
        foreach ($parts as $part) {
            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Empty AI response.');
        }

        return $text;
    }
}
