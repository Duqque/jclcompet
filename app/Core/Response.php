<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    private array $headers = [];

    public function __construct(
        public string $body = '',
        public int $status = 200,
        array $headers = [],
        private ?string $filePath = null,
    ) {
        $this->headers = $headers;
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8', 'Cache-Control' => 'no-store']
        );
    }

    public static function redirect(string $to, int $status = 302): self
    {
        if (!preg_match('#^https?://#', $to)) {
            $to = url($to);
        }
        return new self('', $status, ['Location' => $to]);
    }

    public static function download(string $content, string $filename, string $mime): self
    {
        return new self($content, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . str_replace('"', '', $filename) . '"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public static function file(string $path, string $mime, ?string $downloadName = null, bool $inline = true): self
    {
        $headers = ['Content-Type' => $mime, 'Content-Length' => (string) filesize($path), 'X-Content-Type-Options' => 'nosniff'];
        if ($downloadName !== null) {
            $headers['Content-Disposition'] = ($inline ? 'inline' : 'attachment') . '; filename="' . str_replace('"', '', $downloadName) . '"';
        }
        return new self('', 200, $headers, $path);
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("$name: $value");
            }
        }
        if ($this->filePath !== null) {
            readfile($this->filePath);
            return;
        }
        echo $this->body;
    }
}
