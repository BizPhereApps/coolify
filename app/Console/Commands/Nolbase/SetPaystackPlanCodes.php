<?php

namespace App\Console\Commands\Nolbase;

use App\Models\Plan;
use Illuminate\Console\Command;

use function Laravel\Prompts\text;

/**
 * After creating the 4 plans in the Paystack dashboard (Pro Monthly,
 * Pro Annual, Business Monthly, Business Annual), record their Paystack
 * plan codes against the local `plans` rows so checkout can attach
 * subscriptions to the right plan. This replaces the tinker one-liner
 * documented in LAUNCH-CHECKLIST §2.2.
 *
 * Idempotent: re-running just overwrites the columns with whatever you
 * pass in. Skip a plan by omitting its option.
 */
class SetPaystackPlanCodes extends Command
{
    protected $signature = 'nolbase:plans:set-paystack-codes
        {--pro-monthly= : Pro Monthly plan code (PLN_xxx)}
        {--pro-annual= : Pro Annual plan code}
        {--business-monthly= : Business Monthly plan code}
        {--business-annual= : Business Annual plan code}';

    protected $description = 'Map Paystack plan codes onto the local plans table.';

    public function handle(): int
    {
        $codes = [
            ['plan' => 'pro', 'column' => 'paystack_plan_code_monthly', 'option' => 'pro-monthly', 'label' => 'Pro Monthly'],
            ['plan' => 'pro', 'column' => 'paystack_plan_code_annual', 'option' => 'pro-annual', 'label' => 'Pro Annual'],
            ['plan' => 'business', 'column' => 'paystack_plan_code_monthly', 'option' => 'business-monthly', 'label' => 'Business Monthly'],
            ['plan' => 'business', 'column' => 'paystack_plan_code_annual', 'option' => 'business-annual', 'label' => 'Business Annual'],
        ];

        $interactive = ! $this->hasAnyOption($codes);
        $updated = 0;

        foreach ($codes as $row) {
            $value = $this->option($row['option']);
            if (! $value && $interactive) {
                $value = text("Paystack plan code for {$row['label']}", placeholder: 'PLN_xxxxxxxxxxx', required: false);
            }
            if (! $value) {
                $this->line("  <fg=yellow>skip</>  {$row['label']}");

                continue;
            }
            if (! str_starts_with($value, 'PLN_')) {
                $this->error("'{$value}' does not look like a Paystack plan code (expected PLN_…).");

                return self::FAILURE;
            }

            $plan = Plan::query()->where('code', $row['plan'])->first();
            if (! $plan) {
                $this->error("Local plan '{$row['plan']}' not found. Run the PlanSeeder first.");

                return self::FAILURE;
            }
            $plan->update([$row['column'] => $value]);
            $this->line("  <fg=green>ok</>    {$row['label']} → {$value}");
            $updated++;
        }

        $this->newLine();
        $this->info("Updated {$updated} plan code".($updated === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }

    private function hasAnyOption(array $codes): bool
    {
        foreach ($codes as $row) {
            if ($this->option($row['option'])) {
                return true;
            }
        }

        return false;
    }
}
