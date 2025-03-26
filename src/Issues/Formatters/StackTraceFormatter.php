<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StackTraceFormatter
{
    private const VENDOR_FRAME_PLACEHOLDER = '[Vendor frames]';

    public function format(string $stackTrace, bool $collapseVendorFrames = true): string
    {
        return collect(explode("\n", $stackTrace))
            ->filter(fn ($line) => ! empty(trim($line)))
            ->map(function ($line) use ($collapseVendorFrames) {
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

                $line = $this->padStackTraceLine($line);

                if ($collapseVendorFrames && $this->isVendorFrame($line)) {
                    return self::VENDOR_FRAME_PLACEHOLDER;
                }

                return $line;
            })
            ->pipe(fn ($lines) => $collapseVendorFrames ? $this->collapseVendorFrames($lines) : $lines)
            ->join("\n");
    }

    /**
     * Stack trace lines start with #\d. Here we pad the numbers 0-9
     * with a preceding zero to keep everything in line visually.
     */
    public function padStackTraceLine(string $line): string
    {
        return (string) preg_replace('/^#(\d)(?!\d)/', '#0$1', $line);
    }

    private function formatInitialException(string $line): array
    {
        [$message, $exception] = explode('{"exception":"[object] ', $line);

        return [
            $message,
            $exception,
        ];
    }

    private function isVendorFrame($line): bool
    {
        return str_contains((string) $line, self::VENDOR_FRAME_PLACEHOLDER)
            || str_contains((string) $line, '/vendor/') && ! Str::isMatch("/BoundMethod\.php\([0-9]+\): App/", $line)
            || str_contains((string) $line, '/artisan')
            || str_ends_with($line, '{main}');
    }

    private function collapseVendorFrames(Collection $lines): Collection
    {
        $hasVendorFrame = false;

        return $lines->filter(function ($line) use (&$hasVendorFrame) {
            $isVendorFrame = $this->isVendorFrame($line);

            if ($isVendorFrame) {
                // Skip the line if a vendor frame has already been added.
                if ($hasVendorFrame) {
                    return false;
                }

                // Otherwise, mark that a vendor frame has been added.
                $hasVendorFrame = true;
            } else {
                // Reset the flag if the current line is not a vendor frame.
                $hasVendorFrame = false;
            }

            return true;
        });
    }
}
