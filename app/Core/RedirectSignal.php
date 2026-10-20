<?php
declare(strict_types=1);

namespace App\Core;

/** Interrompt le traitement pour rediriger (utilisé par les middlewares et les contrôleurs). */
final class RedirectSignal extends \RuntimeException
{
    public function __construct(public readonly string $to)
    {
        parent::__construct('redirect');
    }
}
