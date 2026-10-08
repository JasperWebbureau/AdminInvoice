<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

final class ProjectInvoiceBranding
{
    /** @var string */ private $projectRoot;
    /** @var ScssBrandColorReader */ private $colors;

    public function __construct(string $projectRoot, ScssBrandColorReader $colors)
    {
        $resolved = realpath($projectRoot);
        if ($resolved === false || !is_dir($resolved)) {
            throw new \InvalidArgumentException('Projectroot voor factuurbranding bestaat niet.');
        }
        $this->projectRoot = rtrim($resolved, '/\\');
        $this->colors = $colors;
    }

    public function create(array $company, string $logoPath = ''): array
    {
        $safeCompany = [];
        foreach ([
            'name', 'street', 'postal', 'city', 'country', 'vat_nr', 'kvk',
            'iban', 'iban_holder', 'email', 'tel', 'domain',
        ] as $key) {
            $value = $company[$key] ?? '';
            $safeCompany[$key] = is_string($value) || is_int($value) ? trim((string)$value) : '';
        }
        $safeCompany['logo_data_uri'] = $this->logoDataUri($logoPath);

        return [
            'company' => $safeCompany,
            'brand' => $this->colors->read(
                $this->projectRoot . '/Files/Assets/Css/Variables.scss',
                $this->projectRoot . '/flexgrid/flexgrid/src/Assets/Css/Variables.scss'
            ),
        ];
    }

    private function logoDataUri(string $logoPath): string
    {
        $logoPath = trim($logoPath);
        if ($logoPath === '') {
            return '';
        }
        $resolved = realpath($logoPath);
        $filesRoot = realpath($this->projectRoot . '/Files');
        if ($resolved === false || $filesRoot === false || !is_file($resolved) || !$this->isWithin($resolved, $filesRoot)) {
            return '';
        }
        $size = filesize($resolved);
        if (!is_int($size) || $size < 1 || $size > 5 * 1024 * 1024) {
            return '';
        }

        $extension = strtolower((string)pathinfo($resolved, PATHINFO_EXTENSION));
        $mimes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];
        if (!isset($mimes[$extension])) {
            return '';
        }
        $content = file_get_contents($resolved);

        return is_string($content) && $content !== ''
            ? 'data:' . $mimes[$extension] . ';base64,' . base64_encode($content)
            : '';
    }

    private function isWithin(string $path, string $root): bool
    {
        $path = str_replace('\\', '/', $path);
        $root = rtrim(str_replace('\\', '/', $root), '/');
        if (DIRECTORY_SEPARATOR === '\\') {
            $path = strtolower($path);
            $root = strtolower($root);
        }

        return strpos($path, $root . '/') === 0;
    }
}
