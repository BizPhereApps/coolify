<?php

use App\Models\Application;
use App\Models\GithubApp;
use App\Models\HostingOffer;
use App\Models\Plan;
use App\Models\PrivateKey;
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

function clientReadyForPrivateRepo(): array
{
    $devTeam = Team::factory()->create();
    Subscription::create([
        'team_id' => $devTeam->id, 'plan_id' => Plan::pro()->id,
        'status' => Subscription::STATUS_ACTIVE, 'period' => Subscription::PERIOD_MONTHLY,
    ]);
    // Need a PrivateKey for GithubApp.private_key_id FK. PrivateKey::saving
    // validates the key parses as a real OpenSSH key; we just need the FK
    // satisfied so insert directly via the query builder.
    $privateKeyId = \DB::table('private_keys')->insertGetId([
        'team_id' => $devTeam->id, 'name' => 'gha-key',
        'private_key' => 'encrypted-placeholder', 'uuid' => (string) new \Visus\Cuid2\Cuid2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $server = Server::factory()->create(['team_id' => $devTeam->id]);
    $offer = HostingOffer::create([
        'team_id' => $devTeam->id, 'server_id' => $server->id,
        'name' => 'Starter', 'ram_mb' => 512, 'disk_gb' => 5,
        'max_apps' => 3, 'max_databases' => 0, 'price_ngn_monthly' => 10000,
    ]);
    $client = User::factory()->create();
    $project = Project::create(['name' => 'Bola Shop', 'team_id' => $devTeam->id]);
    SubTeam::create([
        'parent_team_id' => $devTeam->id,
        'client_user_id' => $client->id,
        'hosting_offer_id' => $offer->id,
        'project_id' => $project->id,
    ]);

    // The Developer has installed a GitHub App.
    $githubApp = GithubApp::create([
        'team_id' => $devTeam->id,
        'private_key_id' => $privateKeyId,
        'name' => 'Adaeze Agency GH',
        'organization' => 'adaezeagency',
        'api_url' => 'https://api.github.com',
        'html_url' => 'https://github.com',
        'app_id' => 12345,
        'installation_id' => 67890,
        'client_id' => 'Iv1.abc',
        'client_secret' => 'secret',
        'webhook_secret' => 'whsec',
    ]);

    return [$client, $githubApp, $devTeam];
}

test('Client sees their developer GitHub App in the source picker', function () {
    [$client, $githubApp] = clientReadyForPrivateRepo();
    auth()->login($client);

    $component = Livewire::test(\App\Livewire\Client\Application\Create::class);
    $available = $component->get('availableGithubApps');

    expect($available)->toHaveCount(1)
        ->and($available->first()->id)->toBe($githubApp->id);
});

test('A different team\'s GitHub App is NOT visible to the Client', function () {
    [$client, $githubApp] = clientReadyForPrivateRepo();

    // Set up a completely unrelated tenant with their own GitHub App.
    $strangerTeam = Team::factory()->create();
    $strangerKeyId = \DB::table('private_keys')->insertGetId([
        'team_id' => $strangerTeam->id, 'name' => 'stranger-key',
        'private_key' => 'encrypted-placeholder', 'uuid' => (string) new \Visus\Cuid2\Cuid2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    GithubApp::create([
        'team_id' => $strangerTeam->id, 'private_key_id' => $strangerKeyId,
        'name' => 'Stranger GH', 'organization' => 'stranger',
        'api_url' => 'https://api.github.com', 'html_url' => 'https://github.com',
        'app_id' => 99999, 'installation_id' => 99999,
        'client_id' => 'x', 'client_secret' => 'x', 'webhook_secret' => 'x',
    ]);

    auth()->login($client);
    $component = Livewire::test(\App\Livewire\Client\Application\Create::class);

    expect($component->get('availableGithubApps'))->toHaveCount(1)
        ->and($component->get('availableGithubApps')->first()->organization)->toBe('adaezeagency');
});

test('Client can create an Application linked to the developer GitHub App with owner/repo', function () {
    [$client, $githubApp] = clientReadyForPrivateRepo();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Create::class)
        ->set('name', 'shop-api')
        ->set('sourceId', $githubApp->id)
        ->set('git_repository', 'bola/shop')
        ->set('git_branch', 'main')
        ->set('ports_exposes', '3000')
        ->call('submit')
        ->assertHasNoErrors();

    $app = Application::where('name', 'shop-api')->first();
    expect($app)->not->toBeNull()
        ->and($app->source_id)->toBe($githubApp->id)
        ->and($app->source_type)->toBe(GithubApp::class)
        ->and($app->git_repository)->toBe('bola/shop');
});

test('Selecting a source rejects a full URL — must be owner/repo format', function () {
    [$client, $githubApp] = clientReadyForPrivateRepo();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Create::class)
        ->set('name', 'badformat')
        ->set('sourceId', $githubApp->id)
        ->set('git_repository', 'https://github.com/bola/shop.git')
        ->set('git_branch', 'main')
        ->set('ports_exposes', '3000')
        ->call('submit')
        ->assertHasErrors('git_repository');

    expect(Application::where('name', 'badformat')->count())->toBe(0);
});

test('No source selected uses the public-URL validation path', function () {
    [$client] = clientReadyForPrivateRepo();
    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Create::class)
        ->set('name', 'public-app')
        ->set('sourceId', null)
        ->set('git_repository', 'https://github.com/bola/public-shop.git')
        ->set('git_branch', 'main')
        ->set('ports_exposes', '3000')
        ->call('submit')
        ->assertHasNoErrors();

    $app = Application::where('name', 'public-app')->first();
    expect($app)->not->toBeNull()
        ->and($app->source_id)->toBeNull()
        ->and($app->source_type)->toBeNull();
});

test('Client cannot bypass the dropdown by setting sourceId to a cross-team GithubApp', function () {
    [$client] = clientReadyForPrivateRepo();

    // Build a GithubApp under a stranger's team and try to use its id.
    $strangerTeam = Team::factory()->create();
    $strangerKeyId = \DB::table('private_keys')->insertGetId([
        'team_id' => $strangerTeam->id, 'name' => 'k',
        'private_key' => 'encrypted-placeholder', 'uuid' => (string) new \Visus\Cuid2\Cuid2,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $stolen = GithubApp::create([
        'team_id' => $strangerTeam->id, 'private_key_id' => $strangerKeyId,
        'name' => 'Stolen', 'organization' => 'stranger',
        'api_url' => 'https://api.github.com', 'html_url' => 'https://github.com',
        'app_id' => 1, 'installation_id' => 1,
        'client_id' => 'x', 'client_secret' => 'x', 'webhook_secret' => 'x',
    ]);

    auth()->login($client);

    Livewire::test(\App\Livewire\Client\Application\Create::class)
        ->set('name', 'sneaky')
        ->set('sourceId', $stolen->id)
        ->set('git_repository', 'attacker/repo')
        ->set('git_branch', 'main')
        ->set('ports_exposes', '3000')
        ->call('submit')
        ->assertHasErrors('sourceId');

    expect(Application::where('name', 'sneaky')->count())->toBe(0);
});
