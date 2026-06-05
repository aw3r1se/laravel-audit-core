<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Contracts;

use Aw3r1se\Audit\AuditContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Decides *how* a model event is turned into recorded state.
 *
 * A strategy only shapes the `state` of a model's own create/update/delete
 * entry; relation-mutation recording (attach/detach/sync) is orthogonal and
 * runs regardless. The context resolves a strategy per model (a per-model
 * override, else `config('audit.strategy')`), so custom strategies can be
 * plugged in without touching the package.
 */
interface AuditStrategy
{
    /**
     * Build the `state` for a model event, or return null to ignore the event.
     *
     * Called for the `created`, `updated`, `deleting` and `deleted` events;
     * a strategy returns null for the events it does not handle (e.g. the diff
     * strategy ignores `deleting`, the snapshot strategy ignores `deleted`).
     *
     * @return array<string, mixed>|null
     */
    public function capture(AuditContext $context, Model $model, string $event): ?array;
}
