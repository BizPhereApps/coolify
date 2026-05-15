<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Visus\Cuid2\Cuid2;

abstract class BaseModel extends Model
{
    protected static function boot()
    {
        parent::boot();

        static::creating(function (Model $model) {
            // Generate a UUID if one isn't set
            if (! $model->uuid) {
                $model->uuid = (string) new Cuid2;
            }

            // Nolbase plan-quota enforcement for standalone databases.
            // StandaloneDocker is a destination model, not a database — exclude it.
            $basename = class_basename($model);
            if (str_starts_with($basename, 'Standalone') && $basename !== 'StandaloneDocker') {
                $team = currentTeam();
                if ($team && \App\Support\PlanQuota::canAddDatabase($team) === false) {
                    $limit = \App\Support\PlanQuota::databaseLimit($team);
                    throw new \RuntimeException(
                        "Your current plan allows a maximum of {$limit} database(s). Upgrade your plan to add more."
                    );
                }
            }
        });
    }

    public function sanitizedName(): Attribute
    {
        return new Attribute(
            get: fn () => sanitize_string($this->getRawOriginal('name')),
        );
    }

    public function image(): Attribute
    {
        return new Attribute(
            get: fn () => sanitize_string($this->getRawOriginal('image')),
        );
    }
}
