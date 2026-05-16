<?php

use App\Models\Plan;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
});

test('non-interactive: writes provided codes onto the correct plans', function () {
    $this->artisan('nolbase:plans:set-paystack-codes', [
        '--pro-monthly' => 'PLN_pro_m',
        '--pro-annual' => 'PLN_pro_a',
        '--business-monthly' => 'PLN_biz_m',
        '--business-annual' => 'PLN_biz_a',
    ])->assertExitCode(0);

    $pro = Plan::pro();
    $biz = Plan::business();

    expect($pro->paystack_plan_code_monthly)->toBe('PLN_pro_m')
        ->and($pro->paystack_plan_code_annual)->toBe('PLN_pro_a')
        ->and($biz->paystack_plan_code_monthly)->toBe('PLN_biz_m')
        ->and($biz->paystack_plan_code_annual)->toBe('PLN_biz_a');
});

test('rejects codes that do not match the PLN_ prefix', function () {
    $this->artisan('nolbase:plans:set-paystack-codes', [
        '--pro-monthly' => 'random_string',
    ])
        ->expectsOutputToContain('does not look like a Paystack plan code')
        ->assertExitCode(1);

    expect(Plan::pro()->paystack_plan_code_monthly)->toBeNull();
});

test('partial updates leave the other plans untouched', function () {
    Plan::business()->update([
        'paystack_plan_code_monthly' => 'PLN_existing_biz_m',
        'paystack_plan_code_annual' => 'PLN_existing_biz_a',
    ]);

    $this->artisan('nolbase:plans:set-paystack-codes', [
        '--pro-monthly' => 'PLN_pro_m',
    ])->assertExitCode(0);

    expect(Plan::pro()->paystack_plan_code_monthly)->toBe('PLN_pro_m')
        ->and(Plan::business()->paystack_plan_code_monthly)->toBe('PLN_existing_biz_m');
});

test('re-running with new codes overwrites the old ones', function () {
    $this->artisan('nolbase:plans:set-paystack-codes', ['--pro-monthly' => 'PLN_v1'])->assertExitCode(0);
    $this->artisan('nolbase:plans:set-paystack-codes', ['--pro-monthly' => 'PLN_v2'])->assertExitCode(0);

    expect(Plan::pro()->paystack_plan_code_monthly)->toBe('PLN_v2');
});
