<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Events;

use Aw3r1se\Audit\AuditEvent;
use Illuminate\Foundation\Events\Dispatchable;

class UserActionEvent
{
    use Dispatchable;

    public function __construct(public readonly AuditEvent $audit) {}
}
