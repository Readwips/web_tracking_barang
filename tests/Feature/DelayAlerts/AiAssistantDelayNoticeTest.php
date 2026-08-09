<?php

namespace Tests\Feature\DelayAlerts;

use App\Services\AiAssistantService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

class AiAssistantDelayNoticeTest extends DelayAlertTestCase
{
    public function test_missing_ai_key_uses_deterministic_fallback_without_network_access(): void
    {
        config(['services.openai.key' => null]);
        Http::preventStrayRequests();

        $message = app(AiAssistantService::class)->delayedShipmentNotice();

        Http::assertNothingSent();
        $this->assertStringContainsString('mengalami keterlambatan', $message);
        $this->assertStringNotContainsString('BOOK-2026-000124', $message);
        $this->assertStringNotContainsString('TANTO-CT-000124', $message);
        $this->assertStringNotContainsString('```', $message);
    }

    public function test_ai_http_failure_also_falls_back_to_the_safe_notice(): void
    {
        config(['services.openai.key' => 'test-api-key']);
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => ['message' => 'service unavailable'],
            ], 503),
        ]);

        $message = app(AiAssistantService::class)->delayedShipmentNotice();

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.openai.com/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-api-key')
            && ! str_contains($request->body(), 'BOOK-2026-000124')
            && ! str_contains($request->body(), 'TANTO-CT-000124'));
        $this->assertStringContainsString('mengalami keterlambatan', $message);
        $this->assertStringNotContainsString('BOOK-2026-000124', $message);
        $this->assertStringNotContainsString('TANTO-CT-000124', $message);
    }
}
