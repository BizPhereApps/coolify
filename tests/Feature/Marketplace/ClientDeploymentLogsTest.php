<?php

use App\Models\Application;
use App\Models\ApplicationDeploymentQueue;
use App\Models\Environment;
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

function clientWithAppForLogs(): array
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
    $project = Project::create(['name' => 'Bola Shop', 'team_id' => $devTeam->id]);
    $env = Environment::where('project_id', $project->id)->first();
    SubTeam::create([
        'parent_team_id' => $devTeam->id,
        'client_user_id' => $client->id,
        'hosting_offer_id' => $offer->id,
        'project_id' => $project->id,
    ]);
    $app = Application::factory()->create([
        'name' => 'bola-shop',
        'environment_id' => $env->id,
        'status' => 'running',
    ]);

    return [$client, $app, $server];
}

test('latestDeployment returns null when no deployment exists', function () {
    [$client, $app] = clientWithAppForLogs();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->assertSet('latestDeployment', null)
        ->assertSet('isDeploymentInProgress', false)
        ->assertSet('latestDeploymentLogs', []);
});

test('latestDeployment returns the most recent deployment for this app', function () {
    [$client, $app, $server] = clientWithAppForLogs();

    // Create both, then bypass Eloquent timestamping with an UPDATE to make
    // the ordering deterministic (Eloquent overrides created_at on insert).
    $older = ApplicationDeploymentQueue::create([
        'application_id' => $app->id,
        'deployment_uuid' => 'dep_old',
        'server_id' => $server->id,
        'application_name' => $app->name,
        'server_name' => $server->name,
        'destination_id' => 1,
        'status' => 'finished',
    ]);
    $older->forceFill(['created_at' => now()->subMinutes(10)])->save();

    $newer = ApplicationDeploymentQueue::create([
        'application_id' => $app->id,
        'deployment_uuid' => 'dep_new',
        'server_id' => $server->id,
        'application_name' => $app->name,
        'server_name' => $server->name,
        'destination_id' => 1,
        'status' => 'in_progress',
        'logs' => json_encode([
            ['name' => 'clone', 'time' => '12:00:00', 'output' => 'Cloning repo...'],
            ['name' => 'build', 'time' => '12:00:05', 'output' => 'Running npm ci...'],
        ]),
    ]);
    $newer->forceFill(['created_at' => now()->subMinute()])->save();

    auth()->login($client);

    $component = Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid]);

    expect($component->get('latestDeployment')->id)->toBe($newer->id)
        ->and($component->get('isDeploymentInProgress'))->toBeTrue()
        ->and($component->get('latestDeploymentLogs'))->toHaveCount(2)
        ->and($component->get('latestDeploymentLogs')[0]['name'])->toBe('clone');
});

test('isDeploymentInProgress reflects status correctly', function () {
    [$client, $app, $server] = clientWithAppForLogs();

    foreach (['queued', 'in_progress'] as $activeStatus) {
        ApplicationDeploymentQueue::query()->delete();
        ApplicationDeploymentQueue::create([
            'application_id' => $app->id,
            'deployment_uuid' => 'dep_'.$activeStatus,
            'server_id' => $server->id,
            'application_name' => $app->name,
            'server_name' => $server->name,
            'destination_id' => 1,
            'status' => $activeStatus,
        ]);
        auth()->login($client);
        $component = Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid]);
        expect($component->get('isDeploymentInProgress'))->toBeTrue("expected true for status={$activeStatus}");
    }

    foreach (['finished', 'error', 'killed'] as $terminalStatus) {
        ApplicationDeploymentQueue::query()->delete();
        ApplicationDeploymentQueue::create([
            'application_id' => $app->id,
            'deployment_uuid' => 'dep_'.$terminalStatus,
            'server_id' => $server->id,
            'application_name' => $app->name,
            'server_name' => $server->name,
            'destination_id' => 1,
            'status' => $terminalStatus,
        ]);
        auth()->login($client);
        $component = Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid]);
        expect($component->get('isDeploymentInProgress'))->toBeFalse("expected false for status={$terminalStatus}");
    }
});

test('latestDeploymentLogs returns [] for malformed logs JSON without crashing', function () {
    [$client, $app, $server] = clientWithAppForLogs();
    ApplicationDeploymentQueue::create([
        'application_id' => $app->id,
        'deployment_uuid' => 'dep_x',
        'server_id' => $server->id,
        'application_name' => $app->name,
        'server_name' => $server->name,
        'destination_id' => 1,
        'status' => 'finished',
        'logs' => '{this is not valid json',
    ]);

    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->assertSet('latestDeploymentLogs', []);
});
