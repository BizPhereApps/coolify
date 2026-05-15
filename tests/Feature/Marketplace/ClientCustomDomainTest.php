<?php

use App\Models\Application;
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

function clientWithAppAndOffer(bool $allowCustomDomain = true): array
{
    $devTeam = Team::factory()->create();
    Subscription::create([
        'team_id' => $devTeam->id, 'plan_id' => Plan::pro()->id,
        'status' => Subscription::STATUS_ACTIVE, 'period' => Subscription::PERIOD_MONTHLY,
    ]);
    $server = Server::factory()->create([
        'team_id' => $devTeam->id,
        'ip' => '203.0.113.42',
    ]);
    $offer = HostingOffer::create([
        'team_id' => $devTeam->id, 'server_id' => $server->id,
        'name' => 'Starter', 'ram_mb' => 512, 'disk_gb' => 5,
        'max_apps' => 1, 'max_databases' => 0, 'price_ngn_monthly' => 10000,
        'allow_custom_domain' => $allowCustomDomain,
    ]);

    $client = User::factory()->create();
    $project = Project::create(['name' => 'Bola Shop', 'team_id' => $devTeam->id]);
    $environment = Environment::where('project_id', $project->id)->first();

    SubTeam::create([
        'parent_team_id' => $devTeam->id,
        'client_user_id' => $client->id,
        'hosting_offer_id' => $offer->id,
        'project_id' => $project->id,
    ]);

    $application = Application::factory()->create([
        'name' => 'bola-shop',
        'environment_id' => $environment->id,
        'status' => 'running',
    ]);

    return [$client, $application, $server, $devTeam];
}

test('Client can set a valid custom domain on their app', function () {
    [$client, $app] = clientWithAppAndOffer();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->set('customDomain', 'bolasshop.com')
        ->call('saveCustomDomain')
        ->assertHasNoErrors();

    expect($app->fresh()->fqdn)->toBe('https://bolasshop.com');
});

test('pasting a URL with scheme is normalized to bare hostname', function () {
    [$client, $app] = clientWithAppAndOffer();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->set('customDomain', 'https://bolasshop.com/')
        ->call('saveCustomDomain')
        ->assertHasNoErrors();

    expect($app->fresh()->fqdn)->toBe('https://bolasshop.com');
});

test('clearing the field removes the custom domain', function () {
    [$client, $app] = clientWithAppAndOffer();
    $app->update(['fqdn' => 'https://oldname.com']);
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->set('customDomain', '')
        ->call('saveCustomDomain')
        ->assertHasNoErrors();

    expect($app->fresh()->fqdn)->toBeNull();
});

test('an invalid hostname is rejected without writing the FQDN', function () {
    [$client, $app] = clientWithAppAndOffer();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->set('customDomain', 'not a domain!')
        ->call('saveCustomDomain')
        ->assertHasErrors('customDomain');

    expect($app->fresh()->fqdn)->toBeNull();
});

test('an offer with allow_custom_domain=false blocks the Client', function () {
    [$client, $app] = clientWithAppAndOffer(allowCustomDomain: false);
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->set('customDomain', 'bolasshop.com')
        ->call('saveCustomDomain')
        ->assertHasErrors('customDomain');

    expect($app->fresh()->fqdn)->toBeNull();
});

test('a domain already used by another app on the same team is rejected', function () {
    [$client, $app, $server, $devTeam] = clientWithAppAndOffer();

    // Another app on the same Developer's team already claims the domain.
    $otherProject = Project::create(['name' => 'Other', 'team_id' => $devTeam->id]);
    $otherEnv = Environment::where('project_id', $otherProject->id)->first();
    Application::factory()->create([
        'name' => 'taken-app',
        'environment_id' => $otherEnv->id,
        'fqdn' => 'https://bolasshop.com',
    ]);

    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid])
        ->set('customDomain', 'bolasshop.com')
        ->call('saveCustomDomain')
        ->assertHasErrors('customDomain');

    expect($app->fresh()->fqdn)->toBeNull();
});

test('the server IP is exposed via the serverIp computed property', function () {
    [$client, $app] = clientWithAppAndOffer();
    auth()->login($client);

    $component = Livewire::test(\App\Livewire\Client\Application\Show::class, ['applicationUuid' => $app->uuid]);

    expect($component->get('serverIp'))->toBe('203.0.113.42');
});
