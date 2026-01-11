<?php

use Naoray\LaravelGithubMonolog\Issues\TemplateSectionCleaner;

beforeEach(function () {
    $this->cleaner = new TemplateSectionCleaner;
});

test('it replaces template variables', function () {
    $template = 'Hello {name}!';
    $replacements = ['{name}' => 'World'];

    $result = $this->cleaner->clean($template, $replacements);

    expect($result)->toBe('Hello World!');
});

test('it removes empty stacktrace section', function () {
    $template = <<<'EOT'
Some content
<!-- stacktrace:start -->
<!-- stacktrace:end -->
More content
EOT;

    $result = $this->cleaner->clean($template, []);

    expect($result)->toBe(<<<'EOT'
Some content
More content
EOT);
});

test('it removes empty previous stacktrace section', function () {
    $template = <<<'EOT'
Some content
<!-- prev-stacktrace:start -->
<!-- prev-stacktrace:end -->
More content
EOT;

    $result = $this->cleaner->clean($template, []);

    expect($result)->toBe(<<<'EOT'
Some content
More content
EOT);
});

test('it removes empty context section', function () {
    $template = <<<'EOT'
Some content
<!-- context:start -->
<!-- context:end -->
More content
EOT;

    $result = $this->cleaner->clean($template, []);

    expect($result)->toBe(<<<'EOT'
Some content
More content
EOT);
});

test('it removes empty extra section', function () {
    $template = <<<'EOT'
Some content
<!-- extra:start -->
<!-- extra:end -->
More content
EOT;

    $result = $this->cleaner->clean($template, []);

    expect($result)->toBe(<<<'EOT'
Some content
More content
EOT);
});

test('it removes empty previous exception section', function () {
    $template = <<<'EOT'
Some content
<!-- prev-exception:start -->
<!-- prev-exception:end -->
More content
EOT;

    $result = $this->cleaner->clean($template, []);

    expect($result)->toBe(<<<'EOT'
Some content
More content
EOT);
});

test('it removes empty previous stacktrace section when previous_exceptions is empty', function () {
    $template = <<<'EOT'
Some content
<!-- prev-stacktrace:start -->
<details>
<summary>🔍 View Previous Exceptions</summary>

{previous_exceptions}

</details>
<!-- prev-stacktrace:end -->
More content
EOT;

    $replacements = [
        '{previous_exceptions}' => '',
    ];

    $result = $this->cleaner->clean($template, $replacements);

    expect($result)
        ->not->toContain('<!-- prev-stacktrace:start -->')
        ->not->toContain('<!-- prev-stacktrace:end -->')
        ->not->toContain('<details>')
        ->not->toContain('<summary>🔍 View Previous Exceptions</summary>')
        ->toContain('Some content')
        ->toContain('More content');
});

test('it preserves previous stacktrace section when previous_exceptions has content', function () {
    $template = <<<'EOT'
Some content
<!-- prev-stacktrace:start -->
<details>
<summary>🔍 View Previous Exceptions</summary>

{previous_exceptions}

</details>
<!-- prev-stacktrace:end -->
More content
EOT;

    $replacements = [
        '{previous_exceptions}' => 'Previous exception content',
    ];

    $result = $this->cleaner->clean($template, $replacements);

    expect($result)
        ->not->toContain('<!-- prev-stacktrace:start -->')
        ->not->toContain('<!-- prev-stacktrace:end -->')
        ->toContain('<details>')
        ->toContain('<summary>🔍 View Previous Exceptions</summary>')
        ->toContain('Previous exception content')
        ->toContain('Some content')
        ->toContain('More content');
});

test('it normalizes multiple newlines before signature', function () {
    $template = <<<'EOT'
Some content



<!-- Signature: test -->
EOT;

    $result = $this->cleaner->clean($template, []);

    expect($result)->toBe(<<<'EOT'
Some content

<!-- Signature: test -->
EOT);
});

test('it removes standalone section flags', function () {
    $template = <<<'EOT'
Some content
<!-- stacktrace:start -->
Content
<!-- extra:end -->
<!-- context:start -->
More content
EOT;

    $result = $this->cleaner->clean($template, []);

    expect($result)->toBe(<<<'EOT'
Some content
Content
More content
EOT);
});

test('it preserves content while removing section flags', function () {
    $template = <<<'EOT'
Some content
<!-- stacktrace:start -->
Stack trace content
<!-- stacktrace:end -->
<!-- context:start -->
{"key": "value"}
<!-- context:end -->
<!-- extra:start -->
Extra data
<!-- extra:end -->
More content
EOT;

    $replacements = [
        '{simplified_stack_trace}' => 'Stack trace content',
        '{context}' => '{"key": "value"}',
        '{extra}' => 'Extra data',
    ];

    $result = $this->cleaner->clean($template, $replacements);

    expect($result)->toBe(<<<'EOT'
Some content
Stack trace content
{"key": "value"}
Extra data
More content
EOT);
});

test('it removes empty sections even when wrapped in details blocks', function () {
    $template = <<<'EOT'
Some content
<!-- context:start -->
<details>
<summary>Context</summary>
<!-- context:end -->
</details>
More content
EOT;

    $result = $this->cleaner->clean($template, []);

    expect($result)
        ->not->toContain('<!-- context:start -->')
        ->not->toContain('<!-- context:end -->')
        ->not->toContain('<details>')
        ->not->toContain('<summary>Context</summary>')
        ->toContain('Some content')
        ->toContain('More content');
});

test('it preserves section content when wrapped in details blocks', function () {
    $template = <<<'EOT'
Some content
<!-- context:start -->
<details>
<summary>Context</summary>
{context}
</details>
<!-- context:end -->
More content
EOT;

    $replacements = [
        '{context}' => '{"key": "value"}',
    ];

    $result = $this->cleaner->clean($template, $replacements);

    expect($result)
        ->not->toContain('<!-- context:start -->')
        ->not->toContain('<!-- context:end -->')
        ->toContain('<details>')
        ->toContain('<summary>Context</summary>')
        ->toContain('{"key": "value"}')
        ->toContain('Some content')
        ->toContain('More content');
});

test('it handles nested details blocks correctly', function () {
    $template = <<<'EOT'
Some content
<!-- stacktrace:start -->
<details>
<summary>Stack Trace</summary>
{simplified_stack_trace}
<details>
<summary>Full Trace</summary>
{full_stack_trace}
</details>
</details>
<!-- stacktrace:end -->
More content
EOT;

    $replacements = [
        '{simplified_stack_trace}' => 'Simplified trace',
        '{full_stack_trace}' => 'Full trace',
    ];

    $result = $this->cleaner->clean($template, $replacements);

    expect($result)
        ->not->toContain('<!-- stacktrace:start -->')
        ->not->toContain('<!-- stacktrace:end -->')
        ->toContain('<details>')
        ->toContain('<summary>Stack Trace</summary>')
        ->toContain('Simplified trace')
        ->toContain('<summary>Full Trace</summary>')
        ->toContain('Full trace')
        ->toContain('Some content')
        ->toContain('More content');
});
