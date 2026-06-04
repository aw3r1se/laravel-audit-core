<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Feature;

use Aw3r1se\Audit\AuditContext;
use Aw3r1se\Audit\Tests\Fixtures\SilentPost;
use Aw3r1se\Audit\Tests\Fixtures\Tag;
use Aw3r1se\Audit\Tests\TestCase;

final class IgnoredAuditTest extends TestCase
{
    private function pull(): array
    {
        return resolve(AuditContext::class)->pull();
    }

    public function test_it_honours_per_model_ignored_fields(): void
    {
        SilentPost::create(['name' => 'Hello', 'body' => 'World']);

        $state = $this->pull()[0]['state'];

        $this->assertArrayHasKey('name', $state);
        $this->assertArrayNotHasKey('body', $state);
    }

    public function test_it_skips_ignored_relations(): void
    {
        $post = SilentPost::create(['name' => 'Hello']);
        $tag = Tag::create(['name' => 'news']);
        $this->pull();

        $post->tags()->attach($tag->getKey());

        $this->assertCount(0, $this->pull());
    }

    public function test_ignored_fields_are_configurable(): void
    {
        config()->set('audit.ignored_fields', ['created_at', 'updated_at', 'name']);

        \Aw3r1se\Audit\Tests\Fixtures\Post::create(['name' => 'Hidden', 'body' => 'Visible']);

        $state = $this->pull()[0]['state'];

        $this->assertArrayNotHasKey('name', $state);
        $this->assertArrayHasKey('body', $state);
    }
}
