<?php
declare(strict_types=1);

namespace App\Core;

class HttpException extends \RuntimeException
{
    public function __construct(public readonly int $status, string $message = '', public readonly array $data = [])
    {
        parent::__construct($message !== '' ? $message : self::defaultMessage($status), $status);
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Requête invalide.',
            401 => 'Authentification requise.',
            403 => 'Accès refusé.',
            404 => 'Page introuvable.',
            405 => 'Méthode non autorisée.',
            409 => 'Conflit : les données ont été modifiées entre-temps.',
            419 => 'Session expirée, veuillez recharger la page.',
            422 => 'Données invalides.',
            423 => 'Compétition verrouillée (archivée) : lecture seule.',
            429 => 'Trop de tentatives, réessayez plus tard.',
            default => 'Une erreur est survenue.',
        };
    }
}
