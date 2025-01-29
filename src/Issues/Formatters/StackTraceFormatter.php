<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StackTraceFormatter
{
    private const VENDOR_FRAME_PLACEHOLDER = '[Vendor frames]';

    public function format(string $stackTrace): string
    {
        return collect(explode("\n", $stackTrace))
            ->filter(fn ($line) => ! empty(trim($line)))
            ->map(function ($line) {
                if (trim($line) === '"}') {
                    return '';
                }

                if (str_contains($line, '{"exception":"[object] ')) {
                    return $this->formatInitialException($line);
                }

                if (! Str::isMatch('/#[0-9]+ /', $line)) {
                    return $line;
                }

                $line = str_replace(base_path(), '', $line);

                if (str_contains((string) $line, '/vendor/') && ! Str::isMatch("/BoundMethod\.php\([0-9]+\): App/", $line)) {
                    return self::VENDOR_FRAME_PLACEHOLDER;
                }

                return $line;
            })
            ->pipe($this->collapseVendorFrames(...))
            ->join("\n");
    }

    private function formatInitialException(string $line): array
    {
        [$message, $exception] = explode('{"exception":"[object] ', $line);

        return [
            $message,
            $exception,
        ];
    }

    private function collapseVendorFrames(Collection $lines): Collection
    {
        $hasVendorFrame = false;

        return $lines->filter(function ($line) use (&$hasVendorFrame) {
            $isVendorFrame = str_contains($line, '[Vendor frames]');

            if ($isVendorFrame) {
                if ($hasVendorFrame) {
                    return false;
                }
                $hasVendorFrame = true;
            } else {
                $hasVendorFrame = false;
            }

            return true;
        });
    }
}
