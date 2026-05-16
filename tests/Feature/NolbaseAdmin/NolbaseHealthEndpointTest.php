<?php

use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Contracts\Redis\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
});

/**
 * Stub the `redis` container binding with an object that accepts any method
 * call. The health controller's Redis::connection()->ping() resolves through
 * this binding. The third constructor arg sets the ping return value; with
 * a RuntimeException it simulates a Redis outage.
 */
function bindRedisStub(?Throwable $pingThrows = null, mixed $pingReturns = true): void
{
    app()->instance('redis', new class($pingThrows, $pingReturns) implements Factory
    {
        public function __construct(private ?Throwable $throws, private mixed $returns) {}

        public function connection($name = null)
        {
            return $this;
        }

        public function ping(): mixed
        {
            if ($this->throws) {
                throw $this->throws;
            }

            return $this->returns;
        }

        public function __call(string $name, array $args): mixed
        {
            return null;
        }
    });
}

test('returns 200 with healthy status when db and redis respond', function () {
    bindRedisStub();

    $response = $this->getJson('/api/nolbase/health');

    $response->assertStatus(200)
        ->assertJson(['status' => 'healthy'])
        ->assertJsonStructure([
            'status',
            'checked_at',
            'version' => ['commit'],
            'checks' => [
                'db' => ['ok', 'latency_ms'],
                'redis' => ['ok', 'latency_ms'],
            ],
        ]);

    expect($response->json('checks.db.ok'))->toBeTrue();
    expect($response->json('checks.redis.ok'))->toBeTrue();
});

test('omits paystack check by default', function () {
    bindRedisStub();

    $response = $this->getJson('/api/nolbase/health');

    expect($response->json('checks'))->not->toHaveKey('paystack');
});

test('?deep=1 includes a paystack reachability probe', function () {
    bindRedisStub();
    config()->set('paystack.secret_key', 'sk_test_xyz');
    Http::fake([
        'api.paystack.co/bank*' => Http::response(['status' => true, 'data' => []], 200),
    ]);

    $response = $this->getJson('/api/nolbase/health?deep=1');

    $response->assertStatus(200);
    expect($response->json('checks.paystack.ok'))->toBeTrue()
        ->and($response->json('checks.paystack.http_status'))->toBe(200);
});

test('paystack downtime does NOT degrade the health endpoint', function () {
    bindRedisStub();
    config()->set('paystack.secret_key', 'sk_test_xyz');
    Http::fake([
        'api.paystack.co/bank*' => Http::response(['status' => false], 502),
    ]);

    $response = $this->getJson('/api/nolbase/health?deep=1');

    $response->assertStatus(200) // db + redis still healthy
        ->assertJson(['status' => 'healthy']);
    expect($response->json('checks.paystack.ok'))->toBeFalse();
});

test('missing paystack secret is reported in the deep probe', function () {
    bindRedisStub();
    config()->set('paystack.secret_key', null);

    $response = $this->getJson('/api/nolbase/health?deep=1');

    expect($response->json('checks.paystack.ok'))->toBeFalse()
        ->and($response->json('checks.paystack.error'))->toContain('PAYSTACK_SECRET_KEY');
});

test('redis failure flips the endpoint to 503 degraded', function () {
    bindRedisStub(pingThrows: new RuntimeException('redis unreachable'));

    $response = $this->getJson('/api/nolbase/health');

    $response->assertStatus(503)
        ->assertJson(['status' => 'degraded']);
    expect($response->json('checks.redis.ok'))->toBeFalse()
        ->and($response->json('checks.db.ok'))->toBeTrue();
});

test('non-PONG redis reply is treated as failure', function () {
    bindRedisStub(pingReturns: 'WAT');

    $response = $this->getJson('/api/nolbase/health');

    $response->assertStatus(503);
    expect($response->json('checks.redis.ok'))->toBeFalse();
});
