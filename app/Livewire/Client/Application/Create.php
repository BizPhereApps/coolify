<?php

namespace App\Livewire\Client\Application;

use App\Models\Application;
use App\Models\GithubApp;
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

    /**
     * null = public Git URL (default). Integer = id of a GithubApp owned by
     * the Developer's parent team that the Client may deploy private repos
     * through.
     */
    public ?int $sourceId = null;

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

    /**
     * GitHub Apps that the Developer (parent team) has installed and that the
     * Client may deploy private repos through. Strictly scoped to the parent
     * team — Client cannot pick another tenant's source.
     */
    public function getAvailableGithubAppsProperty()
    {
        return GithubApp::query()
            ->where('team_id', $this->subTeam->parent_team_id)
            ->orderBy('name')
            ->get(['id', 'name', 'organization', 'html_url']);
    }

    public function submit(): mixed
    {
        if ($this->isOverQuota()) {
            $this->addError('quota', "Your hosting plan allows a maximum of {$this->appsLimit} application(s). Contact your developer to upgrade.");

            return null;
        }

        // Validation differs based on whether the Client is using a public
        // Git URL (any ValidGitRepositoryUrl) or routing through the
        // Developer's GitHub App (expects "owner/repo" — Coolify resolves
        // via the GitHub App's installation token at clone time).
        if ($this->sourceId === null) {
            $repoRule = ['required', 'string', new ValidGitRepositoryUrl];
        } else {
            $repoRule = ['required', 'string', 'regex:/^[A-Za-z0-9_.-]+\/[A-Za-z0-9_.-]+$/'];
        }

        $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:64', 'regex:/^[a-zA-Z0-9-_]+$/'],
            'git_repository' => $repoRule,
            'git_branch' => ['required', 'string', new ValidGitBranch],
            'ports_exposes' => ['required', 'string', 'regex:/^\d{1,5}(?:,\d{1,5})*$/'],
            'build_pack' => ['required', 'string', 'in:nixpacks,dockerfile,dockerimage,static'],
        ], [
            'name.regex' => 'Name can only contain letters, digits, dashes, and underscores.',
            'ports_exposes.regex' => 'Ports must be a comma-separated list of numbers (e.g. 3000 or 3000,8080).',
            'git_repository.regex' => 'For private GitHub repos, use the "owner/repo" format (e.g. bola/shop).',
        ]);

        // Cross-team source guard: if the Client somehow submits a source_id
        // that doesn't belong to their Developer's team, reject it. The
        // dropdown already only lists their team's apps but we re-validate
        // server-side because the property is publicly bindable.
        $source = null;
        if ($this->sourceId !== null) {
            $source = GithubApp::where('team_id', $this->subTeam->parent_team_id)
                ->where('id', $this->sourceId)
                ->first();
            if (! $source) {
                $this->addError('sourceId', 'Selected Git source is not available on your hosting.');

                return null;
            }
        }

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

        $application = Application::create(array_filter([
            'name' => $this->name,
            'description' => 'Created by client via Nolbase marketplace.',
            'git_repository' => $this->git_repository,
            'git_branch' => $this->git_branch,
            'ports_exposes' => $this->ports_exposes,
            'build_pack' => $this->build_pack,
            'environment_id' => $environment->id,
            'destination_id' => $destination->id,
            'destination_type' => StandaloneDocker::class,
            'source_id' => $source?->id,
            'source_type' => $source ? GithubApp::class : null,
        ], static fn ($v) => $v !== null));

        return $this->redirect(route('client.application.show', $application->uuid));
    }

    public function render()
    {
        return view('livewire.client.application.create');
    }
}
