<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Tests\Fixtures;

use Aw3r1se\Audit\Concerns\InteractsWithAudit;
use Aw3r1se\Audit\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * A weak entity: a child of {@see Post} with no primary key of its own, so
 * getKey() is always null. Used to verify the attribute-snapshot fallback.
 */
class PostMetric extends Model implements Auditable
{
    use InteractsWithAudit;

    public $incrementing = false;

    public $timestamps = false;

    protected $table = 'post_metrics';

    protected $guarded = [];
}
