<?php

use App\Actions\Marketplace\RunPayoutBatch;
use App\Actions\Paystack\SaveDeveloperPayoutAccount;
use App\Models\MarketplaceTransaction;
use App\Models\PayoutAccount;
use App\Models\Team;
use Database\Seeders\InstanceSettingsSeeder;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(InstanceSettingsSeeder::class);
    $this->seed(PlanSeeder::class);
    config()->set('paystack.secret_key', 'sk_test_payouts');
});

function fakePaystackTransferOk(): void
{
    Http::fake([
        'api.paystack.co/bank/resolve*' => Http::response([
            'status' => true,
            'data' => ['account_name' => 'BOLA OWONIBI', 'account_number' => '0123456789'],
        ]),
        'api.paystack.co/transferrecipient' => Http::response([
            'status' => true,
            'data' => ['recipient_code' => 'RCP_test_123'],
        ]),
        'api.paystack.co/transfer' => Http::response([
            'status' => true,
            'data' => ['transfer_code' => 'TRF_test_xyz', 'reference' => 'nb_payout_ref'],
        ]),
    ]);
}

function recordCharge(Team $team, int $amountNgn, int $feeNgn): MarketplaceTransaction
{
    return MarketplaceTransaction::create([
        'type' => MarketplaceTransaction::TYPE_CHARGE,
        'developer_team_id' => $team->id,
        'amount_ngn' => $amountNgn,
        'fee_ngn' => $feeNgn,
        'net_ngn' => $amountNgn - $feeNgn,
        'status' => MarketplaceTransaction::STATUS_SUCCESS,
        'paystack_reference' => 'ref_'.uniqid(),
        'occurred_at' => now(),
    ]);
}

test('SaveDeveloperPayoutAccount verifies the account and stores recipient_code', function () {
    $team = Team::factory()->create();
    fakePaystackTransferOk();

    $account = SaveDeveloperPayoutAccount::run($team, '058', '0123456789');

    expect($account->team_id)->toBe($team->id)
        ->and($account->account_name)->toBe('BOLA OWONIBI')
        ->and($account->paystack_recipient_code)->toBe('RCP_test_123')
        ->and($account->isVerified())->toBeTrue();
});

test('SaveDeveloperPayoutAccount throws when Paystack cannot resolve the account', function () {
    $team = Team::factory()->create();
    Http::fake([
        'api.paystack.co/bank/resolve*' => Http::response([
            'status' => false,
            'message' => 'Could not resolve account',
        ], 422),
    ]);

    expect(fn () => SaveDeveloperPayoutAccount::run($team, '058', '9999999999'))
        ->toThrow(\RuntimeException::class);
});

test('RunPayoutBatch skips developers below the minimum payout', function () {
    $team = Team::factory()->create();
    PayoutAccount::create([
        'team_id' => $team->id,
        'bank_code' => '058',
        'account_number_encrypted' => '0123456789',
        'account_name' => 'X',
        'paystack_recipient_code' => 'RCP_x',
        'verified_at' => now(),
    ]);
    // Only ₦2,000 — below the ₦5,000 floor.
    recordCharge($team, 2000, 200);

    fakePaystackTransferOk();
    $result = RunPayoutBatch::run();

    expect($result)->toMatchArray(['paid' => 0, 'skipped' => 1, 'total_ngn' => 0]);
    expect(MarketplaceTransaction::where('type', MarketplaceTransaction::TYPE_PAYOUT)->count())->toBe(0);
});

test('RunPayoutBatch pays developers at-or-above the minimum and records a payout row', function () {
    $team = Team::factory()->create();
    PayoutAccount::create([
        'team_id' => $team->id,
        'bank_code' => '058',
        'account_number_encrypted' => '0123456789',
        'account_name' => 'BOLA',
        'paystack_recipient_code' => 'RCP_real',
        'verified_at' => now(),
    ]);
    $c1 = recordCharge($team, 10000, 1000); // net 9000
    $c2 = recordCharge($team, 5000, 500);   // net 4500
    // Total net: 13,500 → above ₦5,000 minimum.

    fakePaystackTransferOk();
    $result = RunPayoutBatch::run();

    expect($result)->toMatchArray(['paid' => 1, 'skipped' => 0, 'total_ngn' => 13500]);

    $payout = MarketplaceTransaction::where('type', MarketplaceTransaction::TYPE_PAYOUT)->first();
    expect($payout->amount_ngn)->toBe(13500)
        ->and(data_get($payout->payload, 'charge_ids'))->toBe([$c1->id, $c2->id]);
});

test('RunPayoutBatch is idempotent — already-covered charges are not paid out twice', function () {
    $team = Team::factory()->create();
    PayoutAccount::create([
        'team_id' => $team->id,
        'bank_code' => '058',
        'account_number_encrypted' => '0123456789',
        'account_name' => 'BOLA',
        'paystack_recipient_code' => 'RCP_real',
        'verified_at' => now(),
    ]);
    recordCharge($team, 10000, 1000); // net 9000

    fakePaystackTransferOk();
    RunPayoutBatch::run();
    $secondRun = RunPayoutBatch::run();

    expect($secondRun)->toMatchArray(['paid' => 0, 'skipped' => 1, 'total_ngn' => 0]);
    expect(MarketplaceTransaction::where('type', MarketplaceTransaction::TYPE_PAYOUT)->count())->toBe(1);
});

test('A new charge after a previous payout gets included in the NEXT run', function () {
    $team = Team::factory()->create();
    PayoutAccount::create([
        'team_id' => $team->id,
        'bank_code' => '058',
        'account_number_encrypted' => '0123456789',
        'account_name' => 'BOLA',
        'paystack_recipient_code' => 'RCP_real',
        'verified_at' => now(),
    ]);
    recordCharge($team, 10000, 1000);
    fakePaystackTransferOk();
    RunPayoutBatch::run(); // first payout

    // New charge arrives later
    $newCharge = recordCharge($team, 7000, 700); // net 6300
    $second = RunPayoutBatch::run();

    expect($second['paid'])->toBe(1);
    $payouts = MarketplaceTransaction::where('type', MarketplaceTransaction::TYPE_PAYOUT)->orderBy('id')->get();
    expect($payouts->count())->toBe(2)
        ->and($payouts->last()->amount_ngn)->toBe(6300)
        ->and(data_get($payouts->last()->payload, 'charge_ids'))->toBe([$newCharge->id]);
});

test('Developers without a verified payout account are skipped entirely', function () {
    $team = Team::factory()->create();
    PayoutAccount::create([
        'team_id' => $team->id,
        'bank_code' => '058',
        'account_number_encrypted' => '0123456789',
        'account_name' => 'X',
        'paystack_recipient_code' => 'RCP_x',
        // No verified_at
    ]);
    recordCharge($team, 50000, 5000); // net 45000

    fakePaystackTransferOk();
    $result = RunPayoutBatch::run();

    expect($result)->toMatchArray(['paid' => 0, 'skipped' => 0, 'total_ngn' => 0]);
});
