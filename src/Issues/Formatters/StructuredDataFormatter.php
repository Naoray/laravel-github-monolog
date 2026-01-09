<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

class StructuredDataFormatter
{
    public function format(?array $data): string
    {
        if ($data === null || $data === []) {
            return '';
        }

        return "```json\n".json_encode($data, JSON_PRETTY_PRINT)."\n```";
    }
}
