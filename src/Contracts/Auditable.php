<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Contracts;

interface Auditable
{
    /**
     * Human-readable label for the model in audit output.
     */
    public function auditLabel(): string;

    /**
     * Attributes excluded from recorded state.
     *
     * @return list<string>
     */
    public function getAuditIgnoredFields(): array;

    /**
     * Relations whose changes must not be recorded.
     *
     * @return list<string>
     */
    public function getAuditIgnoredRelations(): array;

    /**
     * Recording strategy override: a `config('audit.strategies')` key or an
     * {@see AuditStrategy} class-string. Null falls back to `config('audit.strategy')`.
     */
    public function getAuditStrategy(): ?string;

    /**
     * Relations to embed in a snapshot (dot-notation for nesting). Only
     * consulted by the snapshot strategy.
     *
     * @return list<string>
     */
    public function getAuditSnapshotRelations(): array;
}
