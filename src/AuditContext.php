<?php

declare(strict_types=1);

namespace Aw3r1se\Audit;

use Aw3r1se\Audit\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * Request-scoped buffer of model and relation changes.
 *
 * Bound as a scoped singleton: under Octane/RoadRunner it is flushed between
 * requests, so it MUST be resolved inside each event closure, never captured
 * once at model boot time.
 */
class AuditContext
{
    /** @var list<array<string, mixed>> */
    protected array $changes = [];

    public function record(Model $model, string $action): void
    {
        $state = match ($action) {
            'created' => $model->getAttributes(),
            'updated' => $model->getChanges(),
            'deleted' => $model->getRawOriginal(),
            default => [],
        };

        $this->changes[] = [
            'model_type' => $model::class,
            'model_id' => $model->getKey(),
            'action' => $action,
            'state' => $this->filter($model, $state),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordRelation(
        Model $parent,
        string $relation,
        string $relatedType,
        string $action,
        array $payload,
    ): void {
        $this->changes[] = [
            'model_type' => $parent::class,
            'model_id' => $parent->getKey(),
            'action' => $action,
            'state' => [
                'relation' => $relation,
                'related_type' => $relatedType,
                ...$payload,
            ],
        ];
    }

    /**
     * Drain and reset the buffer.
     *
     * @return list<array<string, mixed>>
     */
    public function pull(): array
    {
        $changes = $this->changes;
        $this->changes = [];

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function filter(Model $model, array $attributes): array
    {
        $excluded = array_merge($this->ignoredFieldsFor($model), $model->getHidden());

        return array_filter(
            $attributes,
            static fn (string $key): bool => !in_array($key, $excluded, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * @return list<string>
     */
    protected function ignoredFieldsFor(Model $model): array
    {
        return $model instanceof Auditable ? $model->getAuditIgnoredFields() : [];
    }
}
