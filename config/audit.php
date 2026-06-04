<?php

declare(strict_types=1);

use Aw3r1se\Audit\Transport\NullTransport;

return [
    /*
    |--------------------------------------------------------------------------
    | Audit transport
    |--------------------------------------------------------------------------
    |
    | The class responsible for delivering an AuditEvent to its destination
    | (a message broker, an external service, a database table, ...). It must
    | implement Aw3r1se\Audit\Contracts\AuditTransport. Bind your own here; the
    | package ships NullTransport (no-op) and LogTransport (PSR-3 logger).
    |
    */
    'transport' => env('AUDIT_TRANSPORT', NullTransport::class),

    /*
    |--------------------------------------------------------------------------
    | Logged-out user fallback
    |--------------------------------------------------------------------------
    |
    | When the request has no authenticated user, the middleware falls back to
    | this request attribute to resolve the actor id (e.g. set during logout
    | before the token is invalidated).
    |
    */
    'user_id_attribute' => env('AUDIT_USER_ID_ATTRIBUTE', 'audit_user_id'),

    /*
    |--------------------------------------------------------------------------
    | Auditable HTTP methods
    |--------------------------------------------------------------------------
    |
    | Request methods the middleware treats as mutating and therefore audits.
    |
    */
    'auditable_methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],

    /*
    |--------------------------------------------------------------------------
    | Globally ignored attributes
    |--------------------------------------------------------------------------
    |
    | Default attributes stripped from every recorded state. Per-model
    | overrides live in Auditable::getAuditIgnoredFields().
    |
    */
    'ignored_fields' => ['created_at', 'updated_at'],
];
