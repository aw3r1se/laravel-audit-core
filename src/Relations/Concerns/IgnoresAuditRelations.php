<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Relations\Concerns;

use Aw3r1se\Audit\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

trait IgnoresAuditRelations
{
    protected function relationAuditIgnored(Model $model, ?string $relationName): bool
    {
        if ($relationName === null || $relationName === '') {
            return false;
        }

        return $model instanceof Auditable
            && in_array($relationName, $model->getAuditIgnoredRelations(), true);
    }
}
