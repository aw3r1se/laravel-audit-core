<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Fixtures;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Same table as {@see Post}, but ignores the `tags` relation and the `body`
 * attribute for auditing.
 */
class SilentPost extends Post
{
    protected $table = 'posts';

    /** @var list<string> */
    protected array $auditIgnoredFields = ['body'];

    /** @var list<string> */
    protected array $auditIgnoredRelations = ['tags'];

    public function tags(): BelongsToMany
    {
        // Pin the pivot table so it is not derived from this subclass name.
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id')->withPivot('note');
    }
}
