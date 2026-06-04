<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Fixtures;

use Aw3r1se\Audit\Concerns\InteractsWithAudit;
use Aw3r1se\Audit\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model implements Auditable
{
    use InteractsWithAudit;

    protected $guarded = [];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
