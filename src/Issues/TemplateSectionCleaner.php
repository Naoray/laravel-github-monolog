<?php

namespace Naoray\LaravelGithubMonolog\Issues;

use Illuminate\Support\Str;

class TemplateSectionCleaner
{
    public function clean(string $template, array $replacements): string
    {
        $content = Str::of($template)
            ->replace(array_keys($replacements), array_values($replacements))
            ->toString();

        // Remove empty sections
        $sectionsToRemove = SectionMapping::getSectionsToRemove($replacements);
        foreach ($sectionsToRemove as $section) {
            $pattern = SectionMapping::getSectionPattern($section, true);
            $content = (string) preg_replace($pattern, '', $content);
        }

        // Remove flags from non-empty sections
        $remainingSections = SectionMapping::getRemainingSections($sectionsToRemove);
        foreach ($remainingSections as $section) {
            $pattern = SectionMapping::getSectionPattern($section);
            $content = (string) preg_replace($pattern, '$1', $content);
        }

        // Remove any remaining standalone flags
        $content = (string) preg_replace(SectionMapping::getStandaloneFlagPattern(), '', $content);

        // Normalize multiple newlines between content and signature
        $content = (string) preg_replace('/\n{2,}<!-- Signature:/', "\n\n<!-- Signature:", $content);

        return $content;
    }
}
