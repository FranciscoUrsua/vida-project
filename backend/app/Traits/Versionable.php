<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

trait Versionable
{
    /**
     * Boot the trait and register events.
     */
    protected static function bootVersionable()
    {
        static::created(function (Model $model) {
            static::createVersion($model, 1, 'Creación inicial');
        });

        static::updating(function (Model $model) {
            $latestVersion = static::getLatestVersionNumber($model);
            static::createVersion($model, $latestVersion + 1, static::getChangesReason($model));
        });
    }

    /**
     * Create a new version snapshot in the versions table.
     *
     * @param Model $model
     * @param int $version
     * @param string $reason
     * @return void
     */
    protected static function createVersion(Model $model, int $version, string $reason = ''): void
    {
        $data = $model->toArray();
        // Exclude timestamps and soft deletes to avoid noise in history
        unset($data['created_at'], $data['updated_at'], $data['deleted_at']);

        DB::table('versions')->insert([
            'versionable_id' => $model->getKey(),
            'versionable_type' => get_class($model),
            'version' => $version,
            'data' => json_encode($data), // Snapshot as JSON
            'changed_by' => Auth::id() ?? null, // AppUser ID or null in seeds
            'change_reason' => $reason,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Get the latest version number for the model.
     *
     * @param Model $model
     * @return int
     */
    protected static function getLatestVersionNumber(Model $model): int
    {
        return DB::table('versions')
            ->where('versionable_id', $model->getKey())
            ->where('versionable_type', get_class($model))
            ->max('version') ?? 0;
    }

    /**
     * Get a reason for the change (override in model if needed).
     *
     * @param Model $model
     * @return string
     */
    protected static function getChangesReason(Model $model): string
    {
        $changes = $model->getChanges();
        if (empty($changes)) {
            return 'Actualización menor';
        }

        $fields = array_keys($changes);
        $reason = 'Cambio en: ' . implode(', ', $fields);

        // Custom reasons (override in model)
        if (in_array('numero_id', $fields)) {
            $reason .= ' (cambio de documento de identidad)';
        }

        return $reason;
    }

    /**
     * Get all versions for this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function versions()
    {
        return $this->hasMany(Version::class, 'versionable_id', $this->getKeyName())
            ->where('versionable_type', get_class($this))
            ->orderBy('version', 'asc');
    }

    /**
     * Get the latest version for this model.
     *
     * @return Version|null
     */
    public function latestVersion()
    {
        return $this->versions()->latest('version')->first();
    }

    /**
     * Get changes between two versions.
     *
     * @param int $oldVersion
     * @param int $newVersion
     * @return array|null
     */
    public function getChangesBetweenVersions(int $oldVersion, int $newVersion): ?array
    {
        $old = $this->versions()->where('version', $oldVersion)->first();
        $new = $this->versions()->where('version', $newVersion)->first();

        if (!$old || !$new) {
            return null;
        }

        $oldData = $old->data;
        $newData = $new->data;

        return array_diff_assoc($newData, $oldData); // Keys changed/added
    }
}
