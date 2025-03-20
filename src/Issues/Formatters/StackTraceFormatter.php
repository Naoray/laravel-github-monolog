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

                // Stack trace lines start with #\d. Here we pad the numbers 0-9
                // with a preceding zero to keep everything in line visually.
                $line = preg_replace('/^(\e\[0m)#(\d)(?!\d)/', '$1#0$2', $line);

                if ($this->isVendorFrame($line)) {
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

    private function isVendorFrame($line): bool
    {
        return str_contains((string) $line, self::VENDOR_FRAME_PLACEHOLDER)
            || str_contains((string) $line, '/vendor/') && ! Str::isMatch("/BoundMethod\.php\([0-9]+\): App/", $line)
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
