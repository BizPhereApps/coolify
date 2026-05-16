<?php

use App\Actions\Nolbase\DeregisterNolbaseSubdomain;
use App\Actions\Nolbase\RegisterNolbaseSubdomain;
use App\Models\Application;
use App\Models\Environment;
use App\Models\InstanceSettings;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
use App\Models\Team;
use App\Rules\ValidNolbaseSubdomain;
use App\Services\NolbaseDnsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(function () {
    InstanceSettings::unguarded(fn () => InstanceSettings::firstOrCreate(['id' => 0]));

    $this->team = Team::factory()->create();
    $this->server = Server::factory()->create(['team_id' => $this->team->id, 'ip' => '1.2.3.4']);
    $this->destination = StandaloneDocker::where('server_id', $this->server->id)->first();
    $this->project = Project::factory()->create(['team_id' => $this->team->id]);
    $this->environment = Environment::factory()->create(['project_id' => $this->project->id]);
    $this->application = Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => $this->destination->getMorphClass(),
        'fqdn' => null,
    ]);

    // Stub DNS service so tests never call real Cloudflare
    $dns = new class extends NolbaseDnsService
    {
        public function __construct() {}

        public function isConfigured(): bool
        {
            return true;
        }

        public function createARecord(string $slug, string $ip): string
        {
            return "cf-record-{$slug}";
        }

        public function deleteRecord(string $recordId): void {}

        public function recordExists(string $slug): bool
        {
            return false;
        }

        public function fqdn(string $slug): string
        {
            return "https://{$slug}.nolbase.app";
        }
    };

    app()->instance(NolbaseDnsService::class, $dns);
});

// ── ValidNolbaseSubdomain rule ────────────────────────────────────────────────

test('valid slugs pass the rule', function (string $slug) {
    $result = Validator::make(['s' => $slug], ['s' => new ValidNolbaseSubdomain])->passes();
    expect($result)->toBeTrue();
})->with(['my-app', 'hello123', 'test-app', 'a1b2c3']);

test('invalid slugs fail the rule', function (string $slug) {
    $result = Validator::make(['s' => $slug], ['s' => new ValidNolbaseSubdomain])->passes();
    expect($result)->toBeFalse();
})->with(['-leading', 'trailing-', 'AB', 'a', str_repeat('x', 64), 'has spaces']);

test('reserved slugs are rejected', function (string $slug) {
    $result = Validator::make(['s' => $slug], ['s' => new ValidNolbaseSubdomain])->passes();
    expect($result)->toBeFalse();
})->with(['www', 'app', 'admin', 'api', 'nolbase']);

// ── RegisterNolbaseSubdomain ──────────────────────────────────────────────────

test('registers subdomain and saves cloudflare record id', function () {
    $result = RegisterNolbaseSubdomain::run($this->application, 'my-app');

    expect($result)->toMatchArray([
        'fqdn' => 'https://my-app.nolbase.app',
        'record_id' => 'cf-record-my-app',
    ]);

    $this->application->refresh();
    expect($this->application->nolbase_subdomain)->toBe('my-app');
    expect($this->application->nolbase_dns_record_id)->toBe('cf-record-my-app');
    expect($this->application->fqdn)->toContain('https://my-app.nolbase.app');
});

test('rejects a slug already taken by another application', function () {
    Application::factory()->create([
        'environment_id' => $this->environment->id,
        'destination_id' => $this->destination->id,
        'destination_type' => $this->destination->getMorphClass(),
        'nolbase_subdomain' => 'taken-slug',
    ]);

    expect(fn () => RegisterNolbaseSubdomain::run($this->application, 'taken-slug'))
        ->toThrow(RuntimeException::class, 'already taken');
});

test('changing slug deregisters old and registers new', function () {
    RegisterNolbaseSubdomain::run($this->application, 'first-slug');
    RegisterNolbaseSubdomain::run($this->application, 'second-slug');

    $this->application->refresh();
    expect($this->application->nolbase_subdomain)->toBe('second-slug');
    expect($this->application->fqdn)->toContain('second-slug.nolbase.app');
    expect($this->application->fqdn)->not->toContain('first-slug.nolbase.app');
});

test('preserves existing custom fqdn when adding subdomain', function () {
    $this->application->forceFill(['fqdn' => 'https://custom.example.com'])->save();

    RegisterNolbaseSubdomain::run($this->application, 'my-app');

    $this->application->refresh();
    expect($this->application->fqdn)->toContain('https://custom.example.com');
    expect($this->application->fqdn)->toContain('https://my-app.nolbase.app');
});

// ── DeregisterNolbaseSubdomain ────────────────────────────────────────────────

test('deregister clears subdomain columns and strips nolbase fqdn', function () {
    RegisterNolbaseSubdomain::run($this->application, 'my-app');
    DeregisterNolbaseSubdomain::run($this->application);

    $this->application->refresh();
    expect($this->application->nolbase_subdomain)->toBeNull();
    expect($this->application->nolbase_dns_record_id)->toBeNull();
    expect((string) $this->application->fqdn)->not->toContain('nolbase.app');
});

test('deregister is a no-op when no subdomain is set', function () {
    expect(fn () => DeregisterNolbaseSubdomain::run($this->application))->not->toThrow(Throwable::class);
});

test('deregister preserves other custom domains', function () {
    $this->application->forceFill(['fqdn' => 'https://custom.example.com'])->save();
    RegisterNolbaseSubdomain::run($this->application, 'my-app');

    DeregisterNolbaseSubdomain::run($this->application);

    $this->application->refresh();
    expect($this->application->fqdn)->toBe('https://custom.example.com');
});

// ── Application deletion cleans up DNS ───────────────────────────────────────

test('force deleting an application deregisters its subdomain', function () {
    RegisterNolbaseSubdomain::run($this->application, 'delete-me');

    $this->application->forceDelete();

    expect(Application::withTrashed()->find($this->application->id))->toBeNull();
});
