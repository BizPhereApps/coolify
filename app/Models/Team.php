<?php

namespace App\Models;

use App\Events\ServerReachabilityChanged;
use App\Notifications\Channels\SendsDiscord;
use App\Notifications\Channels\SendsEmail;
use App\Notifications\Channels\SendsPushover;
use App\Notifications\Channels\SendsSlack;
use App\Traits\HasNotificationSettings;
use App\Traits\HasSafeStringAttribute;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;
use OpenApi\Attributes as OA;

#[OA\Schema(
    description: 'Team model',
    type: 'object',
    properties: [
        'id' => ['type' => 'integer', 'description' => 'The unique identifier of the team.'],
        'name' => ['type' => 'string', 'description' => 'The name of the team.'],
        'description' => ['type' => 'string', 'description' => 'The description of the team.'],
        'personal_team' => ['type' => 'boolean', 'description' => 'Whether the team is personal or not.'],
        'created_at' => ['type' => 'string', 'description' => 'The date and time the team was created.'],
        'updated_at' => ['type' => 'string', 'description' => 'The date and time the team was last updated.'],
        'show_boarding' => ['type' => 'boolean', 'description' => 'Whether to show the boarding screen or not.'],
        'custom_server_limit' => ['type' => 'string', 'description' => 'The custom server limit.'],
        'members' => new OA\Property(
            property: 'members',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/User'),
            description: 'The members of the team.'
        ),
    ]
)]

class Team extends Model implements SendsDiscord, SendsEmail, SendsPushover, SendsSlack
{
    use HasFactory, HasNotificationSettings, HasSafeStringAttribute, Notifiable;

    protected $fillable = [
        'name',
        'description',
        'personal_team',
        'show_boarding',
        'custom_server_limit',
        'nolbase_status',
    ];

    protected $casts = [
        'personal_team' => 'boolean',
    ];

    protected static function booted()
    {
        static::created(function ($team) {
            $team->emailNotificationSettings()->create([
                'use_instance_email_settings' => isDev(),
            ]);
            $team->discordNotificationSettings()->create();
            $team->slackNotificationSettings()->create();
            $team->telegramNotificationSettings()->create();
            $team->pushoverNotificationSettings()->create();
            $team->webhookNotificationSettings()->create();
        });

        static::saving(function ($team) {
            if (auth()->user()?->isMember()) {
                throw new \Exception('You are not allowed to update this team.');
            }
        });

        static::deleting(function (Team $team) {
            foreach ($team->privateKeys as $key) {
                $key->delete();
            }

            // Transfer instance-wide sources to root team so they remain available
            GithubApp::where('team_id', $team->id)->where('is_system_wide', true)->update(['team_id' => 0]);
            GitlabApp::where('team_id', $team->id)->where('is_system_wide', true)->update(['team_id' => 0]);

            // Delete non-instance-wide sources owned by this team
            $teamSources = GithubApp::where('team_id', $team->id)->get()
                ->merge(GitlabApp::where('team_id', $team->id)->get());
            foreach ($teamSources as $source) {
                $source->delete();
            }

            foreach (Tag::whereTeamId($team->id)->get() as $tag) {
                $tag->delete();
            }

            foreach ($team->environment_variables()->get() as $sharedVariable) {
                $sharedVariable->delete();
            }

            foreach ($team->s3s as $s3) {
                $s3->delete();
            }
        });
    }

    public static function serverLimitReached(?Team $team = null)
    {
        $team = $team ?? currentTeam();
        if (! $team) {
            return true;
        }

        return ! \App\Support\PlanQuota::canAddServer($team);
    }

    public static function appLimitReached(?Team $team = null): bool
    {
        $team = $team ?? currentTeam();
        if (! $team) {
            return true;
        }

        return ! \App\Support\PlanQuota::canAddApp($team);
    }

    public static function databaseLimitReached(?Team $team = null): bool
    {
        $team = $team ?? currentTeam();
        if (! $team) {
            return true;
        }

        return ! \App\Support\PlanQuota::canAddDatabase($team);
    }

    public function canResell(): bool
    {
        if (! isCloud()) {
            return true; // self-hosted: no gating
        }

        return (bool) $this->subscription?->plan?->hasFeature('reseller');
    }

    public function subscriptionPastOverDue()
    {
        if (isCloud()) {
            return $this->subscription?->isPastDue() === true;
        }

        return false;
    }

    public function serverOverflow()
    {
        if (Team::serverLimit($this) < $this->servers->count()) {
            return true;
        }

        return false;
    }

