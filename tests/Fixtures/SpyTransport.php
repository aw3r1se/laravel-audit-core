<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Fixtures;

use Aw3r1se\Audit\AuditEvent;
use Aw3r1se\Audit\Contracts\AuditTransport;

/**
 * Test double that captures every event handed to it.
 */
final class SpyTransport implements AuditTransport
{
    /** @var list<AuditEvent> */
    public static array $sent = [];

    public static function reset(): void
    {
        self::$sent = [];
    }

    public function send(AuditEvent $event): void
    {
        self::$sent[] = $event;
    }
}
