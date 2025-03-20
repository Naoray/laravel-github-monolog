<?php

namespace Naoray\LaravelGithubMonolog\Issues;

use Illuminate\Support\Collection;

class SectionMapping
{
    private const SECTION_MAPPINGS = [
        '{simplified_stack_trace}' => 'stacktrace',
        '{full_stack_trace}' => 'stacktrace',
        '{previous_exceptions}' => 'prev-exception',
        '{context}' => 'context',
        '{extra}' => 'extra',
        '{prev_exception_simplified_stack_trace}' => 'prev-exception-stacktrace',
        '{prev_exception_full_stack_trace}' => 'prev-exception-stacktrace',
    ];

    public static function getSectionsToRemove(array $replacements): array
    {
        return collect(self::SECTION_MAPPINGS)
            ->when(empty($replacements), fn (Collection $collection) => $collection->values()->unique())
            ->when(!empty($replacements), function (Collection $collection) use ($replacements) {
                return $collection
                    ->filter(fn (string $_, string $placeholder) => isset($replacements[$placeholder]) && empty($replacements[$placeholder]))
                    ->values()
                    ->unique();
            })
            ->values()
            ->toArray();
    }

    public static function getRemainingSections(array $sectionsToRemove): array
    {
        return collect(self::SECTION_MAPPINGS)
            ->values()
            ->unique()
            ->diff($sectionsToRemove)
            ->values()
            ->toArray();
    }

    public static function getSectionPattern(string $section, bool $removeContent = false): string
    {
        if ($removeContent) {
            return "/<!-- {$section}:start -->.*?<!-- {$section}:end -->\n?/s";
        }

        return "/<!-- {$section}:start -->\s*(.*?)\s*<!-- {$section}:end -->/s";
    }

    public static function getStandaloneFlagPattern(): string
    {
        return '/<!-- (stacktrace|prev-stacktrace|context|extra|prev-exception|prev-exception-stacktrace):(start|end) -->\n?/s';
    }
}
