<?php

namespace App\Services\Concerns;

trait NormalizesNewsAnalysisResult
{
    /**
     * Parse model output into a JSON object. LLMs often add prose or markdown fences
     * even when asked for JSON-only; we try several extractions before failing.
     *
     * @return array<string, mixed>
     */
    protected function parseJsonObject(string $text): array
    {
        $candidates = $this->jsonStringCandidates($text);
        $lastException = null;

        foreach ($candidates as $candidate) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }

            try {
                $flags = JSON_THROW_ON_ERROR;
                if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
                    $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
                }
                $decoded = json_decode($candidate, true, 512, $flags);
            } catch (\JsonException $e) {
                $lastException = $e;

                continue;
            }

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        throw new \RuntimeException(
            'The AI response could not be parsed as JSON. Try again, or use a smaller or clearer image.',
            0,
            $lastException
        );
    }

    /**
     * Build ordered, de-duplicated strings to attempt json_decode on.
     *
     * @return list<string>
     */
    private function jsonStringCandidates(string $text): array
    {
        $t = trim($text);
        if (str_starts_with($t, "\xEF\xBB\xBF")) {
            $t = substr($t, 3);
        }

        $candidates = [];
        $candidates[] = $t;

        // Fenced blocks anywhere in the response (not only when the whole body is a fence).
        if (preg_match_all('/```(?:json)?\s*([\s\S]*?)\s*```/', $text, $m)) {
            foreach ($m[1] as $block) {
                $candidates[] = trim($block);
            }
        }

        $balanced = $this->extractFirstBalancedJsonObject($t);
        if ($balanced !== null) {
            $candidates[] = $balanced;
        }

        $seen = [];
        $out = [];

        foreach ($candidates as $c) {
            if ($c === '') {
                continue;
            }
            $key = md5($c);
            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = $c;
            }
        }

        return $out;
    }

    /**
     * Extract the first top-level `{ ... }` with correct string/brace tracking.
     */
    private function extractFirstBalancedJsonObject(string $text): ?string
    {
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escape = false;
        $len = strlen($text);

        for ($i = $start; $i < $len; $i++) {
            $c = $text[$i];

            if ($escape) {
                $escape = false;

                continue;
            }

            if ($inString) {
                if ($c === '\\') {
                    $escape = true;

                    continue;
                }
                if ($c === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($c === '"') {
                $inString = true;

                continue;
            }

            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *   verdict: string,
     *   verdict_hint: string,
     *   confidence: int,
     *   confidence_hint: string,
     *   real_percent: int,
     *   fake_percent: int,
     *   explanation: string,
     *   topics: list<string>
     * }
     */
    protected function normalizeResult(array $data): array
    {
        $verdict = strtoupper((string) ($data['verdict'] ?? 'UNCERTAIN'));
        if (! in_array($verdict, ['REAL', 'FAKE', 'UNCERTAIN'], true)) {
            $verdict = 'UNCERTAIN';
        }

        $verdictHint = (string) ($data['verdict_hint'] ?? 'Assessment complete');
        $confidence = (int) round((float) ($data['confidence'] ?? 50));
        $confidence = max(0, min(100, $confidence));

        $confidenceHint = (string) ($data['confidence_hint'] ?? 'Moderate confidence');
        $real = (int) round((float) ($data['real_percent'] ?? 50));
        $fake = (int) round((float) ($data['fake_percent'] ?? 50));

        $real = max(0, min(100, $real));
        $fake = max(0, min(100, $fake));
        if ($real + $fake !== 100) {
            $fake = 100 - $real;
            $fake = max(0, min(100, $fake));
            if ($real + $fake !== 100) {
                $real = 50;
                $fake = 50;
            }
        }

        $explanation = (string) ($data['explanation'] ?? 'No explanation provided.');
        $topics = $data['related_topics'] ?? [];
        if (! is_array($topics)) {
            $topics = [];
        }
        $topics = array_values(array_filter(array_map(static fn ($t) => is_string($t) ? trim($t) : '', $topics)));
        $topics = array_slice($topics, 0, 12);

        return [
            'verdict' => $verdict,
            'verdict_hint' => $verdictHint,
            'confidence' => $confidence,
            'confidence_hint' => $confidenceHint,
            'real_percent' => $real,
            'fake_percent' => $fake,
            'explanation' => $explanation,
            'topics' => $topics,
        ];
    }
}
