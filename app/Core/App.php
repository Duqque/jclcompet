<?php
declare(strict_types=1);

namespace App\Core;

final class App
{
    public function __construct(private readonly Router $router)
    {
    }

    public function handle(Request $request): Response
    {
        try {
            Session::start($request);
            $match = $this->router->match($request->method, $request->path);
            if ($match === null) {
                throw new HttpException(404);
            }
            $request->params = $match['params'];
            $this->runMiddleware($match['route']['middleware'], $request);
            $response = $this->invoke($match['route']['handler'], $request);
        } catch (ValidationException $e) {
            $response = $this->validationResponse($e, $request);
        } catch (HttpException $e) {
            $response = $this->errorResponse($e->status, $e->getMessage(), $request, $e->data);
        } catch (\Throwable $e) {
            Logger::error($e);
            $message = Config::get('app.debug') ? get_class($e) . ' : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')' : HttpException::defaultMessage(500);
            $response = $this->errorResponse(500, $message, $request);
        }
        return $this->secureHeaders($response);
    }

    private function runMiddleware(array $middleware, Request $request): void
    {
        $csrf = $request->method !== 'GET' && $request->method !== 'HEAD' && !in_array('nocsrf', $middleware, true);
        foreach ($middleware as $mw) {
            [$name, $arg] = array_pad(explode(':', $mw, 2), 2, null);
            switch ($name) {
                case 'auth':
                    if (!Auth::check()) {
                        throw new HttpException(401);
                    }
                    $this->enforcePasswordChange($request);
                    break;
                case 'guest':
                    if (Auth::check()) {
                        throw new RedirectSignal(Auth::homePath());
                    }
                    break;
                case 'role':
                    if (!Auth::check()) {
                        throw new HttpException(401);
                    }
                    if (!Auth::hasRole(...explode(',', (string) $arg))) {
                        throw new HttpException(403);
                    }
                    break;
                case 'can':
                    Auth::authorize((string) $arg);
                    break;
            }
        }
        if ($csrf) {
            Csrf::verify($request);
        }
    }

    private function enforcePasswordChange(Request $request): void
    {
        $user = Auth::user();
        if ($user && (int) $user['must_change_password'] === 1
            && !in_array($request->path, ['/compte/mot-de-passe', '/deconnexion'], true)) {
            if ($request->wantsJson()) {
                throw new HttpException(403, 'Vous devez changer votre mot de passe avant de continuer.');
            }
            throw new RedirectSignal('/compte/mot-de-passe');
        }
    }

    private function invoke(array|callable $handler, Request $request): Response
    {
        try {
            if (is_array($handler)) {
                [$class, $method] = $handler;
                $result = (new $class())->$method($request);
            } else {
                $result = $handler($request);
            }
        } catch (RedirectSignal $r) {
            return Response::redirect($r->to);
        }
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        return Response::html((string) $result);
    }

    private function validationResponse(ValidationException $e, Request $request): Response
    {
        if ($request->wantsJson()) {
            return Response::json(['ok' => false, 'error' => $e->getMessage(), 'errors' => $e->errors], 422);
        }
        Session::flashErrors($e->errors);
        Session::flashInput($request->post);
        Session::flash('error', $e->getMessage());
        return Response::redirect($this->back($request));
    }

    private function back(Request $request): string
    {
        $ref = $request->header('Referer');
        $base = (string) Config::get('app.url');
        if ($ref && $base !== '' && str_starts_with($ref, $base)) {
            return $ref;
        }
        return $request->path;
    }

    private function errorResponse(int $status, string $message, Request $request, array $data = []): Response
    {
        if ($status === 401 && !$request->wantsJson()) {
            Session::put('_intended', $request->method === 'GET' ? $request->path : null);
            return Response::redirect('/connexion');
        }
        if (in_array($status, [409, 422, 423], true) && !$request->wantsJson() && $request->method === 'POST') {
            Session::flashInput($request->post);
            Session::flash('error', $message);
            return Response::redirect($this->back($request));
        }
        if ($status === 419 && !$request->wantsJson()) {
            Session::flash('error', $message);
            return Response::redirect($this->back($request));
        }
        if ($request->wantsJson()) {
            return Response::json(array_merge(['ok' => false, 'error' => $message], $data), $status);
        }
        $html = View::render('errors/error', ['status' => $status, 'message' => $message, 'title' => 'Erreur ' . $status], 'error');
        return Response::html($html, $status);
    }

    private function secureHeaders(Response $response): Response
    {
        $response->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->header('Content-Security-Policy', "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'");
        if ((bool) Config::get('app.force_secure_cookies', false)) {
            $response->header('Strict-Transport-Security', 'max-age=31536000');
        }
        return $response;
    }
}
