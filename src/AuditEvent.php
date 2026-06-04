<?php

declare(strict_types=1);

namespace Aw3r1se\Audit;

/**
 * Immutable description of a single audited HTTP action.
 *
 * Carries everything a transport needs to persist or forward the action,
 * decoupled from the request lifecycle so it can travel through a queue.
 */
final readonly class AuditEvent
{
    /**
     * @param  array<string, mixed>  $routeParams
     * @param  array<string, mixed>  $body
     * @param  list<array<string, mixed>>  $changes
     */
    public function __construct(
        public string $route,
        public ?string $ip = null,
        public array $routeParams = [],
        public array $body = [],
        public int $statusCode = 200,
        public int|string|null $userId = null,
        public array $changes = [],
    ) {}

    /**
     * Wire representation, pruned of empty values.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'route' => $this->route,
            'ip' => $this->ip,
            'user_id' => $this->userId,
            'status_code' => $this->statusCode,
            'body' => $this->body,
            'path_params' => $this->routeParams,
            'context' => $this->changes,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }
}
