<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\EnvironmentVariable;
use App\Models\HostingOffer;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Server;
use App\Models\SubTeam;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('constants.coolify.self_hosted', false);
});

function clientWithApp(): array
{
    $devTeam = Team::factory()->create();
    Subscription::create([
        'team_id' => $devTeam->id, 'plan_id' => Plan::pro()->id,
        'status' => Subscription::STATUS_ACTIVE, 'period' => Subscription::PERIOD_MONTHLY,
    ]);
    $server = Server::factory()->create(['team_id' => $devTeam->id]);
    $offer = HostingOffer::create([
        'team_id' => $devTeam->id, 'server_id' => $server->id,
        'name' => 'Starter', 'ram_mb' => 512, 'disk_gb' => 5,
        'max_apps' => 1, 'max_databases' => 0, 'price_ngn_monthly' => 10000,
    ]);

    $client = User::factory()->create();
    $project = Project::create([
        'name' => "Bola's Shop",
        'team_id' => $devTeam->id,
    ]);
    // Project::created auto-creates a 'production' environment.
    $environment = Environment::where('project_id', $project->id)->first();

    $subTeam = SubTeam::create([
        'parent_team_id' => $devTeam->id,
        'client_user_id' => $client->id,
        'hosting_offer_id' => $offer->id,
        'project_id' => $project->id,
    ]);

    $application = Application::factory()->create([
        'name' => 'bola-shop-api',
        'environment_id' => $environment->id,
        'status' => 'running:healthy',
        'git_repository' => 'https://github.com/bola/shop.git',
        'git_branch' => 'main',
    ]);

    return [$client, $subTeam, $application];
}

test('Client dashboard lists applications in the SubTeams project', function () {
    [$client, $subTeam, $app] = clientWithApp();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Dashboard::class)
        ->assertSee('bola-shop-api')
        ->assertSee('running:healthy');
});

test('Client application Show 403s on cross-project access', function () {
    [$client, $subTeam, $app] = clientWithApp();

    // Build a second Developer + Project + Application — unrelated to this Client.
    $other = Team::factory()->create();
    $otherProject = Project::create(['name' => 'Other', 'team_id' => $other->id]);
    $otherEnv = Environment::where('project_id', $otherProject->id)->first();
    $otherApp = Application::factory()->create([
        'name' => 'stolen', 'environment_id' => $otherEnv->id, 'status' => 'unknown',
    ]);

    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $otherApp->uuid])
        ->assertStatus(403);
});

test('Client can add and edit env vars on their app', function () {
    [$client, $subTeam, $app] = clientWithApp();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->set('newEnvKey', 'DATABASE_URL')
        ->set('newEnvValue', 'postgres://x')
        ->call('addEnv');

    $env = EnvironmentVariable::where('resourceable_type', Application::class)
        ->where('resourceable_id', $app->id)
        ->where('key', 'DATABASE_URL')
        ->first();
    expect($env)->not->toBeNull()
        ->and($env->value)->toBe('postgres://x');

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->set("envEdits.{$env->id}", 'postgres://updated')
        ->call('saveEnv', $env->id);
    expect($env->fresh()->value)->toBe('postgres://updated');
});

test('Client cannot create env vars with disallowed key format', function () {
    [$client, $subTeam, $app] = clientWithApp();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->set('newEnvKey', 'lowercase-key')
        ->set('newEnvValue', 'x')
        ->call('addEnv')
        ->assertHasErrors('newEnvKey');

    expect(EnvironmentVariable::where('resourceable_id', $app->id)->where('key', 'lowercase-key')->count())->toBe(0);
});

test('Client cannot edit env vars on a different application', function () {
    [$client, $subTeam, $app] = clientWithApp();
    $other = Team::factory()->create();
    $otherProject = Project::create(['name' => 'Other', 'team_id' => $other->id]);
    $otherEnv = Environment::where('project_id', $otherProject->id)->first();
    $otherApp = Application::factory()->create([
        'name' => 'other-app', 'environment_id' => $otherEnv->id, 'status' => 'running',
    ]);
    $stolenEnv = EnvironmentVariable::create([
        'key' => 'SECRET', 'value' => 'hi',
        'resourceable_type' => Application::class,
        'resourceable_id' => $otherApp->id,
    ]);

    auth()->login($client);

    expect(fn () => Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->call('saveEnv', $stolenEnv->id)
    )->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    expect($stolenEnv->fresh()->value)->toBe('hi');
});
