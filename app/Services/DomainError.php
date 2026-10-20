<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;

/** Erreur métier présentée telle quelle à l'utilisateur (règle non respectée). */
final class DomainError extends HttpException
{
    public function __construct(string $message, int $status = 422, array $data = [])
    {
        parent::__construct($status, $message, $data);
    }
}
