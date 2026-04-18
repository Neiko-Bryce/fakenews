<?php

namespace Tests\Unit;

use App\Services\FallbackNewsAnalyzer;
use App\Services\GeminiNewsAnalyzer;
use App\Services\GroqNewsAnalyzer;
use RuntimeException;
use Tests\TestCase;

class FallbackNewsAnalyzerTest extends TestCase
{
    /** @return array<string, mixed> */
    private function sampleResult(): array
    {
        return [
            'verdict' => 'REAL',
            'verdict_hint' => 'ok',
            'confidence' => 80,
            'confidence_hint' => 'high',
            'real_percent' => 80,
            'fake_percent' => 20,
            'explanation' => 'Test.',
            'topics' => [],
        ];
    }

    public function test_uses_groq_when_gemini_throws_quota_error_and_both_keys_configured(): void
    {
        config(['services.news_analyzer.driver' => 'gemini']);
        config(['services.news_analyzer.fallback' => true]);
        config(['services.gemini.key' => 'gemini-key']);
        config(['services.groq.key' => 'sk-test']);

        $gemini = $this->createMock(GeminiNewsAnalyzer::class);
        $gemini->expects($this->once())->method('analyze')->willThrowException(
            new RuntimeException('Gemini quota exceeded (429): too many requests.')
        );

        $groq = $this->createMock(GroqNewsAnalyzer::class);
        $groq->expects($this->once())->method('analyze')->willReturn($this->sampleResult());

        $analyzer = new FallbackNewsAnalyzer($gemini, $groq);
        $result = $analyzer->analyze('text', 'hello', null, null);

        $this->assertSame('REAL', $result['verdict']);
    }

    public function test_cycles_back_to_gemini_after_groq_also_hits_quota(): void
    {
        config(['services.news_analyzer.driver' => 'gemini']);
        config(['services.news_analyzer.fallback' => true]);
        config(['services.gemini.key' => 'gemini-key']);
        config(['services.groq.key' => 'sk-test']);
        config(['services.news_analyzer.fallback_max_attempts' => 6]);

        $gemini = $this->createMock(GeminiNewsAnalyzer::class);
        $gemini->expects($this->exactly(2))->method('analyze')->willReturnCallback(function (): array {
            static $n = 0;
            $n++;
            if ($n === 1) {
                throw new RuntimeException('Gemini quota exceeded (429).');
            }

            return $this->sampleResult();
        });

        $groq = $this->createMock(GroqNewsAnalyzer::class);
        $groq->expects($this->once())->method('analyze')->willThrowException(
            new RuntimeException('Groq rate limit (429).')
        );

        $analyzer = new FallbackNewsAnalyzer($gemini, $groq);
        $result = $analyzer->analyze('text', 'hello', null, null);

        $this->assertSame('REAL', $result['verdict']);
    }

    public function test_does_not_cycle_when_gemini_error_is_not_quota_related(): void
    {
        config(['services.news_analyzer.driver' => 'gemini']);
        config(['services.news_analyzer.fallback' => true]);
        config(['services.gemini.key' => 'gemini-key']);
        config(['services.groq.key' => 'sk-test']);

        $gemini = $this->createMock(GeminiNewsAnalyzer::class);
        $gemini->expects($this->once())->method('analyze')->willThrowException(
            new RuntimeException('Empty AI response.')
        );

        $groq = $this->createMock(GroqNewsAnalyzer::class);
        $groq->expects($this->never())->method('analyze');

        $analyzer = new FallbackNewsAnalyzer($gemini, $groq);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Empty AI response.');

        $analyzer->analyze('text', 'hello', null, null);
    }

    public function test_single_provider_only_when_other_key_missing(): void
    {
        config(['services.news_analyzer.driver' => 'gemini']);
        config(['services.news_analyzer.fallback' => true]);
        config(['services.gemini.key' => 'gemini-key']);
        config(['services.groq.key' => '']);

        $gemini = $this->createMock(GeminiNewsAnalyzer::class);
        $gemini->expects($this->once())->method('analyze')->willThrowException(
            new RuntimeException('Gemini quota exceeded (429).')
        );

        $groq = $this->createMock(GroqNewsAnalyzer::class);
        $groq->expects($this->never())->method('analyze');

        $analyzer = new FallbackNewsAnalyzer($gemini, $groq);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Gemini quota exceeded (429).');

        $analyzer->analyze('text', 'hello', null, null);
    }

    public function test_fallback_disabled_uses_only_primary_even_with_two_keys(): void
    {
        config(['services.news_analyzer.driver' => 'gemini']);
        config(['services.news_analyzer.fallback' => false]);
        config(['services.gemini.key' => 'gemini-key']);
        config(['services.groq.key' => 'sk-test']);

        $gemini = $this->createMock(GeminiNewsAnalyzer::class);
        $gemini->expects($this->once())->method('analyze')->willThrowException(
            new RuntimeException('Gemini quota exceeded (429).')
        );

        $groq = $this->createMock(GroqNewsAnalyzer::class);
        $groq->expects($this->never())->method('analyze');

        $analyzer = new FallbackNewsAnalyzer($gemini, $groq);

        $this->expectException(RuntimeException::class);
        $analyzer->analyze('text', 'hello', null, null);
    }

    public function test_uses_gemini_when_groq_primary_throws_rate_limit_and_both_keys(): void
    {
        config(['services.news_analyzer.driver' => 'groq']);
        config(['services.news_analyzer.fallback' => true]);
        config(['services.gemini.key' => 'gemini-key']);
        config(['services.groq.key' => 'sk-test']);

        $groq = $this->createMock(GroqNewsAnalyzer::class);
        $groq->expects($this->once())->method('analyze')->willThrowException(
            new RuntimeException('Groq rate limit (429). Wait a few minutes.')
        );

        $gemini = $this->createMock(GeminiNewsAnalyzer::class);
        $gemini->expects($this->once())->method('analyze')->willReturn($this->sampleResult());

        $analyzer = new FallbackNewsAnalyzer($gemini, $groq);
        $result = $analyzer->analyze('text', 'hello', null, null);

        $this->assertSame('REAL', $result['verdict']);
    }

    public function test_throws_after_max_attempts_when_all_quota_errors(): void
    {
        config(['services.news_analyzer.driver' => 'gemini']);
        config(['services.news_analyzer.fallback' => true]);
        config(['services.gemini.key' => 'gemini-key']);
        config(['services.groq.key' => 'sk-test']);
        config(['services.news_analyzer.fallback_max_attempts' => 4]);

        $gemini = $this->createMock(GeminiNewsAnalyzer::class);
        $gemini->expects($this->exactly(2))->method('analyze')->willThrowException(
            new RuntimeException('Gemini quota exceeded (429).')
        );

        $groq = $this->createMock(GroqNewsAnalyzer::class);
        $groq->expects($this->exactly(2))->method('analyze')->willThrowException(
            new RuntimeException('Groq rate limit (429).')
        );

        $analyzer = new FallbackNewsAnalyzer($gemini, $groq);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('All providers hit quota or rate limits after 4 attempts');

        $analyzer->analyze('text', 'hello', null, null);
    }
}
