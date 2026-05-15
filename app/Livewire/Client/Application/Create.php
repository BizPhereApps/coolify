<?php

namespace App\Livewire\Client\Application;

use App\Models\Application;
use App\Models\StandaloneDocker;
use App\Models\SubTeam;
use App\Rules\ValidGitBranch;
use App\Rules\ValidGitRepositoryUrl;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.simple')]
class Create extends Component
{
    public SubTeam $subTeam;

    public string $name = '';

    public string $git_repository = '';

    public string $git_branch = 'main';

    public string $ports_exposes = '3000';

    public string $build_pack = 'nixpacks';

    public int $appsUsed = 0;

    public int $appsLimit = 0;

    public function mount(): void
    {
        $user = auth()->user();
        abort_unless($user, 401);

        $subTeam = SubTeam::with('offer.server', 'project')
            ->where('client_user_id', $user->id)
            ->whereNull('terminated_at')
            ->first();
        abort_unless($subTeam, 404, 'No active hosting.');
        abort_unless($subTeam->project_id, 404, 'Project not provisioned yet.');

        $this->subTeam = $subTeam;
        $this->appsLimit = (int) ($subTeam->offer?->max_apps ?? 0);
        $this->appsUsed = Application::whereRelation('environment', 'project_id', $subTeam->project_id)->count();

        if ($this->appsLimit > 0 && $this->appsUsed >= $this->appsLimit) {
            // Don't immediately abort — let the view render the over-quota message
            // and a "Back" button. The submit() guard repeats the check.
        }
    }

    public function isOverQuota(): bool
    {
        return $this->appsLimit > 0 && $this->appsUsed >= $this->appsLimit;
    }

    public function submit(): mixed
    {
        if ($this->isOverQuota()) {
            $this->addError('quota', "Your hosting plan allows a maximum of {$this->appsLimit} application(s). Contact your developer to upgrade.");

            return null;
        }

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[a-zA-Z0-9-_]+$/'],
            'git_repository' => ['required', 'string', new ValidGitRepositoryUrl],
            'git_branch' => ['required', 'string', new ValidGitBranch],
            'ports_exposes' => ['required', 'string', 'regex:/^\d{1,5}(?:,\d{1,5})*$/'],
            'build_pack' => ['required', 'string', 'in:nixpacks,dockerfile,dockerimage,static'],
        ], [
            'name.regex' => 'Name can only contain letters, digits, dashes, and underscores.',
            'ports_exposes.regex' => 'Ports must be a comma-separated list of numbers (e.g. 3000 or 3000,8080).',
        ]);

        $server = $this->subTeam->offer->server;
        $destination = StandaloneDocker::where('server_id', $server->id)->first();
        if (! $destination) {
            $this->addError('quota', 'The server has no Docker destination configured. Contact your developer.');

            return null;
        }

        // Pull the project's auto-created 'production' environment.
        $environment = \App\Models\Environment::where('project_id', $this->subTeam->project_id)
            ->orderBy('id')
            ->first();
        if (! $environment) {
            $this->addError('quota', 'No environment found for this project. Contact your developer.');

            return null;
        }

        $application = Application::create([
            'name' => $this->name,
            'description' => 'Created by client via Nolbase marketplace.',
            'git_repository' => $this->git_repository,
            'git_branch' => $this->git_branch,
            'ports_exposes' => $this->ports_exposes,
            'build_pack' => $this->build_pack,
            'environment_id' => $environment->id,
            'destination_id' => $destination->id,
            'destination_type' => StandaloneDocker::class,
        ]);

        return $this->redirect(route('client.application.show', $application->uuid));
    }

    public function render()
    {
        return view('livewire.client.application.create');
    }
}
