<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Feature;

use Aw3r1se\Audit\AuditContext;
use Aw3r1se\Audit\Tests\Fixtures\Post;
use Aw3r1se\Audit\Tests\TestCase;

/**
 * Regression: the context is request-scoped, so changes from one Octane request
 * must not leak into the next. Resolving it twice within a scope returns the
 * same instance; flushing scoped instances (a new request) yields a clean one.
 */
final class AuditContextScopeTest extends TestCase
{
    public function test_it_is_a_scoped_singleton_within_a_request(): void
    {
        $this->assertSame(
            resolve(AuditContext::class),
            resolve(AuditContext::class),
        );
    }

    public function test_changes_do_not_leak_across_scopes(): void
    {
        Post::create(['name' => 'Request one']);
        $this->assertCount(1, resolve(AuditContext::class)->pull());

        Post::create(['name' => 'Still request one']);

        // Simulate the next Octane request.
        $this->app->forgetScopedInstances();

        $this->assertCount(0, resolve(AuditContext::class)->pull());
    }
}
