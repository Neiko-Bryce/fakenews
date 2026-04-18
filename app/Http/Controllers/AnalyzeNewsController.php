<?php

namespace App\Http\Controllers;

use App\Contracts\NewsAnalyzer;
use App\Http\Requests\AnalyzeNewsRequest;
use App\Models\AnalysisLog;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class AnalyzeNewsController extends Controller
{
    public function __invoke(AnalyzeNewsRequest $request, NewsAnalyzer $analyzer)
    {
        $validated = $request->validated();
        $image = $request->file('image');

        $baseLog = [
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'mode' => (string) $validated['mode'],
            'has_image' => $image !== null,
            'image_bytes' => $image?->getSize(),
            'image_mime' => $image?->getMimeType(),
            'image_client_name' => $image?->getClientOriginalName(),
            'text_length' => isset($validated['text']) ? strlen((string) $validated['text']) : null,
            'has_url' => isset($validated['url']) && (string) $validated['url'] !== '',
        ];

        Log::info('News analysis requested from landing page', $baseLog);

        try {
            $result = $analyzer->analyze(
                (string) $validated['mode'],
                isset($validated['text']) ? (string) $validated['text'] : null,
                isset($validated['url']) ? (string) $validated['url'] : null,
                $request->file('image'),
            );

            $this->persistAnalysisLog(array_merge($baseLog, [
                'status' => 'success',
                'http_status' => 200,
                'error_message' => null,
                'analysis_result' => $result,
            ]));

            return response()->json([
                'result' => $result,
            ]);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'API_KEY is not configured')
                ? 503
                : 502;

            $this->persistAnalysisLog(array_merge($baseLog, [
                'status' => 'error',
                'http_status' => $status,
                'error_message' => $e->getMessage(),
            ]));

            return response()->json([
                'message' => $e->getMessage(),
            ], $status);
        } catch (Throwable $e) {
            report($e);

            $this->persistAnalysisLog(array_merge($baseLog, [
                'status' => 'error',
                'http_status' => 500,
                'error_message' => 'Analysis failed. Please try again later.',
            ]));

            return response()->json([
                'message' => 'Analysis failed. Please try again later.',
            ], 500);
        }
    }

    /**
     * Never break the analyze response if logging fails (e.g. migration not run yet).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function persistAnalysisLog(array $attributes): void
    {
        try {
            AnalysisLog::query()->create($attributes);
        } catch (Throwable $e) {
            report($e);
            Log::warning('Could not persist analysis_logs row', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
