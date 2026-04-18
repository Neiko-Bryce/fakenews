<?php

namespace Tests\Unit;

use App\Services\Concerns\NormalizesNewsAnalysisResult;
use Tests\TestCase;

class NormalizesNewsAnalysisResultTest extends TestCase
{
    public function test_parses_raw_json(): void
    {
        $t = $this->traitTester();
        $out = $t->parsePublic('{"verdict":"UNCERTAIN","verdict_hint":"x","confidence":50,"confidence_hint":"m","real_percent":50,"fake_percent":50,"explanation":"e","related_topics":[]}');
        $this->assertSame('UNCERTAIN', $out['verdict']);
    }

    public function test_parses_json_in_markdown_fence(): void
    {
        $t = $this->traitTester();
        $raw = "Here you go:\n```json\n{\"verdict\":\"REAL\",\"verdict_hint\":\"x\",\"confidence\":80,\"confidence_hint\":\"m\",\"real_percent\":80,\"fake_percent\":20,\"explanation\":\"ok\",\"related_topics\":[]}\n```\n";
        $out = $t->parsePublic($raw);
        $this->assertSame('REAL', $out['verdict']);
    }

    public function test_parses_json_after_prose(): void
    {
        $t = $this->traitTester();
        $json = '{"verdict":"FAKE","verdict_hint":"x","confidence":60,"confidence_hint":"m","real_percent":40,"fake_percent":60,"explanation":"e","related_topics":[]}';
        $raw = "Analysis follows.\n\n".$json."\n\nThanks.";
        $out = $t->parsePublic($raw);
        $this->assertSame('FAKE', $out['verdict']);
    }

    private function traitTester(): object
    {
        return new class
        {
            use NormalizesNewsAnalysisResult;

            /** @return array<string, mixed> */
            public function parsePublic(string $text): array
            {
                return $this->normalizeResult($this->parseJsonObject($text));
            }
        };
    }
}
