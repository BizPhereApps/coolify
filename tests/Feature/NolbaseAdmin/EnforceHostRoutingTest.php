<?php

use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('nolbase.marketing_host', 'nolbase.io');
    config()->set('nolbase.app_host', 'app.nolbase.io');
    // Coolify's PreventRequestsDuringMaintenance uses cache.store('redis')
    // — bypass with the array store so tests don't try to reach Redis.
    config()->set('app.maintenance.store', 'array');
    config()->set('app.url', 'http://nolbase.io');
    Cache::forget('instance_settings_fqdn_host');
});

test('marketing host serves the homepage', function () {
    $this->get('http://nolbase.io/')
        ->assertOk()
        ->assertSee('Self-hostable PaaS', false);
});

test('marketing host serves /pricing', function () {
    $this->get('http://nolbase.io/pricing')->assertOk();
});

test('marketing host serves /legal/privacy with DRAFT banner', function () {
    $this->get('http://nolbase.io/legal/privacy')
        ->assertOk()
        ->assertSee('DRAFT — not legally binding', false);
});

test('marketing host serves /legal/terms with DRAFT banner', function () {
    $this->get('http://nolbase.io/legal/terms')
        ->assertOk()
        ->assertSee('DRAFT — not legally binding', false);
});

test('marketing host redirects /login to the app host', function () {
    $this->get('http://nolbase.io/login')
        ->assertRedirect('http://app.nolbase.io/login');
});

test('marketing host redirects /register to the app host', function () {
    $this->get('http://nolbase.io/register')
        ->assertRedirect('http://app.nolbase.io/register');
});

test('marketing host redirects deep app paths preserving path + query', function () {
    $this->get('http://nolbase.io/subscription/new?plan=pro')
        ->assertRedirect('http://app.nolbase.io/subscription/new?plan=pro');
});

test('app host at / redirects through the auth gate', function () {
    $this->get('http://app.nolbase.io/')->assertRedirect();
});

test('app host redirects /pricing back to the marketing host', function () {
    $this->get('http://app.nolbase.io/pricing')
        ->assertRedirect('http://nolbase.io/pricing');
});

test('app host redirects legal pages back to the marketing host', function () {
    $this->get('http://app.nolbase.io/legal/privacy')
        ->assertRedirect('http://nolbase.io/legal/privacy');
});

test('middleware is a no-op when hosts are not configured (dev mode)', function () {
    config()->set('nolbase.marketing_host', null);
    config()->set('nolbase.app_host', null);

    $this->get('http://anything.test/pricing')->assertOk();
});

test('middleware is a no-op when both hosts are equal', function () {
    config()->set('nolbase.marketing_host', 'localhost');
    config()->set('nolbase.app_host', 'localhost');

    $this->get('http://localhost/pricing')->assertOk();
});
