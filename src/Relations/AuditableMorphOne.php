<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Relations;

use Aw3r1se\Audit\Relations\Concerns\RecordsHasOneOrMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends MorphOne<TRelatedModel, TDeclaringModel>
 */
class AuditableMorphOne extends MorphOne
{
    use RecordsHasOneOrMany;
}
