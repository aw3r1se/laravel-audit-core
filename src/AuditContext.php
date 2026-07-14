<?php

declare(strict_types=1);

namespace Aw3r1se\Audit;

use Aw3r1se\Audit\Contracts\Auditable;
use Aw3r1se\Audit\Contracts\AuditStrategy;
use Aw3r1se\Audit\Strategies\ChangesStrategy;
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

    /** @var array<string, AuditStrategy> */
    protected array $strategies = [];

    /**
     * Record a model event. How the state is shaped (a diff or a full snapshot)
     * is delegated to the {@see AuditStrategy} resolved for the model; a null
     * return means the strategy ignores this event and nothing is buffered.
     *
     * An empty state is dropped too: once ignored and hidden attributes are
     * filtered out there is nothing auditable left, and buffering it would
     * surface as an entry with no changes.
     */
    public function record(Model $model, string $event): void
    {
        $state = $this->strategyFor($model)->capture($this, $model, $event);

        if ($state === null || $state === []) {
            return;
        }

        $this->changes[] = [
            'model' => $model,
            // 'deleting' is an internal capture hook; report it as 'deleted'.
            'action' => $event === 'deleting' ? 'deleted' : $event,
            'state' => $state,
        ];
    }

    /**
     * A stable audit identity for a model: its primary key, or — for a weak
     * entity that has no usable key — a filtered snapshot of its attributes, so
     * the change is still recorded meaningfully instead of as a null id.
     */
    public function identify(Model $model): mixed
    {
        $key = $model->getKey();

        if ($key !== null) {
            return $key;
        }

        return $this->filter($model, $model->getAttributes());
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
            'model' => $parent,
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
     * The model identity (type + id) is materialised here, not when the change
     * is buffered: a relation attached to a not-yet-persisted parent (e.g. a
     * belongsTo associated before the child's INSERT) has no key at record time
     * but does by the time the request-scoped buffer is drained.
     *
     * @return list<array<string, mixed>>
     */
    public function pull(): array
    {
        $changes = array_map(function (array $entry): array {
            /** @var Model $model */
            $model = $entry['model'];

            return [
                'model_type' => $model::class,
                'model_id' => $this->identify($model),
                'action' => $entry['action'],
                'state' => $entry['state'],
            ];
        }, $this->changes);

        $this->changes = [];

        return $changes;
    }

    /**
     * Strip ignored and hidden attributes from a state array. Public so custom
     * strategies can reuse the exact same filtering the engine applies.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public function filter(Model $model, array $attributes): array
    {
        $excluded = array_merge($this->ignoredFieldsFor($model), $model->getHidden());

        return array_filter(
            $attributes,
            static fn (string $key): bool => !in_array($key, $excluded, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Resolve the recording strategy for a model: a per-model override
     * ({@see Auditable::getAuditStrategy()}) if present, otherwise the
     * configured default. Resolved instances are cached for the request.
     */
    protected function strategyFor(Model $model): AuditStrategy
    {
        $choice = $model instanceof Auditable ? $model->getAuditStrategy() : null;
        $choice ??= config('audit.strategy', 'changes');

        return $this->strategies[$choice] ??= $this->makeStrategy((string) $choice);
    }

    /**
     * Resolve a strategy from its config key (e.g. `snapshot`) or a class-string.
     */
    protected function makeStrategy(string $choice): AuditStrategy
    {
        /** @var array<string, class-string<AuditStrategy>> $map */
        $map = config('audit.strategies', []);

        /** @var class-string<AuditStrategy> $class */
        $class = $map[$choice] ?? $choice;

        if (!is_a($class, AuditStrategy::class, true)) {
            $class = ChangesStrategy::class;
        }

        return app()->make($class);
    }

    /**
     * @return list<string>
     */
    protected function ignoredFieldsFor(Model $model): array
    {
        if ($model instanceof Auditable) {
            return $model->getAuditIgnoredFields();
        }

        // A non-Auditable model (e.g. a weak entity snapshotted via identify())
        // still benefits from the global defaults so timestamps stay out.
        /** @var list<string> $defaults */
        $defaults = config('audit.ignored_fields', []);

        return $defaults;
    }
}
