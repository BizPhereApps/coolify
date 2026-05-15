<?php

namespace App\Livewire\Client\Application;

use App\Models\Application;
use App\Models\EnvironmentVariable;
use App\Models\SubTeam;
use App\Rules\ValidHostname;
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

    public string $customDomain = '';

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
        // Strip scheme for the editable field; we'll re-add on save.
        $this->customDomain = str_replace(['https://', 'http://'], '', $application->fqdn ?? '');
    }

    public function getServerIpProperty(): ?string
    {
        return $this->subTeam->offer?->server?->ip;
    }

    public function getCustomDomainAllowedProperty(): bool
    {
        return (bool) ($this->subTeam->offer?->allow_custom_domain ?? false);
    }

    public function saveCustomDomain(): void
    {
        if (! $this->customDomainAllowed) {
            $this->addError('customDomain', 'Custom domains are not enabled on your hosting plan.');

            return;
        }

        $value = trim($this->customDomain);

        // Clearing the domain — back to whatever Coolify's default routing gives.
        if ($value === '') {
            $this->application->update(['fqdn' => null]);
            $this->dispatch('toast', ['type' => 'success', 'message' => 'Custom domain removed.']);

            return;
        }

        // Allow user to paste "https://foo.com" or just "foo.com"; normalize to hostname.
        $value = str_replace(['https://', 'http://'], '', $value);
        $value = rtrim($value, '/');
        // Reflect the normalized value back to the component property so
        // $this->validate() sees the cleaned hostname (not the pasted URL).
        $this->customDomain = $value;

        $this->validate([
            'customDomain' => ['required', 'string', new ValidHostname],
        ], [], ['customDomain' => 'custom domain']);

        // Guard against the same FQDN already being used by another Application
        // on this team's server. Two apps fighting for the same domain via
        // Traefik silently routes to whoever registered first — protect the
        // Client from a confusing outcome.
        $conflict = Application::query()
            ->where('fqdn', 'https://'.$value)
            ->where('id', '!=', $this->application->id)
            ->whereHas('environment.project', fn ($q) => $q->where('team_id', $this->subTeam->parent_team_id))
            ->exists();
        if ($conflict) {
            $this->addError('customDomain', "{$value} is already routed to another application on this server.");

            return;
        }

        $this->application->update(['fqdn' => 'https://'.$value]);
        $this->customDomain = $value;
        $this->dispatch('toast', ['type' => 'success', 'message' => 'Custom domain saved. Redeploy to provision the TLS certificate.']);
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