    public static function serverLimit(?Team $team = null)
    {
        $team = $team ?? currentTeam();
        if (! $team) {
            return 0;
        }
        $team = Team::find($team->id);
        if (! $team) {
            return 0;
        }

        $limit = \App\Support\PlanQuota::serverLimit($team);

        // PlanQuota::UNLIMITED (0) is translated to a large number for legacy
        // callers that compare directly (e.g. servers.count() >= limit).
        return $limit === \App\Support\PlanQuota::UNLIMITED ? PHP_INT_MAX : $limit;
    }

    public function limits(): Attribute
    {
        return Attribute::make(
            get: function () {
                return Team::serverLimit($this);
            }
        );
    }

    public function routeNotificationForDiscord()
    {
        return data_get($this, 'discord_webhook_url', null);
    }

    public function routeNotificationForTelegram()
    {
        return [
            'token' => data_get($this, 'telegram_token', null),
            'chat_id' => data_get($this, 'telegram_chat_id', null),
        ];
    }

    public function routeNotificationForSlack()
    {
        return data_get($this, 'slack_webhook_url', null);
    }

    public function routeNotificationForPushover()
    {
        return [
            'user' => data_get($this, 'pushover_user_key', null),
            'token' => data_get($this, 'pushover_api_token', null),
        ];
    }

    public function getRecipients(): array
    {
        $recipients = $this->members()->pluck('email')->toArray();
        $validatedEmails = array_filter($recipients, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });
        if (is_null($validatedEmails)) {
            return [];
        }

        return array_values($validatedEmails);
    }

    public function isAnyNotificationEnabled()
    {
        if (isCloud()) {
            return true;
        }

        return $this->getNotificationSettings('email')?->isEnabled() ||
            $this->getNotificationSettings('discord')?->isEnabled() ||
            $this->getNotificationSettings('slack')?->isEnabled() ||
            $this->getNotificationSettings('telegram')?->isEnabled() ||
            $this->getNotificationSettings('pushover')?->isEnabled() ||
            $this->getNotificationSettings('webhook')?->isEnabled();
    }

    public function subscriptionEnded()
    {
        if (! $this->subscription) {
            return;
        }

        $this->subscription->update([
            'status' => \App\Models\Subscription::STATUS_CANCELLED,
            'paystack_subscription_code' => null,
            'cancel_at_period_end' => false,
            'cancelled_at' => now(),
        ]);
        foreach ($this->servers as $server) {
            $server->settings()->update([
                'is_usable' => false,
                'is_reachable' => false,
            ]);
            ServerReachabilityChanged::dispatch($server);
            $server->unreachable_count = 3;
            $server->unreachable_notification_sent = true;
            $server->save();
        }
    }

    public function environment_variables()
    {
        return $this->hasMany(SharedEnvironmentVariable::class)->where('type', 'team');
    }

    public function members()
    {
        return $this->belongsToMany(User::class, 'team_user', 'team_id', 'user_id')->withPivot('role');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function applications()
    {
        return $this->hasManyThrough(Application::class, Project::class);
    }

    public function invitations()
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function isEmpty()
    {
        if ($this->projects()->count() === 0 && $this->servers()->count() === 0 && $this->privateKeys()->count() === 0 && $this->sources()->count() === 0) {
            return true;
        }

        return false;
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function servers()
    {
        return $this->hasMany(Server::class);
    }

    public function privateKeys()
    {
        return $this->hasMany(PrivateKey::class);
    }

    public function cloudProviderTokens()
    {
        return $this->hasMany(CloudProviderToken::class);
    }

    public function sources()
    {
        $sources = collect([]);
        $github_apps = GithubApp::where(function ($query) {
            $query->where(function ($q) {
                $q->where('team_id', $this->id)
                    ->orWhere('is_system_wide', true);
            })->where('is_public', false);
        })->get();

        $gitlab_apps = GitlabApp::where(function ($query) {
            $query->where(function ($q) {
                $q->where('team_id', $this->id)
                    ->orWhere('is_system_wide', true);
            })->where('is_public', false);
        })->get();

        return $sources->merge($github_apps)->merge($gitlab_apps);
    }

    public function s3s()
    {
        return $this->hasMany(S3Storage::class)->where('is_usable', true);
    }

    public function emailNotificationSettings()
    {
        return $this->hasOne(EmailNotificationSettings::class);
    }

    public function discordNotificationSettings()
    {
        return $this->hasOne(DiscordNotificationSettings::class);
    }

    public function telegramNotificationSettings()
    {
        return $this->hasOne(TelegramNotificationSettings::class);
    }

    public function slackNotificationSettings()
    {
        return $this->hasOne(SlackNotificationSettings::class);
    }

    public function pushoverNotificationSettings()
    {
        return $this->hasOne(PushoverNotificationSettings::class);
    }

    public function webhookNotificationSettings()
    {
        return $this->hasOne(WebhookNotificationSettings::class);
    }
}
