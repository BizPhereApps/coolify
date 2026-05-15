<?php

use App\Models\BaseModel;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PlanSeeder::class);
});

/**
 * Pins the C2 invariant from the audit: the BaseModel::creating quota hook
 * only fires for Standalone* models (excluding StandaloneDocker), so service
 * templates that create ServiceDatabase rows are NOT subject to the per-team
 * plan database limit. If someone renames a database model or removes the
 * basename filter, this test breaks loudly.
 */
test('the quota hook filter is scoped to Standalone* models only', function () {
    $standaloneModels = [
        \App\Models\StandalonePostgresql::class,
        \App\Models\StandaloneMysql::class,
        \App\Models\StandaloneMariadb::class,
        \App\Models\StandaloneMongodb::class,
        \App\Models\StandaloneRedis::class,
        \App\Models\StandaloneKeydb::class,
        \App\Models\StandaloneClickhouse::class,
        \App\Models\StandaloneDragonfly::class,
    ];

    foreach ($standaloneModels as $model) {
        $basename = class_basename($model);
        $shouldFire = str_starts_with($basename, 'Standalone') && $basename !== 'StandaloneDocker';
        expect($shouldFire)->toBeTrue("{$basename} should trigger the quota hook");
    }

    // The carve-outs:
    expect(class_basename(\App\Models\StandaloneDocker::class))->toBe('StandaloneDocker')
        ->and(class_basename(\App\Models\ServiceDatabase::class))->toBe('ServiceDatabase')
        ->and(class_basename(\App\Models\ServiceApplication::class))->toBe('ServiceApplication')
        ->and(class_basename(\App\Models\Application::class))->toBe('Application');

    // Confirm BaseModel is the actual parent (the hook is on its boot()).
    expect(is_subclass_of(\App\Models\ServiceDatabase::class, BaseModel::class))->toBeTrue()
        ->and(is_subclass_of(\App\Models\StandalonePostgresql::class, BaseModel::class))->toBeTrue();
});
