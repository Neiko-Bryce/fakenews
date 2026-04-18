<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AnalysisLog;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AnalysisLogController extends Controller
{
    public function index(): Response
    {
        $logs = AnalysisLog::query()
            ->with(['user:id,name,email'])
            ->latest()
            ->paginate(25)
            ->through(static function (AnalysisLog $log): array {
                return [
                    'id' => $log->id,
                    'created_at' => $log->created_at?->toIso8601String(),
                    'mode' => $log->mode,
                    'status' => $log->status,
                    'http_status' => $log->http_status,
                    'has_image' => $log->has_image,
                    'image_bytes' => $log->image_bytes,
                    'image_mime' => $log->image_mime,
                    'image_client_name' => $log->image_client_name,
                    'text_length' => $log->text_length,
                    'has_url' => $log->has_url,
                    'ip_address' => $log->ip_address,
                    'user' => $log->user
                        ? [
                            'name' => $log->user->name,
                            'email' => $log->user->email,
                        ]
                        : null,
                    'error_message' => $log->error_message,
                    'analysis_result' => $log->analysis_result,
                ];
            });

        return Inertia::render('admin/logs', [
            'logs' => $logs,
        ]);
    }

    public function destroy(AnalysisLog $log): RedirectResponse
    {
        $log->delete();

        return back();
    }
}
