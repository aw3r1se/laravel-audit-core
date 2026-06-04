<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Concerns;

use Aw3r1se\Audit\AuditContext;
use Aw3r1se\Audit\Relations\AuditableBelongsTo;
use Aw3r1se\Audit\Relations\AuditableBelongsToMany;
use Aw3r1se\Audit\Relations\AuditableHasMany;
use Aw3r1se\Audit\Relations\AuditableHasOne;
use Aw3r1se\Audit\Relations\AuditableMorphMany;
use Aw3r1se\Audit\Relations\AuditableMorphOne;
use Aw3r1se\Audit\Relations\AuditableMorphToMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Records created/updated/deleted state and routes relation mutations through
 * auditing relation classes. Implement {@see \Aw3r1se\Audit\Contracts\Auditable}
 * on the host model.
 *
 * @mixin Model
 */
trait InteractsWithAudit
{
    public static function bootInteractsWithAudit(): void
    {
        // Resolve the (scoped) context inside each closure so it stays correct
        // across Octane requests — never capture it in this once-per-worker boot.
        static::created(static fn (Model $model) => resolve(AuditContext::class)->record($model, 'created'));
        static::updated(static fn (Model $model) => resolve(AuditContext::class)->record($model, 'updated'));
        static::deleted(static fn (Model $model) => resolve(AuditContext::class)->record($model, 'deleted'));
    }

    public function auditLabel(): string
    {
        $name = $this->getAttribute('name');

        return is_string($name) && $name !== ''
            ? $name
            : '#' . $this->getKey();
    }

    /**
     * Global defaults from config, plus the optional per-model
     * `$auditIgnoredFields` property. Override this method only when a model
     * needs to replace the defaults entirely.
     *
     * @return list<string>
     */
    public function getAuditIgnoredFields(): array
    {
        /** @var list<string> $defaults */
        $defaults = config('audit.ignored_fields', ['created_at', 'updated_at']);

        /** @var list<string> $own */
        $own = $this->auditIgnoredFields ?? [];

        return array_values(array_unique([...$defaults, ...$own]));
    }

    /**
     * Reads the optional per-model `$auditIgnoredRelations` property. Override
     * this method instead for computed values.
     *
     * @return list<string>
     */
    public function getAuditIgnoredRelations(): array
    {
        /** @var list<string> $own */
        $own = $this->auditIgnoredRelations ?? [];

        return $own;
    }

    /**
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     */
    protected function newBelongsToMany(
        Builder $query,
        Model $parent,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
    ): AuditableBelongsToMany {
        return new AuditableBelongsToMany(
            $query,
            $parent,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
        );
    }

    /**
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     * @param  bool  $inverse
     */
    protected function newMorphToMany(
        Builder $query,
        Model $parent,
        $name,
        $table,
        $foreignPivotKey,
        $relatedPivotKey,
        $parentKey,
        $relatedKey,
        $relationName = null,
        $inverse = false,
    ): AuditableMorphToMany {
        return new AuditableMorphToMany(
            $query,
            $parent,
            $name,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey,
            $relatedKey,
            $relationName,
            $inverse,
        );
    }

    /**
     * @param  string  $foreignKey
     * @param  string  $localKey
     */
    protected function newHasMany(Builder $query, Model $parent, $foreignKey, $localKey): AuditableHasMany
    {
        $relation = new AuditableHasMany($query, $parent, $foreignKey, $localKey);
        $relation->setAuditRelationName($this->guessAuditRelationName());

        return $relation;
    }

    /**
     * @param  string  $foreignKey
     * @param  string  $localKey
     */
    protected function newHasOne(Builder $query, Model $parent, $foreignKey, $localKey): AuditableHasOne
    {
        $relation = new AuditableHasOne($query, $parent, $foreignKey, $localKey);
        $relation->setAuditRelationName($this->guessAuditRelationName());

        return $relation;
    }

    /**
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     */
    protected function newMorphMany(Builder $query, Model $parent, $type, $id, $localKey): AuditableMorphMany
    {
        $relation = new AuditableMorphMany($query, $parent, $type, $id, $localKey);
        $relation->setAuditRelationName($this->guessAuditRelationName());

        return $relation;
    }

    /**
     * @param  string  $type
     * @param  string  $id
     * @param  string  $localKey
     */
    protected function newMorphOne(Builder $query, Model $parent, $type, $id, $localKey): AuditableMorphOne
    {
        $relation = new AuditableMorphOne($query, $parent, $type, $id, $localKey);
        $relation->setAuditRelationName($this->guessAuditRelationName());

        return $relation;
    }

    /**
     * @param  string  $foreignKey
     * @param  string  $ownerKey
     * @param  string  $relation
     */
    protected function newBelongsTo(
        Builder $query,
        Model $child,
        $foreignKey,
        $ownerKey,
        $relation,
    ): AuditableBelongsTo {
        return new AuditableBelongsTo($query, $child, $foreignKey, $ownerKey, $relation);
    }

    /**
     * Recover the calling relation method name (the relation name) from the
     * stack, skipping the internal relation factory frames.
     */
    protected function guessAuditRelationName(): ?string
    {
        $internal = [
            'newHasMany', 'newHasOne', 'newMorphMany', 'newMorphOne',
            'hasMany', 'hasOne', 'morphMany', 'morphOne',
            'guessAuditRelationName',
        ];

        $flags = DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS;
        foreach (debug_backtrace($flags, 15) as $frame) {
            $function = $frame['function'] ?? null;
            if ($function === null) {
                continue;
            }
            if (($frame['object'] ?? null) !== $this) {
                continue;
            }
            if (in_array($function, $internal, true)) {
                continue;
            }

            return $function;
        }

        return null;
    }
}
