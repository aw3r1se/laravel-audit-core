<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Relations\Concerns;

use Aw3r1se\Audit\AuditContext;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Relations\HasOneOrMany
 */
trait RecordsHasOneOrMany
{
    use IgnoresAuditRelations;

    protected bool $auditSilent = false;

    protected ?string $auditRelationName = null;

    public function setAuditRelationName(?string $name): void
    {
        $this->auditRelationName = $name;
    }

    public function save(Model $model): Model|false
    {
        return $this->recordSingle(fn () => parent::save($model));
    }

    /**
     * @param  iterable<int, Model>  $models
     * @return iterable<int, Model>
     */
    public function saveMany($models): array
    {
        return $this->recordBatch(fn () => parent::saveMany($models));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes = []): Model
    {
        return $this->recordSingle(fn () => parent::create($attributes));
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $records
     * @return Collection<int, Model>
     */
    public function createMany(iterable $records): Collection
    {
        return $this->recordBatch(fn () => parent::createMany($records));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function forceCreate(array $attributes = []): Model
    {
        return $this->recordSingle(fn () => parent::forceCreate($attributes));
    }

    /**
     * @param  iterable<int, array<string, mixed>>  $records
     * @return Collection<int, Model>
     */
    public function forceCreateMany(iterable $records): Collection
    {
        return $this->recordBatch(fn () => parent::forceCreateMany($records));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  Closure|array<string, mixed>  $values
     */
    public function firstOrCreate(array $attributes = [], Closure|array $values = []): Model
    {
        return $this->recordIfCreated(fn () => parent::firstOrCreate($attributes, $values));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  Closure|array<string, mixed>  $values
     */
    public function createOrFirst(array $attributes = [], Closure|array $values = []): Model
    {
        return $this->recordIfCreated(fn () => parent::createOrFirst($attributes, $values));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $values
     */
    public function updateOrCreate(array $attributes, array $values = []): Model
    {
        return $this->recordIfCreated(fn () => parent::updateOrCreate($attributes, $values));
    }

    /**
     * @template T
     *
     * @param  callable(): T  $mutation
     * @return T
     */
    private function recordSingle(callable $mutation): mixed
    {
        $silent = $this->auditSilent;
        $result = $mutation();

        if (!$silent && $result instanceof Model && $result->exists) {
            $this->recordRelationAudit('relation_attached', [
                'attached' => [$this->auditIdentify($result)],
            ]);
        }

        return $result;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $mutation
     * @return T
     */
    private function recordBatch(callable $mutation): mixed
    {
        $this->auditSilent = true;
        try {
            $result = $mutation();
        } finally {
            $this->auditSilent = false;
        }

        // Always identify per model (instead of Collection::modelKeys()) so weak
        // entities fall back to an attribute snapshot rather than a null key.
        $ids = $this->extractKeys(is_iterable($result) ? $result : []);

        if ($ids !== []) {
            $this->recordRelationAudit('relation_attached', [
                'attached' => $ids,
            ]);
        }

        return $result;
    }

    /**
     * @param  callable(): mixed  $mutation
     */
    private function recordIfCreated(callable $mutation): Model
    {
        $this->auditSilent = true;
        try {
            $result = $mutation();
        } finally {
            $this->auditSilent = false;
        }

        if ($result instanceof Model && $result->wasRecentlyCreated) {
            $this->recordRelationAudit('relation_attached', [
                'attached' => [$this->auditIdentify($result)],
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recordRelationAudit(string $action, array $payload): void
    {
        if ($this->relationAuditIgnored($this->parent, $this->auditRelationName)) {
            return;
        }

        /** @var AuditContext $context */
        $context = resolve(AuditContext::class);

        $context->recordRelation(
            $this->parent,
            $this->auditRelationName ?? '',
            $this->related::class,
            $action,
            $payload,
        );
    }

    /**
     * Identify a related model for the audit log: its key, or an attribute
     * snapshot when it is a weak entity with no usable key.
     */
    protected function auditIdentify(Model $model): mixed
    {
        return resolve(AuditContext::class)->identify($model);
    }

    /**
     * @param  iterable<int, mixed>  $models
     * @return list<mixed>
     */
    protected function extractKeys(iterable $models): array
    {
        $ids = [];
        foreach ($models as $model) {
            if ($model instanceof Model && $model->exists) {
                $ids[] = $this->auditIdentify($model);
            }
        }

        return $ids;
    }
}
