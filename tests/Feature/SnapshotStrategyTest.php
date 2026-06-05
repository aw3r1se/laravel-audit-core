<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Feature;

use Aw3r1se\Audit\AuditContext;
use Aw3r1se\Audit\Tests\Fixtures\Author;
use Aw3r1se\Audit\Tests\Fixtures\Comment;
use Aw3r1se\Audit\Tests\Fixtures\Post;
use Aw3r1se\Audit\Tests\Fixtures\SnapshotPost;
use Aw3r1se\Audit\Tests\TestCase;

final class SnapshotStrategyTest extends TestCase
{
    /**
     * @return list<array<string, mixed>>
     */
    private function pull(): array
    {
        return resolve(AuditContext::class)->pull();
    }

    private function entry(string $model, string $action): ?array
    {
        return collect($this->pull())
            ->first(fn (array $c): bool => $c['model_type'] === $model && $c['action'] === $action);
    }

    public function test_snapshot_captures_filtered_attributes_and_relations(): void
    {
        $author = Author::create(['name' => 'Jane']);
        $post = SnapshotPost::create([
            'name' => 'Post',
            'author_id' => $author->getKey(),
            'secret' => 'hidden',
        ]);
        Comment::create(['post_id' => $post->getKey(), 'body' => 'hi']);
        $this->pull();

        $post->update(['name' => 'Renamed']);

        $entry = $this->entry(SnapshotPost::class, 'updated');
        $this->assertNotNull($entry);

        $state = $entry['state'];
        $this->assertArrayHasKey('attributes', $state);
        $this->assertArrayHasKey('relations', $state);

        // Full attributes, hidden field stripped.
        $this->assertSame('Renamed', $state['attributes']['name']);
        $this->assertArrayNotHasKey('secret', $state['attributes']);

        // belongsTo serialized as a single model, hasMany as a list.
        $this->assertSame('Jane', $state['relations']['author']['name']);
        $this->assertSame('hi', $state['relations']['comments'][0]['body']);
    }

    public function test_delete_snapshot_is_taken_while_relations_are_attached(): void
    {
        $author = Author::create(['name' => 'Jane']);
        $post = SnapshotPost::create(['name' => 'Post', 'author_id' => $author->getKey()]);
        Comment::create(['post_id' => $post->getKey(), 'body' => 'hi']);
        $this->pull();

        $post->delete();

        $deletes = collect($this->pull())
            ->filter(fn (array $c): bool => $c['model_type'] === SnapshotPost::class && $c['action'] === 'deleted')
            ->values();

        // 'deleting' captured exactly once — not duplicated by 'deleted'.
        $this->assertCount(1, $deletes);
        $this->assertSame('hi', $deletes[0]['state']['relations']['comments'][0]['body']);
    }

    public function test_default_changes_strategy_still_records_a_flat_diff(): void
    {
        $post = Post::create(['name' => 'Post']);
        $this->pull();

        $post->update(['name' => 'Renamed']);

        $entry = $this->entry(Post::class, 'updated');
        $this->assertNotNull($entry);
        // Plain diff, not the snapshot {attributes, relations} shape.
        $this->assertSame(['name' => 'Renamed'], $entry['state']);
    }
}
