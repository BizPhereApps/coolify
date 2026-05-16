<?php

use App\Models\NolbaseSetting;
use App\Services\NolbaseAlert;
use Database\Seeders\InstanceSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
});

test('no-op when no webhook url is configured', function () {
    Http::fake();

    NolbaseAlert::send('Title', 'msg', NolbaseAlert::LEVEL_INFO, ['foo' => 'bar']);

    Http::assertNothingSent();
});

test('posts a slack-shaped payload to the configured webhook', function () {
    NolbaseSetting::write('nolbase_alerts_webhook_url', 'https://hooks.slack.com/services/T/B/X');
    Http::fake(['hooks.slack.com/*' => Http::response('ok', 200)]);

    NolbaseAlert::send(
        'Payment failed',
        'Charge declined for team #42',
        NolbaseAlert::LEVEL_WARN,
        ['team_id' => 42, 'amount_ngn' => 12_750],
    );

    Http::assertSent(function ($request) {
        $body = $request->data();

        return $request->url() === 'https://hooks.slack.com/services/T/B/X'
            && str_contains((string) $body['text'], 'Payment failed')
            && $body['attachments'][0]['color'] === '#f0b400'
            && $body['attachments'][0]['title'] === 'Payment failed'
            && collect($body['attachments'][0]['fields'])->contains(fn ($f) => $f['title'] === 'team_id' && $f['value'] === '42');
    });
});

test('critical level renders red', function () {
    NolbaseSetting::write('nolbase_alerts_webhook_url', 'https://hooks.slack.com/x');
    Http::fake();

    NolbaseAlert::send('Outage', 'DB is down', NolbaseAlert::LEVEL_CRITICAL);

    Http::assertSent(fn ($r) => $r->data()['attachments'][0]['color'] === '#cc1f1a');
});

test('http failure is swallowed and never bubbles', function () {
    NolbaseSetting::write('nolbase_alerts_webhook_url', 'https://hooks.slack.com/down');
    Http::fake(['hooks.slack.com/*' => Http::response(null, 500)]);

    // Should not throw — fire-and-forget.
    NolbaseAlert::send('x', 'y');
    expect(true)->toBeTrue();
});

test('NolbaseSetting takes precedence over env', function () {
    NolbaseSetting::write('nolbase_alerts_webhook_url', 'https://hooks.slack.com/from-db');
    Http::fake();

    NolbaseAlert::send('x', 'y');

    Http::assertSent(fn ($r) => $r->url() === 'https://hooks.slack.com/from-db');
});
