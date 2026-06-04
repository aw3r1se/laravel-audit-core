<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Relations\Concerns;

use Aw3r1se\Audit\AuditContext;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin \Illuminate\Database\Eloquent\Relations\BelongsTo
 */
trait RecordsBelongsTo
{
    use IgnoresAuditRelations;

    /**
     * @param  Model|int|string|null  $model
     */
    public function associate($model): Model
    {
        return $this->withFkDiff(fn () => parent::associate($model));
    }

    public function dissociate(): Model
    {
        return $this->withFkDiff(fn () => parent::dissociate());
    }

    /**
     * @param  callable(): Model  $mutation
     */
    private function withFkDiff(callable $mutation): Model
    {
        $previous = $this->child->getAttribute($this->foreignKey);
        $result = $mutation();
        $current = $this->child->getAttribute($this->foreignKey);

        if ((string) $previous === (string) $current) {
            return $result;
        }

        if ($previous !== null) {
            $this->recordRelationAudit('relation_detached', [
                'detached' => [$previous],
            ]);
        }

        if ($current !== null) {
            $this->recordRelationAudit('relation_attached', [
                'attached' => [$current],
            ]);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function recordRelationAudit(string $action, array $payload): void
    {
        if ($this->relationAuditIgnored($this->child, $this->getRelationName())) {
            return;
        }

        /** @var AuditContext $context */
        $context = resolve(AuditContext::class);

        $context->recordRelation(
            $this->child,
            $this->getRelationName(),
            $this->related::class,
            $action,
            $payload,
        );
    }
}
