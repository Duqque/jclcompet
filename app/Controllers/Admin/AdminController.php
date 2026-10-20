<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\CompetitionService;

abstract class AdminController extends Controller
{
    protected function competition(Request $r): array
    {
        return CompetitionService::find((int) $r->param('id'));
    }

    protected function admin(string $template, array $data = []): Response
    {
        return $this->view('admin/' . $template, $data, 'admin');
    }
}
