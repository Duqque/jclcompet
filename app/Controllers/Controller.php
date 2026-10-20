<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\ContentService;

abstract class Controller
{
    protected function view(string $template, array $data = [], string $layout = 'site'): Response
    {
        $GLOBALS['__old'] = Session::pullOld();
        $GLOBALS['__errors'] = Session::pullErrors();
        $data += [
            'flash' => Session::pullFlash(),
            'user' => Auth::user(),
            'appName' => Config::get('app.name'),
            'clubName' => ContentService::site('club_name', (string) Config::get('app.organizer.name')),
            'isDemoEnv' => in_array(Config::get('app.env'), ['local', 'demo'], true),
        ];
        return Response::html(View::render($template, $data, $layout));
    }

    protected function redirect(string $to, ?string $message = null, string $type = 'success'): Response
    {
        if ($message !== null) {
            Session::flash($type, $message);
        }
        return Response::redirect($to);
    }

    protected function back(Request $request, ?string $message = null, string $type = 'success', string $fallback = '/'): Response
    {
        if ($message !== null) {
            Session::flash($type, $message);
        }
        $ref = $request->header('Referer');
        $base = (string) Config::get('app.url');
        return Response::redirect($ref && str_starts_with($ref, $base) ? $ref : $fallback);
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function page(Request $request, int $perPage = 50): array
    {
        $page = max(1, (int) ($request->query['page'] ?? 1));
        return [$page, $perPage, ($page - 1) * $perPage];
    }
}
