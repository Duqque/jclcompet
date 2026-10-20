<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private ?array $json = null;
    public array $params = [];

    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query,
        public readonly array $post,
        public readonly array $files,
        public readonly array $server,
        private readonly string $rawBody = '',
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim((string) Config::get('app.base_path', ''), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        $uri = '/' . trim(rawurldecode($uri), '/');
        return new self($method, $uri, $_GET, $_POST, $_FILES, $_SERVER, (string) file_get_contents('php://input'));
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $json = $this->json();
        return $this->post[$key] ?? $json[$key] ?? $this->query[$key] ?? $default;
    }

    public function str(string $key, string $default = ''): string
    {
        $v = $this->input($key, $default);
        return is_scalar($v) ? trim((string) $v) : $default;
    }

    public function int(string $key, ?int $default = null): ?int
    {
        $v = $this->input($key);
        if ($v === null || $v === '' || !is_numeric($v)) {
            return $default;
        }
        return (int) $v;
    }

    public function bool(string $key): bool
    {
        $v = $this->input($key);
        return in_array($v, [true, 1, '1', 'on', 'true', 'yes'], true);
    }

    public function all(): array
    {
        return array_merge($this->query, $this->json(), $this->post);
    }

    public function json(): array
    {
        if ($this->json === null) {
            $this->json = [];
            if (str_contains($this->server['CONTENT_TYPE'] ?? '', 'application/json') && $this->rawBody !== '') {
                $decoded = json_decode($this->rawBody, true);
                $this->json = is_array($decoded) ? $decoded : [];
            }
        }
        return $this->json;
    }

    public function param(string $key): ?string
    {
        return $this->params[$key] ?? null;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $this->server[$key] ?? null;
    }

    public function wantsJson(): bool
    {
        return str_starts_with($this->path, '/api/')
            || str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json')
            || ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isSecure(): bool
    {
        return (!empty($this->server['HTTPS']) && $this->server['HTTPS'] !== 'off')
            || ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
            || (int) ($this->server['SERVER_PORT'] ?? 80) === 443;
    }

    public function file(string $key): ?array
    {
        $f = $this->files[$key] ?? null;
        return is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE ? $f : null;
    }
}
