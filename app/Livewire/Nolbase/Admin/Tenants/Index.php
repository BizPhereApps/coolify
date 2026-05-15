<?php

namespace App\Livewire\Nolbase\Admin\Tenants;

use App\Models\Team;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.simple')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = Team::query()
            ->whereNotIn('id', [0])
            ->with(['subscription.plan'])
            ->withCount('members');

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'ilike', $term)
                    ->orWhereHas('members', fn ($m) => $m->where('email', 'ilike', $term))
                    ->orWhereHas('subscription', fn ($s) => $s->where('paystack_customer_code', 'ilike', $term));
            });
        }

        if ($this->statusFilter !== '') {
            $query->where('nolbase_status', $this->statusFilter);
        }

        return view('livewire.nolbase.admin.tenants.index', [
            'tenants' => $query->orderByDesc('created_at')->paginate(25),
        ]);
    }
}
