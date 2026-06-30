<?php

namespace App\Services;

use App\Models\AuditTrail;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Service class that centralizes AuditTrailService business logic.
 */
class AuditTrailService
{
    /**
     * Fields that should never be written to logs in plain text.
     *
     * @var array<int, string>
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        '_token',
        'evidence_image',
        'file_data',
    ];

    /**
     * Route names and paths that are considered sensitive even before authentication.
     *
     * @var array<int, string>
     */
    protected array $sensitiveRouteFragments = [
        'login',
        'logout',
        'register',
        'password.',
        'forgot-password',
        'reset-password',
    ];

    /**
     * Handle the logRequest workflow for this class.
     */
    public function logRequest(Request $request, Response $response): void
    {
        if ($this->shouldSkip($request)) {
            return;
        }

        $this->log(
            action: 'request.'.strtolower($request->method()),
            metadata: [
                'query' => $this->sanitizeInput($request->query()),
            ],
            newValues: $this->sanitizeInput($request->except($this->sensitiveFields)),
            description: 'Sensitive request recorded by middleware.',
            actor: $this->resolveActor($request),
            request: $request,
            statusCode: $response->getStatusCode(),
        );
    }

    /**
     * Handle the logAuthEvent workflow for this class.
     */
    public function logAuthEvent(
        string $action,
        Model|Authenticatable|null $subject = null,
        array $metadata = [],
        ?Request $request = null,
    ): void {
        $subjectModel = $subject instanceof Model ? $subject : null;
        $resolvedRequest = $request ?? $this->resolveRequest();

        $this->log(
            action: 'auth.'.$action,
            subject: $subjectModel,
            metadata: $metadata,
            description: 'Authentication event: '.$action,
            actor: $this->resolveActor($resolvedRequest, $subjectModel),
            request: $resolvedRequest,
        );
    }

    /**
     * Handle the logModelEvent workflow for this class.
     */
    public function logModelEvent(
        string $event,
        Model $model,
        ?array $oldValues = null,
        ?array $newValues = null,
        array $metadata = [],
    ): void {
        $request = $this->resolveRequest();

        $this->log(
            action: strtolower(class_basename($model)).'.'.$event,
            subject: $model,
            metadata: array_merge($metadata, [
                'model' => $model::class,
            ]),
            oldValues: $oldValues,
            newValues: $newValues,
            description: class_basename($model).' '.$event.'.',
            actor: $this->resolveActor($request),
            request: $request,
        );
    }

    /**
     * Handle the log workflow for this class.
     */
    public function log(
        string $action,
        ?Model $subject = null,
        array $metadata = [],
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        Model|Authenticatable|null $actor = null,
        ?Request $request = null,
        ?int $statusCode = null,
    ): void {
        if (! $this->auditTrailTableExists()) {
            return;
        }

        $resolvedActor = $actor instanceof Model ? $actor : null;
        $resolvedRequest = $request ?? $this->resolveRequest();

        try {
            AuditTrail::create([
                'actor_type' => $resolvedActor?->getMorphClass(),
                'actor_id' => $resolvedActor?->getKey(),
                'actor_name' => $this->resolveDisplayName($resolvedActor),
                'action' => $action,
                'description' => $description,
                'subject_type' => $subject?->getMorphClass(),
                'subject_id' => $subject?->getKey(),
                'subject_name' => $this->resolveDisplayName($subject),
                'route_name' => $resolvedRequest?->route()?->getName(),
                'method' => $resolvedRequest?->method(),
                'url' => $resolvedRequest?->fullUrl(),
                'ip_address' => $resolvedRequest?->ip(),
                'user_agent' => $resolvedRequest?->userAgent(),
                'status_code' => $statusCode,
                'old_values' => $oldValues === null ? null : $this->sanitizeInput($oldValues),
                'new_values' => $newValues === null ? null : $this->sanitizeInput($newValues),
                'metadata' => $this->sanitizeInput($metadata),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Failed to persist audit trail record.', [
                'action' => $action,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Handle the shouldSkip workflow for this class.
     */
    protected function shouldSkip(Request $request): bool
    {
        $routeName = (string) $request->route()?->getName();
        $path = $request->path();
        $isSensitivePath = collect($this->sensitiveRouteFragments)->contains(
            fn (string $fragment): bool => str_contains($routeName, $fragment) || str_contains($path, $fragment)
        );

        if (! $isSensitivePath && $request->isMethod('GET')) {
            return true;
        }

        if (! $isSensitivePath && ! $request->user()) {
            return true;
        }

        return $request->is('build/*')
            || $request->is('_debugbar/*')
            || $request->is('favicon.ico');
    }

    /**
     * Handle the auditTrailTableExists workflow for this class.
     */
    protected function auditTrailTableExists(): bool
    {
        static $exists;

        if ($exists !== null) {
            return $exists;
        }

        try {
            return $exists = Schema::hasTable('audit_trails');
        } catch (Throwable) {
            return $exists = false;
        }
    }

    /**
     * Handle the resolveRequest workflow for this class.
     */
    protected function resolveRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        return request();
    }

    /**
     * Handle the resolveActor workflow for this class.
     */
    protected function resolveActor(?Request $request = null, ?Model $fallback = null): ?Model
    {
        $requestUser = $request?->user();

        if ($requestUser instanceof Model) {
            return $requestUser;
        }

        $authUser = Auth::user();

        if ($authUser instanceof Model) {
            return $authUser;
        }

        return $fallback;
    }

    /**
     * Handle the resolveDisplayName workflow for this class.
     */
    protected function resolveDisplayName(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        foreach (['name', 'full_name', 'email', 'title'] as $attribute) {
            $value = $model->getAttribute($attribute);

            if (filled($value)) {
                return (string) $value;
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }

    /**
     * Trim very long payload values before writing them to logs.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function sanitizeInput(array $input): array
    {
        foreach ($input as $key => $value) {
            if (in_array((string) $key, $this->sensitiveFields, true)) {
                $input[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $input[$key] = $this->sanitizeInput($value);

                continue;
            }

            if (is_string($value) && mb_strlen($value) > 500) {
                $input[$key] = mb_substr($value, 0, 500).'...';

                continue;
            }
        }

        return $input;
    }
}
