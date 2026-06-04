<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Transport;

use Aw3r1se\Audit\AuditEvent;
use Aw3r1se\Audit\Contracts\AuditTransport;

/**
 * Default transport: discards every event. Swap it out via config('audit.transport').
 */
final class NullTransport implements AuditTransport
{
    public function send(AuditEvent $event): void
    {
        // Intentionally a no-op.
    }
}
