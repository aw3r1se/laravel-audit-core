<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Relations;

use Aw3r1se\Audit\Relations\Concerns\RecordsRelationSync;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @template TRelatedModel of Model
 * @template TDeclaringModel of Model
 *
 * @extends MorphToMany<TRelatedModel, TDeclaringModel>
 */
class AuditableMorphToMany extends MorphToMany
{
    use RecordsRelationSync;
}
