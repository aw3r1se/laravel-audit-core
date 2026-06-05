<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Strategies;

use Aw3r1se\Audit\AuditContext;
use Aw3r1se\Audit\Contracts\Auditable;
use Aw3r1se\Audit\Contracts\AuditStrategy;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Records a full picture of the model rather than a diff: its filtered
 * attributes plus a recursive snapshot of the relations it declares via
 * {@see Auditable::getAuditSnapshotRelations()} (dot-notation for nesting).
 *
 * Deletions are captured on `deleting` — while relations are still attached —
 * so the `deleted` event is ignored to avoid a duplicate, emptier entry.
 */
final class SnapshotStrategy implements AuditStrategy
{
    /** @var list<string> */
    private const array EVENTS = ['created', 'updated', 'deleting'];

    public function capture(AuditContext $context, Model $model, string $event): ?array
    {
        if (!in_array($event, self::EVENTS, true)) {
            return null;
        }

        return [
            'attributes' => $context->filter($model, $model->getAttributes()),
            'relations' => $this->relations($context, $model),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function relations(AuditContext $context, Model $model): array
    {
        $declared = $model instanceof Auditable ? $model->getAuditSnapshotRelations() : [];

        if ($declared === []) {
            return [];
        }

        return $this->serialize($context, $model, $this->tree($declared));
    }

    /**
     * @param  array<string, array<string, mixed>>  $tree
     * @return array<string, mixed>
     */
    protected function serialize(AuditContext $context, Model $model, array $tree): array
    {
        $out = [];
        foreach ($tree as $name => $children) {
            // Query each relation fresh (the relation *method*, not the cached
            // property) so the snapshot reflects current persisted state and we
            // never read or pollute the model's loaded-relation cache — a stale
            // cache would otherwise leak between snapshots of the same instance.
            $out[$name] = $this->value($context, $model->{$name}()->getResults(), $children);
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $children
     */
    protected function value(AuditContext $context, mixed $related, array $children): mixed
    {
        if ($related instanceof Model) {
            return $this->model($context, $related, $children);
        }

        if ($related instanceof Collection) {
            return $related->map(fn (Model $model) => $this->model($context, $model, $children))
                ->values()
                ->all();
        }

        return null;
    }

    /**
     * @param  array<string, array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    protected function model(AuditContext $context, Model $model, array $children): array
    {
        $data = $context->filter($model, $model->getAttributes());

        if ($children !== []) {
            $data['relations'] = $this->serialize($context, $model, $children);
        }

        return $data;
    }

    /**
     * Turn a flat dot-notation list into a nested tree:
     * `['author', 'comments.replies']` => `['author' => [], 'comments' => ['replies' => []]]`.
     *
     * @param  list<string>  $relations
     * @return array<string, array<string, mixed>>
     */
    protected function tree(array $relations): array
    {
        $tree = [];

        foreach ($relations as $relation) {
            $cursor = &$tree;
            foreach (explode('.', $relation) as $segment) {
                $cursor[$segment] ??= [];
                $cursor = &$cursor[$segment];
            }
            unset($cursor);
        }

        return $tree;
    }
}
