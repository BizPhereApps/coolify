<?php

namespace App\Livewire\Nolbase\Admin;

use App\Models\NolbaseAdminAudit;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.simple')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public function submit()
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('nolbase')->attempt(['email' => $this->email, 'password' => $this->password], true)) {
            $this->addError('email', 'Invalid credentials.');

            return null;
        }

        $admin = Auth::guard('nolbase')->user();
        $admin->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
        ]);

        NolbaseAdminAudit::log($admin, 'login');

        return redirect()->route('nolbase.admin.dashboard');
    }

    public function render()
    {
        return view('livewire.nolbase.admin.login');
    }
}
