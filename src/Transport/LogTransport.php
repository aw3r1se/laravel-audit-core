<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Transport;

use Aw3r1se\Audit\AuditEvent;
use Aw3r1se\Audit\Contracts\AuditTransport;
use Psr\Log\LoggerInterface;

/**
 * Writes audited actions to a PSR-3 logger. Handy for local development and as
 * a reference implementation of the transport port.
 */
final class LogTransport implements AuditTransport
{
    public function __construct(private readonly LoggerInterface $logger) {}

    public function send(AuditEvent $event): void
    {
        $this->logger->info('audit.user_action', $event->toArray());
    }
}
