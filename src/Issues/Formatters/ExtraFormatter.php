<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

class ExtraFormatter
{
    public function format(array $extra): string
    {
        if (empty($extra)) {
            return '';
        }

        return json_encode($extra, JSON_PRETTY_PRINT);
    }
}
