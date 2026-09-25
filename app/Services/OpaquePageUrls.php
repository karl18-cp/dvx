<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class OpaquePageUrls
{
    private array $tokens = [];

    public function eligible(string $path): bool
    {
        if (! config('navigation.opaque_urls') || ! str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/p/') || preg_match('/[?#\\\\\x00-\x20]/', $path)) {
            return false;
        }
        try {
            $route = Route::getRoutes()->match(Request::create($path, 'GET'));
            $middleware = $route->gatherMiddleware();

            return collect($middleware)->contains(fn ($name) => $name === 'auth' || str_starts_with($name, 'auth:'))
                && ! collect($middleware)->contains(fn ($name) => str_contains($name, 'signed'));
        } catch (HttpExceptionInterface) {
            return false;
        }
    }

    public function encode(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || isset($parts['host']) && $parts['host'] !== request()->getHost()) {
            return $url;
        }
        $path = $parts['path'] ?? '/';
        if (! $this->eligible($path)) {
            return $url;
        }
        $token = $this->tokens[$path] ??= rtrim(strtr(Crypt::encryptString($path), '+/', '-_'), '=');

        return '/p/'.$token.(isset($parts['query']) ? '?'.$parts['query'] : '').(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    public function decode(string $token): string
    {
        abort_unless(strlen($token) <= 4096 && preg_match('/^[A-Za-z0-9_-]+$/D', $token), 404);
        try {
            $path = Crypt::decryptString(strtr($token, '-_', '+/'));
        } catch (DecryptException) {
            abort(404);
        }
        abort_unless($this->eligible($path), 404);
        $this->tokens[$path] = $token;

        return $path;
    }

    public function links(): array
    {
        $links = [];
        foreach (Route::getRoutes() as $route) {
            $path = '/'.ltrim($route->uri(), '/');
            if (in_array('GET', $route->methods(), true) && ! str_contains($path, '{') && $this->eligible($path)) {
                $links[$path] = $this->encode($path);
            }
        }

        return $links;
    }
}
