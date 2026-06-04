<?php

declare(strict_types=1);

namespace Aw3r1se\Audit;

use Aw3r1se\Audit\Contracts\AuditTransport;
use Aw3r1se\Audit\Events\UserActionEvent;
use Aw3r1se\Audit\Listeners\ForwardAuditToTransport;
use Aw3r1se\Audit\Transport\NullTransport;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AuditServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/audit.php', 'audit');

        // Scoped, so it is flushed per request under Octane/RoadRunner.
        $this->app->scoped(AuditContext::class);

        $this->app->bind(AuditTransport::class, function (Application $app): AuditTransport {
            /** @var class-string<AuditTransport> $transport */
            $transport = $app['config']->get('audit.transport', NullTransport::class);

            return $app->make($transport);
        });
    }

    public function boot(): void
    {
        Event::listen(UserActionEvent::class, ForwardAuditToTransport::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/audit.php' => $this->app->configPath('audit.php'),
            ], 'audit-config');
        }
    }
}
