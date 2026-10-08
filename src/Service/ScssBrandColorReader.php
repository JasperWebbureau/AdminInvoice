<?php

declare(strict_types=1);

namespace Flexgrid\Modules\AdminInvoice\Service;

final class ScssBrandColorReader
{
    private const DEFAULT_PRIMARY = '#1F2A44';
    private const DEFAULT_SECONDARY = '#4758BE';

    public function read(string $file, string $fallbackFile = ''): array
    {
        $variables = [];
        foreach (array_filter([$fallbackFile, $file]) as $source) {
            if (!is_file($source) || !is_readable($source)) {
                continue;
            }
            $content = file_get_contents($source);
            if (is_string($content)) {
                preg_match_all('/--([A-Za-z0-9_-]+)\s*:\s*([^;{}]+);/', $content, $matches, PREG_SET_ORDER);
                foreach ($matches as $match) {
                    $variables[strtolower($match[1])] = trim($match[2]);
                }
            }
        }

        return [
            'primary' => $this->firstColor($variables, ['primary', 'fg-primary'], self::DEFAULT_PRIMARY),
            'secondary' => $this->firstColor($variables, ['secondary', 'fg-secondary'], self::DEFAULT_SECONDARY),
        ];
    }

    private function firstColor(array $variables, array $names, string $fallback): string
    {
        foreach ($names as $name) {
            if (!isset($variables[$name])) {
                continue;
            }
            $resolved = $this->resolve($variables[$name], $variables, 0);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        return $fallback;
    }

    private function resolve(string $value, array $variables, int $depth): ?string
    {
        if ($depth > 5) {
            return null;
        }
        $value = trim($value);
        if (preg_match('/^#([A-Fa-f0-9]{6})$/D', $value, $match) === 1) {
            return '#' . strtoupper($match[1]);
        }
        if (preg_match('/^#([A-Fa-f0-9]{3})$/D', $value, $match) === 1) {
            $short = strtoupper($match[1]);
            return '#' . $short[0] . $short[0] . $short[1] . $short[1] . $short[2] . $short[2];
        }
        if (preg_match('/^var\(\s*--([A-Za-z0-9_-]+)\s*(?:,\s*([^\)]+))?\)$/D', $value, $match) !== 1) {
            return null;
        }

        $name = strtolower($match[1]);
        if (isset($variables[$name])) {
            return $this->resolve($variables[$name], $variables, $depth + 1);
        }

        return isset($match[2]) ? $this->resolve($match[2], $variables, $depth + 1) : null;
    }
}
