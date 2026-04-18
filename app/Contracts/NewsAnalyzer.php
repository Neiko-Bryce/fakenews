<?php

namespace App\Contracts;

use Illuminate\Http\UploadedFile;

interface NewsAnalyzer
{
    /**
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
    public function analyze(string $mode, ?string $text, ?string $url, ?UploadedFile $image): array;
}
