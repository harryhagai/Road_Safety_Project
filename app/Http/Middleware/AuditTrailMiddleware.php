<?php

namespace App\Http\Middleware;

use App\Services\AuditTrailService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware that filters requests through the AuditTrailMiddleware rule.
 */
class AuditTrailMiddleware
{
    /**
     * Handle the __construct workflow for this class.
     */
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {
    }

    /**
     * Process the incoming request before it reaches the next layer.
     */

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $this->auditTrailService->logRequest($request, $response);

        return $response;
    }
}
