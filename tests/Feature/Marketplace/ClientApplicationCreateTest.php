<?php

use App\Models\Application;
use App\Models\Environment;
use App\Models\HostingOffer;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Server;
use App\Models\StandaloneDocker;
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

function clientReadyToCreateApp(int $maxApps = 1): array
{
    $devTeam = Team::factory()->create();
    Subscription::create([
        'team_id' => $devTeam->id, 'plan_id' => Plan::pro()->id,
        'status' => Subscription::STATUS_ACTIVE, 'period' => Subscription::PERIOD_MONTHLY,
    ]);
    // Server::created auto-creates a default StandaloneDocker — don't duplicate.
    $server = Server::factory()->create(['team_id' => $devTeam->id]);
    $offer = HostingOffer::create([
        'team_id' => $devTeam->id, 'server_id' => $server->id,
        'name' => 'Starter', 'ram_mb' => 512, 'disk_gb' => 5,
        'max_apps' => $maxApps, 'max_databases' => 0, 'price_ngn_monthly' => 10000,
    ]);
    $client = User::factory()->create();
    $project = Project::create(['name' => 'Bola Shop', 'team_id' => $devTeam->id]);
    SubTeam::create([
        'parent_team_id' => $devTeam->id,
        'client_user_id' => $client->id,
        'hosting_offer_id' => $offer->id,
        'project_id' => $project->id,
    ]);

    return [$client, $project, $server, $offer];
}

test('a Client can create an application via Git URL', function () {
    [$client, $project] = clientReadyToCreateApp(maxApps: 2);
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Create::class)
        ->set('name', 'bola-shop')
        ->set('git_repository', 'https://github.com/bola/shop.git')
        ->set('git_branch', 'main')
        ->set('ports_exposes', '3000')
        ->set('build_pack', 'nixpacks')
        ->call('submit')
        ->assertHasNoErrors();

    $app = Application::where('name', 'bola-shop')->first();
    expect($app)->not->toBeNull()
        ->and($app->git_repository)->toBe('https://github.com/bola/shop.git')
        ->and($app->build_pack)->toBe('nixpacks')
        ->and($app->ports_exposes)->toBe('3000')
        ->and($app->environment->project_id)->toBe($project->id);
});

test('the Client is over quota when they have max_apps applications already', function () {
    [$client, $project] = clientReadyToCreateApp(maxApps: 1);
    $env = Environment::where('project_id', $project->id)->first();
    Application::factory()->create(['environment_id' => $env->id]);

    auth()->login($client);

    $component = Livewire::test(\App\Livewire\Client\Application\Create::class);
    expect($component->instance()->isOverQuota())->toBeTrue();

    $component->set('name', 'another')
        ->set('git_repository', 'https://github.com/bola/another.git')
        ->call('submit')
        ->assertHasErrors('quota');

    expect(Application::where('name', 'another')->count())->toBe(0);
});

test('Invalid Git URL is rejected', function () {
    [$client] = clientReadyToCreateApp();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Create::class)
        ->set('name', 'bad')
        ->set('git_repository', 'not-a-url')
        ->call('submit')
        ->assertHasErrors('git_repository');

    expect(Application::where('name', 'bad')->count())->toBe(0);
});

test('name with bad characters is rejected', function () {
    [$client] = clientReadyToCreateApp();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Create::class)
        ->set('name', 'has spaces!')
        ->set('git_repository', 'https://github.com/bola/x.git')
        ->call('submit')
        ->assertHasErrors('name');
});

test('non-numeric ports are rejected', function () {
    [$client] = clientReadyToCreateApp();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Create::class)
        ->set('name', 'okay')
        ->set('git_repository', 'https://github.com/bola/x.git')
        ->set('ports_exposes', 'http')
        ->call('submit')
        ->assertHasErrors('ports_exposes');
});

test('Dashboard hides New application button when over quota', function () {
    [$client, $project] = clientReadyToCreateApp(maxApps: 1);
    $env = Environment::where('project_id', $project->id)->first();
    Application::factory()->create(['environment_id' => $env->id]);

    auth()->login($client);

    $component = Livewire::test(\App\Livewire\Client\Dashboard::class);
    expect($component->get('canAddApp'))->toBeFalse();
});

test('Dashboard shows New application button when under quota', function () {
    [$client] = clientReadyToCreateApp(maxApps: 3);
    auth()->login($client);

    $component = Livewire::test(\App\Livewire\Client\Dashboard::class);
    expect($component->get('canAddApp'))->toBeTrue();
});

test('max_apps = 0 means unlimited and dashboard always allows New app', function () {
    [$client, $project] = clientReadyToCreateApp(maxApps: 0);
    $env = Environment::where('project_id', $project->id)->first();
    Application::factory()->count(10)->create(['environment_id' => $env->id]);

    auth()->login($client);

    $component = Livewire::test(\App\Livewire\Client\Dashboard::class);
    expect($component->get('canAddApp'))->toBeTrue();
});
