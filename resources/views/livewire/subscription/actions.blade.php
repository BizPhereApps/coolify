<div class="inline-flex gap-2">
    @if ($subscription->isCancelled())
        {{-- nothing to do --}}
    @elseif ($subscription->cancel_at_period_end)
        <button wire:click="resume" class="button">Resume subscription</button>
    @else
        <button wire:click="cancelAtPeriodEnd"
                wire:confirm="Cancel subscription at end of current period?"
                class="button">Cancel subscription</button>
    @endif
</div>
