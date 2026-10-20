<?php
declare(strict_types=1);

namespace App\Core;

final class ValidationException extends HttpException
{
    public function __construct(public readonly array $errors, string $message = 'Certaines informations sont invalides.')
    {
        parent::__construct(422, $message, ['errors' => $errors]);
    }
}
