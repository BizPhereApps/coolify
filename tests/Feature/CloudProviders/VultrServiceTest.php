<?php

use App\Services\VultrService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('getRegions returns the regions array from the API', function () {
    Http::fake([
        'api.vultr.com/v2/regions*' => Http::response([
            'regions' => [
                ['id' => 'ewr', 'city' => 'New Jersey', 'country' => 'US'],
                ['id' => 'fra', 'city' => 'Frankfurt', 'country' => 'DE'],
            ],
            'meta' => ['total' => 2, 'links' => new \stdClass],
        ]),
    ]);

    $regions = (new VultrService('vlt_token'))->getRegions();

    expect($regions)->toHaveCount(2)
        ->and($regions[0]['id'])->toBe('ewr');
});

test('createInstance returns the instance payload', function () {
    Http::fake([
        'api.vultr.com/v2/instances' => Http::response([
            'instance' => [
                'id' => 'inst_abc',
                'label' => 'nolbase-test',
                'main_ip' => '203.0.113.20',
            ],
        ]),
    ]);

    $instance = (new VultrService('vlt_token'))->createInstance([
        'region' => 'ewr', 'plan' => 'vc2-1c-1gb', 'os_id' => 1743,
    ]);

    expect($instance['id'])->toBe('inst_abc')
        ->and($instance['main_ip'])->toBe('203.0.113.20');
});

test('findPublicIpv4 returns null when main_ip is 0.0.0.0 (not yet assigned)', function () {
    $service = new VultrService('vlt_token');
    expect($service->findPublicIpv4(['main_ip' => '0.0.0.0']))->toBeNull()
        ->and($service->findPublicIpv4(['main_ip' => '203.0.113.20']))->toBe('203.0.113.20')
        ->and($service->findPublicIpv4([]))->toBeNull();
});

test('a Vultr API error throws RuntimeException with the error message', function () {
    Http::fake([
        'api.vultr.com/v2/instances' => Http::response([
            'error' => 'invalid plan',
        ], 400),
    ]);

    expect(fn () => (new VultrService('vlt_token'))->createInstance(['plan' => 'bad']))
        ->toThrow(\RuntimeException::class, 'invalid plan');
});

test('an authenticated request includes the bearer token', function () {
    Http::fake([
        'api.vultr.com/v2/ssh-keys*' => Http::response(['ssh_keys' => [], 'meta' => ['links' => new \stdClass]]),
    ]);

    (new VultrService('vlt_test_token'))->getSshKeys();

    Http::assertSent(fn ($request) =>
        $request->hasHeader('Authorization', 'Bearer vlt_test_token')
        && str_contains($request->url(), '/ssh-keys')
    );
});
