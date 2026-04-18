<?php

namespace App\Services;

use App\Contracts\NewsAnalyzer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Runs Gemini and/or Groq in alternating order (starting from NEWS_ANALYZER_DRIVER).
 * On quota / rate-limit style failures (429, etc.), switches to the other provider and
 * can cycle back (e.g. Gemini → Groq → Gemini → …) until success or max attempts.
 */
class FallbackNewsAnalyzer implements NewsAnalyzer
{
    public function __construct(
        private readonly GeminiNewsAnalyzer $gemini,
        private readonly GroqNewsAnalyzer $groq,
    ) {}

    public function analyze(string $mode, ?string $text, ?string $url, ?UploadedFile $image): array
    {
        $driver = config('services.news_analyzer.driver', 'gemini');
        $this->assertPrimaryProviderConfigured($driver);

        $chain = $this->buildAlternatingChain($driver);

        if ($chain === []) {
            throw new RuntimeException('No news analyzer API keys configured.');
        }

        $fallbackEnabled = $this->isFallbackEnabled();

        if (! $fallbackEnabled || count($chain) === 1) {
            try {
                return $chain[0]['analyzer']->analyze($mode, $text, $url, $image);
            } catch (RuntimeException $e) {
                if (
                    $fallbackEnabled
                    && count($chain) === 1
                    && $this->isQuotaOrRateLimitFailure($e)
                ) {
                    $this->throwMissingBackupKeyHint($driver, $e);
                }

                throw $e;
            }
        }

        $maxAttempts = max(2, (int) config('services.news_analyzer.fallback_max_attempts', 6));
        $errors = [];

        for ($i = 0; $i < $maxAttempts; $i++) {
            $entry = $chain[$i % count($chain)];
            $name = $entry['name'];
            $analyzer = $entry['analyzer'];

            try {
                return $analyzer->analyze($mode, $text, $url, $image);
            } catch (RuntimeException $e) {
                if (! $this->isQuotaOrRateLimitFailure($e)) {
                    throw $e;
                }

                $errors[] = $name.': '.$e->getMessage();

                Log::warning('News analyzer: provider hit quota/rate limit; trying next provider.', [
                    'provider' => $name,
                    'attempt' => $i + 1,
                    'max_attempts' => $maxAttempts,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        throw new RuntimeException(
            'All providers hit quota or rate limits after '.$maxAttempts.' attempts. '
            .implode(' | ', $errors)
        );
    }

    /**
     * @return list<array{name: string, analyzer: NewsAnalyzer}>
     */
    private function buildAlternatingChain(string $primaryDriver): array
    {
        $gemini = ['name' => 'gemini', 'analyzer' => $this->gemini];
        $groq = ['name' => 'groq', 'analyzer' => $this->groq];

        $order = $primaryDriver === 'groq'
            ? [$groq, $gemini]
            : [$gemini, $groq];

        return array_values(array_filter(
            $order,
            fn (array $p): bool => $this->hasApiKey($p['name'])
        ));
    }

    private function assertPrimaryProviderConfigured(string $driver): void
    {
        if ($driver === 'groq') {
            if (! $this->hasApiKey('groq')) {
                throw new RuntimeException('GROQ_API_KEY is not configured. Add it to your .env file (get a key at https://console.groq.com/).');
            }

            return;
        }

        if (! $this->hasApiKey('gemini')) {
            throw new RuntimeException('GEMINI_API_KEY is not configured. Add it to your .env file.');
        }
    }

    private function hasApiKey(string $name): bool
    {
        $key = $name === 'groq'
            ? config('services.groq.key')
            : config('services.gemini.key');

        return is_string($key) && $key !== '';
    }

    private function isQuotaOrRateLimitFailure(RuntimeException $e): bool
    {
        $m = strtolower($e->getMessage());

        return str_contains($m, '429')
            || str_contains($m, 'quota')
            || str_contains($m, 'rate limit')
            || str_contains($m, 'resource exhausted')
            || str_contains($m, 'too many requests');
    }

    private function isFallbackEnabled(): bool
    {
        $v = config('services.news_analyzer.fallback', true);

        return filter_var($v, FILTER_VALIDATE_BOOL);
    }

    private function throwMissingBackupKeyHint(string $driver, RuntimeException $previous): void
    {
        if ($driver === 'gemini' && ! $this->hasApiKey('groq')) {
            throw new RuntimeException(
                $previous->getMessage()
                .' Set GROQ_API_KEY in your .env (get a key at https://console.groq.com/keys) so the app can switch to Groq when Gemini hits a limit.',
                0,
                $previous
            );
        }

        if ($driver === 'groq' && ! $this->hasApiKey('gemini')) {
            throw new RuntimeException(
                $previous->getMessage()
                .' Set GEMINI_API_KEY in your .env so the app can switch to Gemini when Groq hits a limit.',
                0,
                $previous
            );
        }

        throw $previous;
    }
}
