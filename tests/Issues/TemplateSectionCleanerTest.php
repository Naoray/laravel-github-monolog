<?php

use Naoray\LaravelGithubMonolog\Issues\TemplateSectionCleaner;

beforeEach(function () {
    $this->cleaner = new TemplateSectionCleaner();
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
        '{extra}' => 'Extra data'
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
