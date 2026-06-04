<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Relations;

use Aw3r1se\Audit\Relations\Concerns\RecordsBelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends BelongsTo<TRelatedModel, TDeclaringModel>
 */
class AuditableBelongsTo extends BelongsTo
{
    use RecordsBelongsTo;
}
