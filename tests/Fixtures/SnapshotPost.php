<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Fixtures;

use Aw3r1se\Audit\Concerns\InteractsWithAudit;
use Aw3r1se\Audit\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A {@see Post} backed by the same table but pinned to the snapshot strategy,
 * to verify the per-model override and relation serialization.
 */
class SnapshotPost extends Model implements Auditable
{
    use InteractsWithAudit;

    protected $table = 'posts';

    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['secret'];

    protected string $auditStrategy = 'snapshot';

    /** @var list<string> */
    protected array $auditSnapshotRelations = ['author', 'comments'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }
}
