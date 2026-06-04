<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Feature;

use Aw3r1se\Audit\Events\UserActionEvent;
use Aw3r1se\Audit\Http\Middleware\AuditActions;
use Aw3r1se\Audit\Tests\Fixtures\Post;
use Aw3r1se\Audit\Tests\Fixtures\SpyTransport;
use Aw3r1se\Audit\Tests\TestCase;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;

final class MiddlewareDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SpyTransport::reset();
        config()->set('audit.transport', SpyTransport::class);
    }

    protected function defineRoutes($router): void
    {
        /** @var Router $router */
        $router->middleware(AuditActions::class)->group(function (Router $router): void {
            $router->post('/posts', function () {
                Post::create(['name' => 'Via HTTP']);

                return response()->json(['ok' => true], 201);
            })->name('posts.store');

            $router->get('/posts', fn () => response()->json([]))->name('posts.index');
        });
    }

    public function test_it_dispatches_an_event_for_mutating_requests(): void
    {
        Event::fake([UserActionEvent::class]);

        $this->postJson('/posts', ['name' => 'Via HTTP'])->assertStatus(201);

        Event::assertDispatched(UserActionEvent::class, function (UserActionEvent $event): bool {
            return $event->audit->route === 'posts.store'
                && $event->audit->statusCode === 201
                && collect($event->audit->changes)->contains(fn (array $c): bool => $c['action'] === 'created');
        });
    }

    public function test_it_does_not_audit_read_requests(): void
    {
        Event::fake([UserActionEvent::class]);

        $this->getJson('/posts')->assertOk();

        Event::assertNotDispatched(UserActionEvent::class);
    }

    public function test_it_forwards_the_event_to_the_configured_transport(): void
    {
        $this->postJson('/posts', ['name' => 'Via HTTP'])->assertStatus(201);

        $this->assertCount(1, SpyTransport::$sent);
        $this->assertSame('posts.store', SpyTransport::$sent[0]->route);
        $this->assertNotEmpty(SpyTransport::$sent[0]->changes);
    }

    public function test_request_body_is_captured_with_excepted_keys_removed(): void
    {
        $this->postJson('/posts', ['name' => 'Via HTTP', 'name_secret' => 'x']);

        $body = SpyTransport::$sent[0]->body;
        $this->assertSame('Via HTTP', $body['name']);
    }
}
