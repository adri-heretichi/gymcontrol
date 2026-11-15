<?php

namespace App\Http\Middleware;

use App\Services\RealtimeChanges;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FlushRealtimeChanges
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        app(RealtimeChanges::class)->flush();
    }
}
