<?php

declare(strict_types=1);

namespace Aw3r1se\Audit\Http\Middleware;

use Aw3r1se\Audit\AuditContext;
use Aw3r1se\Audit\AuditEvent;
use Aw3r1se\Audit\Events\UserActionEvent;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * Captures mutating requests and dispatches a {@see UserActionEvent} carrying
 * the request metadata plus whatever the model layer recorded into the
 * {@see AuditContext} during the request.
 */
class AuditActions
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$except): Response
    {
        $shouldAudit = in_array($request->method(), $this->auditableMethods(), true);
        $body = $shouldAudit ? $this->resolveBody($request, $except) : [];

        $response = $next($request);

        if ($shouldAudit) {
            $changes = resolve(AuditContext::class)->pull();

            if ($this->shouldDispatch($changes)) {
                UserActionEvent::dispatch(new AuditEvent(
                    route: (string) $request->route()?->getName(),
                    ip: $request->getClientIp(),
                    routeParams: $this->resolveRouteParams($request),
                    body: $body,
                    statusCode: $response->getStatusCode(),
                    userId: $this->resolveUserId($request),
                    changes: $changes,
                ));
            }
        }

        return $response;
    }

    /**
     * HTTP methods that trigger an audit.
     *
     * @return list<string>
     */
    protected function auditableMethods(): array
    {
        /** @var list<string> $methods */
        $methods = config('audit.auditable_methods', ['POST', 'PUT', 'PATCH', 'DELETE']);

        return $methods;
    }

    /**
     * Whether a recorded request should be dispatched. The base middleware logs
     * every mutating request (so actions without model changes, e.g. login, are
     * still audited); subclasses may narrow this.
     *
     * @param  list<array<string, mixed>>  $changes
     */
    protected function shouldDispatch(array $changes): bool
    {
        return true;
    }

    protected function resolveUserId(Request $request): int|string|null
    {
        $user = $request->user();

        if ($user !== null) {
            $id = $user->getAuthIdentifier();

            return is_int($id) || is_string($id) ? $id : null;
        }

        $attribute = (string) config('audit.user_id_attribute', 'audit_user_id');
        $fallback = $request->attributes->get($attribute);

        return is_int($fallback) || is_string($fallback) ? $fallback : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveRouteParams(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(fn (mixed $value) => $value instanceof Model ? $value->getKey() : $value)
            ->toArray();
    }

    /**
     * @param  list<string>  $except
     * @return array<string, mixed>
     */
    protected function resolveBody(Request $request, array $except = []): array
    {
        return collect($request->except($except))
            ->map(function (mixed $value): mixed {
                if ($value instanceof UploadedFile) {
                    $value = [
                        'name' => $value->getClientOriginalName(),
                        'mime' => $value->getClientMimeType(),
                        'size' => $value->getSize(),
                    ];
                }

                return $value;
            })
            ->toArray();
    }
}
