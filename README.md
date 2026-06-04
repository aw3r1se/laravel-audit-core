# laravel-audit-core

Eloquent model **and relation** change auditing for Laravel, with a pluggable
delivery transport. Records create/update/delete state plus relation mutations
(`attach`/`detach`/`sync`/`toggle`/`associate`/`pivot` updates) into a
request-scoped buffer, then dispatches a single event per request.

It is **Octane/RoadRunner safe**: the buffer is a scoped singleton and is always
resolved inside the recording closures, never captured at model boot.

## What this package does *not* do

It does not talk to any storage or message broker, and it has no read API. The
buffer is handed to a `transport` you provide. The read side (querying past
audit logs, presentation, localization) stays in your application — this package
is purely the recording engine.

## Install

```bash
composer require aw3r1se/laravel-audit-core
php artisan vendor:publish --tag=audit-config
```

The service provider is auto-discovered.

## Usage

### 1. Make a model auditable

```php
use Aw3r1se\Audit\Concerns\InteractsWithAudit;
use Aw3r1se\Audit\Contracts\Auditable;

class Post extends Model implements Auditable
{
    use InteractsWithAudit;

    // Optional per-model tweaks — just declare the properties:
    /** Added on top of the global config('audit.ignored_fields'). */
    protected array $auditIgnoredFields = ['remember_token'];

    /** Relation mutations on these are not recorded. */
    protected array $auditIgnoredRelations = ['lastUpdateUser'];
}
```

`$auditIgnoredFields` is merged with the global `config('audit.ignored_fields')`,
so you only list the extras. For computed values, override
`getAuditIgnoredFields()` / `getAuditIgnoredRelations()` instead.

The trait overrides the Eloquent relation factory methods, so relation changes
on `belongsTo`, `hasOne/Many`, `morphOne/Many`, `belongsToMany` and
`morphToMany` are audited automatically. No changes to your relation definitions
are needed.

### 2. Attach the middleware

Add `Aw3r1se\Audit\Http\Middleware\AuditActions` to the route group you want
audited (typically your API). It records every `POST/PUT/PATCH/DELETE` and
dispatches `Aw3r1se\Audit\Events\UserActionEvent` carrying an immutable
`AuditEvent`. Pass route arguments to exclude request keys from the captured
body, e.g. `->middleware(AuditActions::class.':password')`.

### 3. Provide a transport

Implement `Aw3r1se\Audit\Contracts\AuditTransport` and point config at it:

```php
// config/audit.php
'transport' => App\Audit\RabbitMqTransport::class,
```

```php
final class RabbitMqTransport implements AuditTransport
{
    public function send(AuditEvent $event): void
    {
        // publish $event->toArray() to your broker / service
    }
}
```

The package ships `NullTransport` (default, no-op) and `LogTransport` (PSR-3).
Delivery runs through the queued `ForwardAuditToTransport` listener, so it never
blocks the request — configure your queue accordingly.

## Configuration

| key                 | default                       | purpose                                            |
|---------------------|-------------------------------|----------------------------------------------------|
| `transport`         | `NullTransport::class`              | who receives each `AuditEvent`                       |
| `user_id_attribute` | `audit_user_id`                     | request attribute used to resolve a logged-out actor |
| `auditable_methods` | `['POST','PUT','PATCH','DELETE']`   | HTTP methods that trigger an audit                   |
| `ignored_fields`    | `['created_at','updated_at']`       | attributes stripped from every recorded state        |

## Testing

```bash
composer install
composer test
```
