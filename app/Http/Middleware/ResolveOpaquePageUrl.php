<?php

namespace App\Http\Middleware;

use App\Services\OpaquePageUrls;
use Closure;
use Illuminate\Http\Request;

class ResolveOpaquePageUrl
{
    public function handle(Request $request, Closure $next)
    {
        if (str_starts_with($request->getPathInfo(), '/p/')) {
            abort_unless($request->isMethod('GET') || $request->isMethod('HEAD'), 405);
            $opaque = $request->getRequestUri();
            $path = app(OpaquePageUrls::class)->decode(substr($request->getPathInfo(), 3));
            $server = $request->server->all();
            $server['REQUEST_URI'] = $path.($request->getQueryString() ? '?'.$request->getQueryString() : '');
            // Resolve before Laravel matches routes: the original route, bindings,
            // authentication, authorization and controller all execute normally.
            $request->initialize($request->query->all(), $request->request->all(), $request->attributes->all(), $request->cookies->all(), $request->files->all(), $server, $request->getContent());
            $request->attributes->set('opaque_page_url', $opaque);
        }

        return $next($request);
    }
}
