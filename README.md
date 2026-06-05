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

#### Weak entities (no primary key)

Some child records have no key of their own (`$incrementing = false`, no
surrogate id). For those, `getKey()` is `null`, so a recorded id would be
meaningless. The engine detects this and falls back to a **filtered attribute
snapshot** of the row (honouring `getHidden()` and the ignored fields), both in
the relation payload (`attached`/`detached`) and in a model's own `model_id`.
You get the actual state of the change instead of `null`.

### Recording strategy: diff vs snapshot

By default each model event is recorded as a **diff** — new attributes on
create, changed attributes on update, the original row on delete. You can
instead record a **snapshot**: the model's full filtered attributes plus a
recursive snapshot of selected relations.

Pick the default for every model in config, and override it per model:

```php
// config/audit.php
'strategy' => 'snapshot', // 'changes' (default) | 'snapshot' | any AuditStrategy class
```

```php
class Order extends Model implements Auditable
{
    use InteractsWithAudit;

    // Override just for this model (a config key or an AuditStrategy class).
    protected string $auditStrategy = 'snapshot';

    // Relations embedded in the snapshot — dot-notation for nesting.
    protected array $auditSnapshotRelations = ['customer', 'lines.product'];
}
```

A snapshot entry's `state` looks like:

```json
{
  "attributes": { "status": "paid", "total": 4200 },
  "relations": {
    "customer": { "name": "ACME" },
    "lines": [ { "qty": 2, "relations": { "product": { "sku": "A1" } } } ]
  }
}
```

Relations are queried fresh at capture time (honouring `getHidden()` and the
ignored fields, same as attributes), so the snapshot reflects current persisted
state. Deletions are captured on `deleting`, while relations are still attached.

This only shapes a model's own create/update/delete entry; relation-mutation
recording (`attach`/`detach`/`sync`) is independent and runs under either
strategy. To add your own strategy, implement
`Aw3r1se\Audit\Contracts\AuditStrategy` and register it under
`config('audit.strategies')` (or reference it by class-string).

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
| `strategy`          | `'changes'`                         | default recording strategy (`changes` / `snapshot`)  |
| `strategies`        | `changes`/`snapshot` map            | key → `AuditStrategy` class, for per-model selection |
| `transport`         | `NullTransport::class`              | who receives each `AuditEvent`                       |
| `user_id_attribute` | `audit_user_id`                     | request attribute used to resolve a logged-out actor |
| `auditable_methods` | `['POST','PUT','PATCH','DELETE']`   | HTTP methods that trigger an audit                   |
| `ignored_fields`    | `['created_at','updated_at']`       | attributes stripped from every recorded state        |

## Testing

```bash
composer install
composer test
```
