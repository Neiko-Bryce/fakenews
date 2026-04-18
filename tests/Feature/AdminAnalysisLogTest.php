<?php

namespace Tests\Feature;

use App\Models\AnalysisLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminAnalysisLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_admin_logs(): void
    {
        $this->get('/admin/logs')->assertRedirect(route('login'));
    }

    public function test_non_admin_cannot_view_admin_logs(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/logs')->assertForbidden();
    }

    public function test_admin_can_view_analysis_activity_page(): void
    {
        $user = User::factory()->admin()->create();

        AnalysisLog::query()->create([
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'mode' => 'text',
            'has_image' => false,
            'image_bytes' => null,
            'image_mime' => null,
            'image_client_name' => null,
            'text_length' => 12,
            'has_url' => false,
            'status' => 'success',
            'http_status' => 200,
            'error_message' => null,
            'analysis_result' => [
                'verdict' => 'REAL',
                'verdict_hint' => 'Likely reliable',
                'confidence' => 82,
                'confidence_hint' => 'High',
                'real_percent' => 82,
                'fake_percent' => 18,
                'explanation' => 'Sources align with known reporting.',
                'topics' => ['news'],
            ],
        ]);

        $response = $this->actingAs($user)->get('/admin/logs');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('admin/logs')
            ->has('logs.data', 1)
            ->where('logs.data.0.mode', 'text')
            ->where('logs.data.0.status', 'success')
            ->where('logs.data.0.analysis_result.verdict', 'REAL')
            ->where('logs.data.0.analysis_result.confidence', 82)
        );
    }

    public function test_guest_cannot_delete_analysis_log(): void
    {
        $log = AnalysisLog::query()->create([
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'mode' => 'text',
            'has_image' => false,
            'image_bytes' => null,
            'image_mime' => null,
            'image_client_name' => null,
            'text_length' => 12,
            'has_url' => false,
            'status' => 'success',
            'http_status' => 200,
            'error_message' => null,
        ]);

        $this->delete("/admin/logs/{$log->id}")->assertRedirect(route('login'));

        $this->assertDatabaseHas('analysis_logs', ['id' => $log->id]);
    }

    public function test_non_admin_cannot_delete_analysis_log(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $log = AnalysisLog::query()->create([
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'mode' => 'text',
            'has_image' => false,
            'image_bytes' => null,
            'image_mime' => null,
            'image_client_name' => null,
            'text_length' => 12,
            'has_url' => false,
            'status' => 'success',
            'http_status' => 200,
            'error_message' => null,
        ]);

        $this->actingAs($user)->delete("/admin/logs/{$log->id}")->assertForbidden();

        $this->assertDatabaseHas('analysis_logs', ['id' => $log->id]);
    }

    public function test_admin_can_delete_analysis_log(): void
    {
        $user = User::factory()->admin()->create();

        $log = AnalysisLog::query()->create([
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'mode' => 'text',
            'has_image' => false,
            'image_bytes' => null,
            'image_mime' => null,
            'image_client_name' => null,
            'text_length' => 12,
            'has_url' => false,
            'status' => 'success',
            'http_status' => 200,
            'error_message' => null,
        ]);

        $this->actingAs($user)->from('/admin/logs')->delete("/admin/logs/{$log->id}")->assertRedirect('/admin/logs');

        $this->assertDatabaseMissing('analysis_logs', ['id' => $log->id]);
    }
}
