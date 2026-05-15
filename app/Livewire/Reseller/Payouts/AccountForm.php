<?php

namespace App\Livewire\Reseller\Payouts;

use App\Actions\Paystack\SaveDeveloperPayoutAccount;
use App\Models\PayoutAccount;
use App\Services\PaystackService;
use Livewire\Component;

class AccountForm extends Component
{
    public string $bank_code = '';

    public string $account_number = '';

    public ?string $resolved_account_name = null;

    public bool $loading_banks = false;

    public array $banks = [];

    public function mount(): void
    {
        $this->loadBanks();
    }

    public function loadBanks(): void
    {
        $this->loading_banks = true;
        try {
            $this->banks = PaystackService::fromConfig()->getBanks();
        } catch (\Throwable $e) {
            $this->addError('banks', 'Could not load bank list: '.$e->getMessage());
            $this->banks = [];
        }
        $this->loading_banks = false;
    }

    public function resolve(): void
    {
        $this->validate([
            'bank_code' => 'required|string',
            'account_number' => ['required', 'string', 'regex:/^\d{10}$/'],
        ], [
            'account_number.regex' => 'Nigerian bank account numbers are 10 digits.',
        ]);

        try {
            $data = PaystackService::fromConfig()->resolveAccount($this->account_number, $this->bank_code);
            $this->resolved_account_name = data_get($data, 'account_name');
        } catch (\Throwable $e) {
            $this->resolved_account_name = null;
            $this->addError('account_number', 'Account could not be resolved: '.$e->getMessage());
        }
    }

    public function save(): mixed
    {
        if (! $this->resolved_account_name) {
            $this->addError('save', 'Resolve the account first to confirm the holder name.');

            return null;
        }

        try {
            SaveDeveloperPayoutAccount::run(currentTeam(), $this->bank_code, $this->account_number);

            return $this->redirect(route('reseller.payouts.index'));
        } catch (\Throwable $e) {
            $this->addError('save', $e->getMessage());

            return null;
        }
    }

    public function render()
    {
        return view('livewire.reseller.payouts.account-form');
    }
}
