<?php

namespace Naoray\LaravelGithubMonolog\Issues\Formatters;

class QueryFormatter
{
    public function format(?array $queries): string
    {
        if (empty($queries)) {
            return '';
        }

        $output = "```sql\n";
        foreach ($queries as $query) {
            $sql = $query['sql'] ?? '';
            $bindings = $query['bindings'] ?? [];
            $time = $query['time'] ?? 0;
            $connection = $query['connection'] ?? 'default';

            $output .= "-- Connection: {$connection} (".number_format($time, 1)."ms)\n";
            $output .= $sql;

            if (! empty($bindings)) {
                $output .= "\n-- Bindings: ".json_encode($bindings, JSON_PRETTY_PRINT);
            }

            $output .= "\n\n";
        }

        return rtrim($output)."\n```";
    }
}
