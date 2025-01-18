<?php

use Mockery\MockInterface;
use Monolog\Level;
use Monolog\LogRecord;
use Naoray\LaravelGithubMonolog\Issues\Formatters\ExceptionFormatter;
use Naoray\LaravelGithubMonolog\Issues\StubLoader;
use Naoray\LaravelGithubMonolog\Issues\TemplateRenderer;

beforeEach(function () {
    /** @var StubLoader&MockInterface */
    $this->stubLoader = Mockery::mock(StubLoader::class);
    /** @var ExceptionFormatter&MockInterface */
    $this->exceptionFormatter = Mockery::mock(ExceptionFormatter::class);

    $this->stubLoader->shouldReceive('load')
        ->with('issue')
        ->andReturn('**Log Level:** {level}\n{message}\n{previous_exceptions}\n{context}\n{extra}\n{signature}');
    $this->stubLoader->shouldReceive('load')
        ->with('comment')
        ->andReturn('# New Occurrence\n**Log Level:** {level}\n{message}');
    $this->stubLoader->shouldReceive('load')
        ->with('previous_exception')
        ->andReturn('## Previous Exception #{count}\n{type}\n{simplified_stack_trace}');

    $this->renderer = new TemplateRenderer(
        exceptionFormatter: $this->exceptionFormatter,
        stubLoader: $this->stubLoader,
    );
});

test('it renders basic log record', function () {
    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: [],
        extra: [],
    );

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**Log Level:** ERROR')
        ->toContain('Test message');
});

test('it renders title without exception', function () {
    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: [],
        extra: [],
    );

    $title = $this->renderer->renderTitle($record);

    expect($title)->toBe('[ERROR] Test message');
});

test('it renders title with exception', function () {
    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: ['exception' => new RuntimeException('Test exception')],
        extra: [],
    );

    $this->exceptionFormatter->shouldReceive('formatTitle')
        ->once()
        ->andReturn('[ERROR] RuntimeException: Test exception');

    $title = $this->renderer->renderTitle($record, $record->context['exception']);

    expect($title)->toBe('[ERROR] RuntimeException: Test exception');
});

test('it renders context data', function () {
    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: ['user_id' => 123],
        extra: [],
    );

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**Context:**')
        ->toContain('"user_id": 123');
});

test('it renders extra data', function () {
    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: [],
        extra: ['request_id' => 'abc123'],
    );

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record);

    expect($rendered)
        ->toContain('**Extra Data:**')
        ->toContain('"request_id": "abc123"');
});

test('it renders previous exceptions', function () {
    $previous = new RuntimeException('Previous exception');
    $exception = new RuntimeException('Test exception', previous: $previous);
    $record = new LogRecord(
        datetime: new DateTimeImmutable(),
        channel: 'test',
        level: Level::Error,
        message: 'Test message',
        context: ['exception' => $exception],
        extra: [],
    );

    $this->exceptionFormatter->shouldReceive('format')
        ->twice()
        ->andReturn([
            'simplified_stack_trace' => 'simplified stack trace',
            'full_stack_trace' => 'full stack trace',
        ]);

    $rendered = $this->renderer->render($this->stubLoader->load('issue'), $record, null, $exception);

    expect($rendered)
        ->toContain('Previous Exception #1')
        ->toContain(RuntimeException::class)
        ->toContain('simplified stack trace');
});
