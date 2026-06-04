<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Relations\Concerns;

use Aw3r1se\Audit\AuditContext;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as BaseCollection;

/**
 * @mixin \Illuminate\Database\Eloquent\Relations\BelongsToMany
 */
trait RecordsRelationSync
{
    use IgnoresAuditRelations;

    protected bool $auditSilent = false;

    /**
     * @param  mixed  $ids
     * @param  array<string, mixed>  $attributes
     */
    public function attach($ids, array $attributes = [], $touch = true): void
    {
        $silent = $this->auditSilent;
        $resolved = $silent ? [] : $this->extractIds($ids);

        parent::attach($ids, $attributes, $touch);

        if (!$silent && $resolved !== []) {
            $this->recordRelationAudit('relation_attached', [
                'attached' => $resolved,
                ...($attributes === [] ? [] : ['pivot' => $attributes]),
            ]);
        }
    }

    /**
     * @param  mixed  $ids
     */
    public function detach($ids = null, $touch = true): int
    {
        // Resolve the affected ids only when we will actually record them, so an
        // audit-ignored detach(null) does not run an extra pivot query.
        $record = !$this->auditSilent
            && !$this->relationAuditIgnored($this->parent, $this->getRelationName());
        $resolved = $record ? $this->resolveDetachIds($ids) : [];

        $result = parent::detach($ids, $touch);

        if ($record && $resolved !== []) {
            $this->recordRelationAudit('relation_detached', [
                'detached' => $resolved,
            ]);
        }

        return $result;
    }

    /**
     * @param  mixed  $ids
     * @return array{attached: array<int, mixed>, detached: array<int, mixed>, updated: array<int, mixed>}
     */
    public function sync($ids, $detaching = true): array
    {
        $this->auditSilent = true;
        try {
            $changes = parent::sync($ids, $detaching);
        } finally {
            $this->auditSilent = false;
        }

        if ($changes['attached'] !== [] || $changes['detached'] !== [] || $changes['updated'] !== []) {
            $this->recordRelationAudit('relation_synced', $changes);
        }

        return $changes;
    }

    /**
     * @param  mixed  $ids
     * @return array{attached: array<int, mixed>, detached: array<int, mixed>}
     */
    public function toggle($ids, $touch = true): array
    {
        $this->auditSilent = true;
        try {
            $changes = parent::toggle($ids, $touch);
        } finally {
            $this->auditSilent = false;
        }

        if ($changes['attached'] !== [] || $changes['detached'] !== []) {
            $this->recordRelationAudit('relation_toggled', $changes);
        }

        return $changes;
    }

    /**
     * @param  mixed  $id
     * @param  array<string, mixed>  $attributes
     */
    public function updateExistingPivot($id, array $attributes, $touch = true): int
    {
        $silent = $this->auditSilent;

        $result = parent::updateExistingPivot($id, $attributes, $touch);

        if (!$silent && $result > 0) {
            $this->recordRelationAudit('relation_pivot_updated', [
                'updated' => $this->extractIds($id),
                'pivot' => $attributes,
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recordRelationAudit(string $action, array $payload): void
    {
        if ($this->relationAuditIgnored($this->parent, $this->getRelationName())) {
            return;
        }

        /** @var AuditContext $context */
        $context = resolve(AuditContext::class);

        $context->recordRelation(
            $this->parent,
            $this->getRelationName() ?? '',
            $this->related::class,
            $action,
            $payload,
        );
    }

    /**
     * @param  mixed  $ids
     * @return list<int|string>
     */
    protected function extractIds($ids): array
    {
        if ($ids === null) {
            return [];
        }

        if ($ids instanceof Model) {
            return [$ids->{$this->relatedKey}];
        }

        if ($ids instanceof EloquentCollection) {
            return $ids->pluck($this->relatedKey)->all();
        }

        if ($ids instanceof BaseCollection) {
            $ids = $ids->all();
        }

        if (!is_array($ids)) {
            return [$this->normalizeId($ids)];
        }

        $isAssoc = array_keys($ids) !== range(0, count($ids) - 1);
        $keys = $isAssoc ? array_keys($ids) : $ids;

        return array_values(array_map(fn ($id) => $this->normalizeId($id), $keys));
    }

    protected function normalizeId(mixed $id): int|string
    {
        return $id instanceof BackedEnum ? $id->value : $id;
    }

    /**
     * @param  mixed  $ids
     * @return list<int|string>
     */
    protected function resolveDetachIds($ids): array
    {
        if ($ids === null) {
            return $this->newPivotQuery()
                ->pluck($this->relatedPivotKey)
                ->all();
        }

        return $this->extractIds($ids);
    }
}
