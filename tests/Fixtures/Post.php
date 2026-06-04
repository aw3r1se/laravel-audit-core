<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Fixtures;

use Aw3r1se\Audit\Concerns\InteractsWithAudit;
use Aw3r1se\Audit\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model implements Auditable
{
    use InteractsWithAudit;

    protected $guarded = [];

    /** @var list<string> */
    protected $hidden = ['secret'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withPivot('note');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(PostMetric::class);
    }
}
