<?php

namespace App\Livewire\Client\Application;

use App\Models\Application;
use App\Models\EnvironmentVariable;
use App\Models\SubTeam;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Visus\Cuid2\Cuid2;

#[Layout('layouts.simple')]
class Show extends Component
{
    public Application $application;

    public SubTeam $subTeam;

    public string $newEnvKey = '';

    public string $newEnvValue = '';

    public array $envEdits = [];

    public function mount(string $applicationUuid): void
    {
        $user = auth()->user();
        abort_unless($user, 401);

        $subTeam = SubTeam::with('project')
            ->where('client_user_id', $user->id)
            ->whereNull('terminated_at')
            ->first();
        abort_unless($subTeam, 404, 'No active hosting.');

        $application = Application::with(['environment', 'environment_variables'])
            ->where('uuid', $applicationUuid)
            ->first();
        abort_unless($application, 404, 'Application not found.');

        // Ownership: the application must belong to the SubTeam's project.
        if ($application->environment?->project_id !== $subTeam->project_id) {
            abort(403, 'You do not have access to this application.');
        }

        $this->subTeam = $subTeam;
        $this->application = $application;
        $this->envEdits = $application->environment_variables
            ->where('resourceable_type', Application::class)
            ->mapWithKeys(fn ($e) => [$e->id => $e->value])
            ->toArray();
    }

    public function deploy(): void
    {
        $deploymentUuid = (string) new Cuid2;
        $result = queue_application_deployment(
            application: $this->application,
            deployment_uuid: $deploymentUuid,
            force_rebuild: false,
        );

        if (data_get($result, 'status') === 'queue_full') {
            $this->dispatch('toast', ['type' => 'error', 'message' => data_get($result, 'message', 'Queue full.')]);

            return;
        }
        if (data_get($result, 'status') === 'skipped') {
            $this->dispatch('toast', ['type' => 'info', 'message' => data_get($result, 'message', 'Already deploying.')]);

            return;
        }

        $this->dispatch('toast', ['type' => 'success', 'message' => 'Deployment queued.']);
    }

    public function saveEnv(int $id): void
    {
        $envVar = EnvironmentVariable::where('id', $id)
            ->where('resourceable_type', Application::class)
            ->where('resourceable_id', $this->application->id)
            ->firstOrFail();

        $envVar->update(['value' => (string) ($this->envEdits[$id] ?? '')]);
        $this->dispatch('toast', ['type' => 'success', 'message' => "Updated {$envVar->key}."]);
    }

    public function deleteEnv(int $id): void
    {
        EnvironmentVariable::where('id', $id)
            ->where('resourceable_type', Application::class)
            ->where('resourceable_id', $this->application->id)
            ->delete();
        unset($this->envEdits[$id]);
        $this->application->load('environment_variables');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Removed.']);
    }

    public function addEnv(): void
    {
        $this->validate([
            'newEnvKey' => ['required', 'string', 'regex:/^[A-Z_][A-Z0-9_]*$/'],
            'newEnvValue' => ['required', 'string'],
        ], [
            'newEnvKey.regex' => 'Env var keys must be uppercase letters / digits / underscores.',
        ]);

        EnvironmentVariable::create([
            'key' => $this->newEnvKey,
            'value' => $this->newEnvValue,
            'resourceable_type' => Application::class,
            'resourceable_id' => $this->application->id,
            'is_runtime' => true,
            'is_buildtime' => true,
        ]);

        $this->newEnvKey = '';
        $this->newEnvValue = '';
        $this->application->load('environment_variables');
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Env var added.']);
    }

    public function render()
    {
        return view('livewire.client.application.show');
    }
}
