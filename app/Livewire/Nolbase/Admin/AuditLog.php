<?php

namespace App\Livewire\Nolbase\Admin;

use App\Models\NolbaseAdminAudit;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.simple')]
class AuditLog extends Component
{
    use WithPagination;

    #[Url(as: 'action')]
    public string $actionFilter = '';

    #[Url(as: 'admin')]
    public string $adminFilter = '';

    public function updatedActionFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAdminFilter(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = NolbaseAdminAudit::query()->with('admin')->orderByDesc('created_at');

        if ($this->actionFilter !== '') {
            $query->where('action', $this->actionFilter);
        }
        if ($this->adminFilter !== '') {
            $query->where('nolbase_admin_id', $this->adminFilter);
        }

        return view('livewire.nolbase.admin.audit-log', [
            'entries' => $query->paginate(50),
            'actions' => NolbaseAdminAudit::query()->distinct()->orderBy('action')->pluck('action'),
            'admins' => \App\Models\NolbaseAdmin::orderBy('name')->get(['id', 'name', 'email']),
        ]);
    }
}
