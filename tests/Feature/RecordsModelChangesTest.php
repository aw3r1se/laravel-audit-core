<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Feature;

use Aw3r1se\Audit\AuditContext;
use Aw3r1se\Audit\Tests\Fixtures\Post;
use Aw3r1se\Audit\Tests\TestCase;

final class RecordsModelChangesTest extends TestCase
{
    private function context(): AuditContext
    {
        return resolve(AuditContext::class);
    }

    public function test_it_records_creation_with_attributes(): void
    {
        $post = Post::create(['name' => 'Hello', 'body' => 'World']);

        $changes = $this->context()->pull();

        $this->assertCount(1, $changes);
        $this->assertSame('created', $changes[0]['action']);
        $this->assertSame(Post::class, $changes[0]['model_type']);
        $this->assertSame($post->getKey(), $changes[0]['model_id']);
        $this->assertSame('Hello', $changes[0]['state']['name']);
        $this->assertSame('World', $changes[0]['state']['body']);
    }

    public function test_it_records_only_changed_attributes_on_update(): void
    {
        $post = Post::create(['name' => 'Hello', 'body' => 'World']);
        $this->context()->pull();

        $post->update(['name' => 'Changed']);

        $changes = $this->context()->pull();

        $this->assertCount(1, $changes);
        $this->assertSame('updated', $changes[0]['action']);
        $this->assertSame(['name' => 'Changed'], $changes[0]['state']);
    }

    public function test_it_records_deletion(): void
    {
        $post = Post::create(['name' => 'Hello']);
        $this->context()->pull();

        $post->delete();

        $changes = $this->context()->pull();

        $this->assertCount(1, $changes);
        $this->assertSame('deleted', $changes[0]['action']);
    }

    public function test_it_strips_timestamps_and_hidden_attributes(): void
    {
        Post::create(['name' => 'Hello', 'secret' => 'shhh']);

        $state = $this->context()->pull()[0]['state'];

        $this->assertArrayNotHasKey('created_at', $state);
        $this->assertArrayNotHasKey('updated_at', $state);
        $this->assertArrayNotHasKey('secret', $state);
    }

    public function test_pull_drains_the_buffer(): void
    {
        Post::create(['name' => 'Hello']);

        $this->assertCount(1, $this->context()->pull());
        $this->assertCount(0, $this->context()->pull());
    }
}
