<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Contracts;

use Aw3r1se\Audit\AuditEvent;

interface AuditTransport
{
    /**
     * Deliver a recorded user action to its destination.
     */
    public function send(AuditEvent $event): void;
}
