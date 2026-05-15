<?php

use App\Services\DigitalOceanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('getRegions returns the regions array from the API', function () {
    Http::fake([
        'api.digitalocean.com/v2/regions*' => Http::response([
            'regions' => [
                ['slug' => 'nyc3', 'name' => 'New York 3', 'available' => true],
                ['slug' => 'fra1', 'name' => 'Frankfurt 1', 'available' => true],
            ],
            'links' => new \stdClass,
        ]),
    ]);

    $service = new DigitalOceanService('dop_token');
    $regions = $service->getRegions();

    expect($regions)->toHaveCount(2)
        ->and($regions[0]['slug'])->toBe('nyc3');
});

test('createDroplet returns the droplet payload', function () {
    Http::fake([
        'api.digitalocean.com/v2/droplets' => Http::response([
            'droplet' => [
                'id' => 12345,
                'name' => 'nolbase-test',
                'networks' => ['v4' => [['type' => 'public', 'ip_address' => '203.0.113.10']]],
            ],
        ]),
    ]);

    $droplet = (new DigitalOceanService('dop_token'))->createDroplet([
        'name' => 'nolbase-test', 'region' => 'nyc3', 'size' => 's-1vcpu-1gb', 'image' => 'ubuntu-22-04-x64',
    ]);

    expect($droplet['id'])->toBe(12345)
        ->and($droplet['name'])->toBe('nolbase-test');
});

test('findPublicIpv4 extracts the public IPv4 from networks', function () {
    $service = new DigitalOceanService('dop_token');
    $droplet = [
        'networks' => [
            'v4' => [
                ['type' => 'private', 'ip_address' => '10.0.0.5'],
                ['type' => 'public', 'ip_address' => '203.0.113.10'],
            ],
        ],
    ];

    expect($service->findPublicIpv4($droplet))->toBe('203.0.113.10');
});

test('findPublicIpv4 returns null when no public IP is assigned yet', function () {
    $service = new DigitalOceanService('dop_token');
    expect($service->findPublicIpv4(['networks' => ['v4' => []]]))->toBeNull();
});

test('an API error throws RuntimeException with the DO message', function () {
    Http::fake([
        'api.digitalocean.com/v2/droplets' => Http::response([
            'message' => 'Insufficient quota',
        ], 422),
    ]);

    expect(fn () => (new DigitalOceanService('dop_token'))->createDroplet(['name' => 'x']))
        ->toThrow(\RuntimeException::class, 'Insufficient quota');
});

test('an authenticated request includes the bearer token', function () {
    Http::fake([
        'api.digitalocean.com/v2/account/keys*' => Http::response(['ssh_keys' => [], 'links' => new \stdClass]),
    ]);

    (new DigitalOceanService('dop_test_token'))->getSshKeys();

    Http::assertSent(fn ($request) =>
        $request->hasHeader('Authorization', 'Bearer dop_test_token')
        && str_contains($request->url(), '/account/keys')
    );
});
