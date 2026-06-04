<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Feature;

use Aw3r1se\Audit\AuditContext;
use Aw3r1se\Audit\Tests\Fixtures\Author;
use Aw3r1se\Audit\Tests\Fixtures\Post;
use Aw3r1se\Audit\Tests\Fixtures\PostMetric;
use Aw3r1se\Audit\Tests\Fixtures\Tag;
use Aw3r1se\Audit\Tests\TestCase;

final class RecordsRelationsTest extends TestCase
{
    private function pull(): array
    {
        return resolve(AuditContext::class)->pull();
    }

    private function freshPost(): Post
    {
        $post = Post::create(['name' => 'Post']);
        resolve(AuditContext::class)->pull();

        return $post;
    }

    public function test_it_records_belongs_to_many_attach(): void
    {
        $post = $this->freshPost();
        $tag = Tag::create(['name' => 'news']);
        resolve(AuditContext::class)->pull();

        $post->tags()->attach($tag->getKey());

        $changes = $this->pull();
        $this->assertCount(1, $changes);
        $this->assertSame('relation_attached', $changes[0]['action']);
        $this->assertSame('tags', $changes[0]['state']['relation']);
        $this->assertSame([$tag->getKey()], $changes[0]['state']['attached']);
        $this->assertArrayNotHasKey('pivot', $changes[0]['state']);
    }

    public function test_it_records_pivot_payload_on_attach(): void
    {
        $post = $this->freshPost();
        $tag = Tag::create(['name' => 'news']);
        resolve(AuditContext::class)->pull();

        $post->tags()->attach($tag->getKey(), ['note' => 'pinned']);

        $state = $this->pull()[0]['state'];
        $this->assertSame(['note' => 'pinned'], $state['pivot']);
    }

    public function test_it_records_detach(): void
    {
        $post = $this->freshPost();
        $tag = Tag::create(['name' => 'news']);
        $post->tags()->attach($tag->getKey());
        resolve(AuditContext::class)->pull();

        $post->tags()->detach($tag->getKey());

        $changes = $this->pull();
        $this->assertSame('relation_detached', $changes[0]['action']);
        $this->assertSame([$tag->getKey()], $changes[0]['state']['detached']);
    }

    public function test_it_records_sync_as_single_entry(): void
    {
        $post = $this->freshPost();
        $a = Tag::create(['name' => 'a']);
        $b = Tag::create(['name' => 'b']);
        $post->tags()->attach($a->getKey());
        resolve(AuditContext::class)->pull();

        $post->tags()->sync([$b->getKey()]);

        $changes = $this->pull();
        $this->assertCount(1, $changes);
        $this->assertSame('relation_synced', $changes[0]['action']);
        $this->assertSame([$b->getKey()], array_values($changes[0]['state']['attached']));
        $this->assertSame([$a->getKey()], array_values($changes[0]['state']['detached']));
    }

    public function test_it_records_toggle(): void
    {
        $post = $this->freshPost();
        $tag = Tag::create(['name' => 'news']);
        resolve(AuditContext::class)->pull();

        $post->tags()->toggle([$tag->getKey()]);

        $this->assertSame('relation_toggled', $this->pull()[0]['action']);
    }

    public function test_it_records_pivot_update(): void
    {
        $post = $this->freshPost();
        $tag = Tag::create(['name' => 'news']);
        $post->tags()->attach($tag->getKey());
        resolve(AuditContext::class)->pull();

        $post->tags()->updateExistingPivot($tag->getKey(), ['note' => 'edited']);

        $changes = $this->pull();
        $this->assertSame('relation_pivot_updated', $changes[0]['action']);
        $this->assertSame(['note' => 'edited'], $changes[0]['state']['pivot']);
    }

    public function test_it_records_belongs_to_associate(): void
    {
        $post = $this->freshPost();
        $author = Author::create(['name' => 'Jane']);
        resolve(AuditContext::class)->pull();

        $post->author()->associate($author);

        $changes = $this->pull();
        $this->assertSame('relation_attached', $changes[0]['action']);
        $this->assertSame('author', $changes[0]['state']['relation']);
        $this->assertSame([$author->getKey()], $changes[0]['state']['attached']);
    }

    public function test_it_records_has_many_create(): void
    {
        $post = $this->freshPost();

        $comment = $post->comments()->create(['body' => 'Nice']);

        $changes = $this->pull();
        // The created Comment ('created') and the relation attach are both recorded.
        $this->assertContains('created', array_column($changes, 'action'));

        $attach = collect($changes)->firstWhere('action', 'relation_attached');
        $this->assertNotNull($attach);
        $this->assertSame('comments', $attach['state']['relation']);
        $this->assertSame([$comment->getKey()], $attach['state']['attached']);
    }

    public function test_it_snapshots_weak_entities_with_no_key_on_attach(): void
    {
        $post = $this->freshPost();

        $post->metrics()->createMany([
            ['label' => 'views', 'value' => 10],
            ['label' => 'clicks', 'value' => 3],
        ]);

        $attach = collect($this->pull())->firstWhere('action', 'relation_attached');

        $this->assertNotNull($attach);
        $this->assertSame('metrics', $attach['state']['relation']);

        // No null ids — each entry is a filtered attribute snapshot instead.
        $attached = $attach['state']['attached'];
        $this->assertCount(2, $attached);
        $this->assertIsArray($attached[0]);
        $this->assertSame('views', $attached[0]['label']);
        $this->assertSame($post->getKey(), $attached[0]['post_id']);
        $this->assertArrayNotHasKey('id', $attached[0]);
    }

    public function test_weak_entity_model_id_falls_back_to_snapshot(): void
    {
        $post = $this->freshPost();

        $post->metrics()->create(['label' => 'views', 'value' => 10]);

        $created = collect($this->pull())->firstWhere('action', 'created');

        $this->assertNotNull($created);
        $this->assertSame(PostMetric::class, $created['model_type']);
        $this->assertIsArray($created['model_id']);
        $this->assertSame('views', $created['model_id']['label']);
    }
}
