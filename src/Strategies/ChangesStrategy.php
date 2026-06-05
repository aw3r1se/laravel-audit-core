<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Strategies;

use Aw3r1se\Audit\AuditContext;
use Aw3r1se\Audit\Contracts\AuditStrategy;
use Illuminate\Database\Eloquent\Model;

/**
 * The default strategy: records the diff of a model event — new attributes on
 * create, changed attributes on update, the original row on delete. Captures
 * deletions on `deleted` (after the row is gone) and ignores `deleting`.
 */
final class ChangesStrategy implements AuditStrategy
{
    public function capture(AuditContext $context, Model $model, string $event): ?array
    {
        $state = match ($event) {
            'created' => $model->getAttributes(),
            'updated' => $model->getChanges(),
            'deleted' => $model->getRawOriginal(),
            default => null,
        };

        return $state === null ? null : $context->filter($model, $state);
    }
}
