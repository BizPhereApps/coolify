<?php

use App\Http\Middleware\ScopeClientToProject;
use App\Models\HostingOffer;
use App\Models\Plan;
use App\Models\Server;
use App\Models\SubTeam;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
});

function clientUserWithSubTeam(): User
{
    $devTeam = Team::factory()->create();
    $server = Server::factory()->create(['team_id' => $devTeam->id]);
    $offer = HostingOffer::create([
        'team_id' => $devTeam->id, 'server_id' => $server->id,
        'name' => 'Starter', 'ram_mb' => 512, 'disk_gb' => 5,
        'max_apps' => 1, 'max_databases' => 0,
        'price_ngn_monthly' => 10000,
    ]);

    $client = User::create([
        'name' => 'Bola', 'email' => 'bola@example.com', 'password' => bcrypt('x'),
    ]);

    SubTeam::create([
        'parent_team_id' => $devTeam->id,
        'client_user_id' => $client->id,
        'hosting_offer_id' => $offer->id,
    ]);

    return $client;
}

function runMiddleware(string $path, ?User $user = null)
{
    $request = Request::create("/{$path}");
    if ($user) {
        $request->setUserResolver(fn () => $user);
    }

    return (new ScopeClientToProject)->handle($request, fn () => response('OK'));
}

test('non-authenticated requests pass through', function () {
    $response = runMiddleware('subscription');
    expect($response->getContent())->toBe('OK');
});

test('a regular tenant user (no SubTeam membership) passes through', function () {
    $user = User::factory()->create();
    $response = runMiddleware('subscription', $user);
    expect($response->getContent())->toBe('OK');
});

test('a Client user is redirected to /client when hitting tenant routes', function () {
    $client = clientUserWithSubTeam();
    $response = runMiddleware('subscription', $client);
    expect($response->isRedirect())->toBeTrue()
        ->and($response->headers->get('Location'))->toContain('/client');
});

test('a Client user can access /client and its descendants', function () {
    $client = clientUserWithSubTeam();
    expect(runMiddleware('client', $client)->getContent())->toBe('OK')
        ->and(runMiddleware('client/anything', $client)->getContent())->toBe('OK');
});

test('a Client user can sign out and finish payment flows even from scoped state', function () {
    $client = clientUserWithSubTeam();
    foreach (['logout', 'payments/paystack/marketplace-callback', 'legal/source', 'livewire/update'] as $path) {
        expect(runMiddleware($path, $client)->getContent())->toBe('OK', "{$path} should pass through");
    }
});

test('a Client user with terminated SubTeam is treated as a regular user', function () {
    $client = clientUserWithSubTeam();
    SubTeam::where('client_user_id', $client->id)->update(['terminated_at' => now()]);

    $response = runMiddleware('subscription', $client);
    expect($response->getContent())->toBe('OK');
});
