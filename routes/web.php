<?php

use App\Http\Controllers\Admin\AnalysisLogController;
use App\Http\Controllers\Admin\LandingContentController;
use App\Http\Controllers\AnalyzeNewsController;
use App\Services\LandingContentService;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function (LandingContentService $landing) {
    return Inertia::render('welcome', [
        'analyzerConfigured' => match (config('services.news_analyzer.driver', 'gemini')) {
            'groq' => filled(config('services.groq.key')),
            default => filled(config('services.gemini.key')),
        },
        'analyzerDriver' => config('services.news_analyzer.driver', 'gemini'),
        'landing' => $landing->resolve(),
    ]);
})->name('home');

Route::post('/analyze', AnalyzeNewsController::class)
    ->middleware('throttle:20,1')
    ->name('analyze');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        return Inertia::render('dashboard', [
            'analyzerDriver' => config('services.news_analyzer.driver', 'gemini'),
            'analyzerConfigured' => match (config('services.news_analyzer.driver', 'gemini')) {
                'groq' => filled(config('services.groq.key')),
                default => filled(config('services.gemini.key')),
            },
        ]);
    })->name('dashboard');
});

Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('logs', [AnalysisLogController::class, 'index'])->name('logs.index');
    Route::delete('logs/{log}', [AnalysisLogController::class, 'destroy'])->name('logs.destroy');
    Route::get('landing', [LandingContentController::class, 'edit'])->name('landing.edit');
    Route::put('landing', [LandingContentController::class, 'update'])->name('landing.update');
    Route::post('landing/reset', [LandingContentController::class, 'reset'])->name('landing.reset');
});

require __DIR__.'/settings.php';
