<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Relations;

use Aw3r1se\Audit\Relations\Concerns\RecordsRelationSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends BelongsToMany<TRelatedModel, TDeclaringModel>
 */
class AuditableBelongsToMany extends BelongsToMany
{
    use RecordsRelationSync;
}
