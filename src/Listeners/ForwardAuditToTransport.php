<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Listeners;

use Aw3r1se\Audit\Contracts\AuditTransport;
use Aw3r1se\Audit\Events\UserActionEvent;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Forwards recorded actions to the configured transport off the request path.
 *
 * Resolves the transport inside handle() (not via constructor injection) so the
 * queued payload stays a plain event and never tries to serialize the transport.
 */
class ForwardAuditToTransport implements ShouldQueue
{
    public function handle(UserActionEvent $event): void
    {
        resolve(AuditTransport::class)->send($event->audit);
    }
}
