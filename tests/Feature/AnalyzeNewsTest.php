<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyzeNewsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_validates_mode(): void
    {
        $response = $this->postJson('/analyze', []);

        $response->assertStatus(422);
    }

    public function test_analyze_requires_text_when_mode_is_text(): void
    {
        $response = $this->postJson('/analyze', [
            'mode' => 'text',
        ]);

        $response->assertStatus(422);
    }

    public function test_analyze_returns_503_when_gemini_not_configured(): void
    {
        config(['services.news_analyzer.driver' => 'gemini']);
        config(['services.gemini.key' => '']);

        $response = $this->postJson('/analyze', [
            'mode' => 'text',
            'text' => 'Example headline for testing.',
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath(
            'message',
            'GEMINI_API_KEY is not configured. Add it to your .env file.',
        );
    }

    public function test_analyze_returns_503_when_groq_not_configured(): void
    {
        config(['services.news_analyzer.driver' => 'groq']);
        config(['services.groq.key' => '']);

        $response = $this->postJson('/analyze', [
            'mode' => 'text',
            'text' => 'Example headline for testing.',
        ]);

        $response->assertStatus(503);
        $response->assertJsonPath(
            'message',
            'GROQ_API_KEY is not configured. Add it to your .env file (get a key at https://console.groq.com/).',
        );
    }
}
