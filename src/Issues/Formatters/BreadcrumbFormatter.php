<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

class BreadcrumbFormatter
{
    /**
     * @param  array<int, array{timestamp: string, category: string, message: string, metadata: array<string, mixed>}>|null  $breadcrumbs
     */
    public function format(?array $breadcrumbs): string
    {
        if (empty($breadcrumbs)) {
            return '';
        }

        $output = "| Time | Category | Message | Details |\n";
        $output .= "| --- | --- | --- | --- |\n";

        foreach ($breadcrumbs as $breadcrumb) {
            $timestamp = $breadcrumb['timestamp'];
            $category = $breadcrumb['category'];
            $message = $this->escapeTableCell($breadcrumb['message']);
            $metadata = $breadcrumb['metadata'];

            $details = ! empty($metadata) ? $this->escapeTableCell($this->formatMetadata($metadata)) : '';

            $output .= "| {$timestamp} | {$category} | {$message} | {$details} |\n";
        }

        return rtrim($output);
    }

    /**
     * Format metadata array as a compact string.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function formatMetadata(array $metadata): string
    {
        $parts = [];
        foreach ($metadata as $key => $value) {
            $parts[] = "{$key}: {$value}";
        }

        return implode(', ', $parts);
    }

    /**
     * Escape characters that would break markdown table cells.
     */
    private function escapeTableCell(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }
}
