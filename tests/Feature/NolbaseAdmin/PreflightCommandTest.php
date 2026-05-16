<?php

use App\Models\NolbaseAdmin;
use App\Models\Plan;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
});

function configureGreenProductionEnv(): void
{
    config()->set('app.env', 'production');
    config()->set('app.debug', false);
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    config()->set('constants.coolify.self_hosted', false);
    config()->set('nolbase.source_repo_url', 'https://github.com/BizPhereApps/nolbase');
    config()->set('paystack.secret_key', 'sk_live_'.bin2hex(random_bytes(8)));
    config()->set('paystack.public_key', 'pk_live_'.bin2hex(random_bytes(8)));
    config()->set('paystack.webhook_secret', bin2hex(random_bytes(16)));
    config()->set('paystack.marketplace_fee_pct', 10.0);
    config()->set('mail.from.address', 'noreply@mail.nolbase.com');
    config()->set('mail.default', 'smtp');

    Plan::pro()->update([
        'paystack_plan_code_monthly' => 'PLN_pro_m',
        'paystack_plan_code_annual' => 'PLN_pro_a',
    ]);
    Plan::business()->update([
        'paystack_plan_code_monthly' => 'PLN_biz_m',
        'paystack_plan_code_annual' => 'PLN_biz_a',
    ]);

    NolbaseAdmin::create([
        'name' => 'Boss',
        'email' => 'boss@nolbase.test',
        'password' => Hash::make('strongpassword12'),
        'role' => NolbaseAdmin::ROLE_SUPERADMIN,
    ]);
}

test('preflight passes with a fully configured production environment', function () {
    configureGreenProductionEnv();

    $this->artisan('nolbase:preflight')
        ->expectsOutputToContain('PASS')
        ->assertExitCode(0);
});

test('preflight fails when the AGPL source repo URL is still placeholder', function () {
    configureGreenProductionEnv();
    config()->set('nolbase.source_repo_url', 'https://github.com/REPLACE-ME/nolbase');

    $this->artisan('nolbase:preflight')
        ->expectsOutputToContain('REPLACE-ME')
        ->assertExitCode(1);
});

test('preflight fails when paystack secret is a test key in production', function () {
    configureGreenProductionEnv();
    config()->set('paystack.secret_key', 'sk_test_abc');

    $this->artisan('nolbase:preflight')
        ->expectsOutputToContain('TEST key in production')
        ->assertExitCode(1);
});

test('preflight fails when APP_DEBUG is true in production', function () {
    configureGreenProductionEnv();
    config()->set('app.debug', true);

    $this->artisan('nolbase:preflight')
        ->expectsOutputToContain('leaks stack traces')
        ->assertExitCode(1);
});

test('preflight fails when SELF_HOSTED is true', function () {
    configureGreenProductionEnv();
    config()->set('constants.coolify.self_hosted', true);

    $this->artisan('nolbase:preflight')
        ->expectsOutputToContain('multi-tenant mode')
        ->assertExitCode(1);
});

test('preflight fails when pro plan is missing paystack codes', function () {
    configureGreenProductionEnv();
    Plan::pro()->update([
        'paystack_plan_code_monthly' => null,
        'paystack_plan_code_annual' => null,
    ]);

    $this->artisan('nolbase:preflight')
        ->expectsOutputToContain('plan pro paystack codes')
        ->assertExitCode(1);
});

test('preflight fails when no superadmin exists', function () {
    configureGreenProductionEnv();
    NolbaseAdmin::query()->where('role', NolbaseAdmin::ROLE_SUPERADMIN)->delete();

    $this->artisan('nolbase:preflight')
        ->expectsOutputToContain('nolbase:admin:create')
        ->assertExitCode(1);
});

test('preflight fails when MAIL_FROM_ADDRESS is still example.com', function () {
    configureGreenProductionEnv();
    config()->set('mail.from.address', 'noreply@example.com');

    $this->artisan('nolbase:preflight')
        ->expectsOutputToContain('placeholder or empty')
        ->assertExitCode(1);
});

test('preflight --strict treats warnings as failures', function () {
    configureGreenProductionEnv();
    // Force a warn: marketplace fee of 0 is a warn (questionable but not fatal)
    config()->set('paystack.marketplace_fee_pct', 0);

    // Without strict, this is a warn → exit 0
    $this->artisan('nolbase:preflight')
        ->assertExitCode(0);

    // With strict, warns become failures
    $this->artisan('nolbase:preflight --strict')
        ->assertExitCode(1);
});

test('preflight --json emits machine-readable output', function () {
    configureGreenProductionEnv();

    $this->artisan('nolbase:preflight --json')
        ->expectsOutputToContain('"summary"')
        ->assertExitCode(0);
});
