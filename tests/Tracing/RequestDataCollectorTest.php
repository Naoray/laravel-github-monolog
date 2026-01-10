<?php

use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Context;
use Naoray\LaravelGithubMonolog\Tracing\RequestDataCollector;

beforeEach(function () {
    $this->collector = new RequestDataCollector;
});

afterEach(function () {
    Context::flush();
});

it('collects request data', function () {
    // Arrange
    $request = Request::create('https://example.com/test?foo=bar', 'POST', ['key' => 'value']);
    $request->headers->set('accept', 'application/json');
    $request->headers->set('cookie', 'sensitive-cookie');
    $request->headers->set('x-custom', 'custom-value');
    $request->headers->set('content-length', '1024');

    $event = new RequestHandled($request, Mockery::mock('Illuminate\Http\Response'));

    // Act
    ($this->collector)($event);

    // Assert
    $requestData = Context::get('request');
    expect($requestData)->toHaveKeys(['url', 'full_url', 'method', 'ip', 'headers', 'cookies', 'query', 'body']);
    expect($requestData['url'])->toBe('https://example.com/test');
    expect($requestData['full_url'])->toBe('https://example.com/test?foo=bar');
    expect($requestData['method'])->toBe('POST');
});

it('filters sensitive headers', function () {
    // Arrange
    $request = Request::create('https://example.com/test', 'GET');
    $request->headers->set('authorization', 'Bearer secret-token');
    $request->headers->set('cookie', 'session=abc123');
    $request->headers->set('safe-header', 'value');

    $event = new RequestHandled($request, Mockery::mock('Illuminate\Http\Response'));

    // Act
    ($this->collector)($event);

    // Assert
    $requestData = Context::get('request');
    expect($requestData['headers']['authorization'][0])->toContain('Bearer');
    expect($requestData['headers']['authorization'][0])->toContain('bytes redacted');
    expect($requestData['headers']['safe-header'][0])->toBe('value');
});

it('handles deleted temporary files gracefully', function () {
    // Arrange
    $request = Request::create('https://example.com/test', 'POST');

    // Create a mock uploaded file that throws RuntimeException when getSize() is called
    $file = Mockery::mock('Illuminate\Http\UploadedFile');
    $file->shouldReceive('getClientOriginalName')->andReturn('test.txt');
    $file->shouldReceive('getMimeType')->andReturn('text/plain');
    $file->shouldReceive('getSize')->andThrow(new \RuntimeException('stat failed'));

    $request->files->set('file', $file);
    $event = new RequestHandled($request, Mockery::mock('Illuminate\Http\Response'));

    // Act & Assert - Should not throw exception
    ($this->collector)($event);

    $requestData = Context::get('request');
    expect($requestData['files'])->toHaveKey('file');
    expect($requestData['files']['file'])->toHaveKey('name');
    expect($requestData['files']['file']['name'])->toBe('test.txt');
    expect($requestData['files']['file']['size'])->toBeNull();
    expect($requestData['files']['file']['mime_type'])->toBe('text/plain');
});

it('collects file upload data using UploadedFile fake', function () {
    // Arrange
    $request = Request::create('https://example.com/upload', 'POST', [
        'title' => 'Test Document',
    ]);

    // Create fake uploaded files using Laravel's fake method
    $file1 = UploadedFile::fake()->create('document.pdf', 1024); // 1KB file
    $file2 = UploadedFile::fake()->image('photo.jpg', 800, 600); // Image file

    $request->files->set('document', $file1);
    $request->files->set('photo', $file2);

    $event = new RequestHandled($request, Mockery::mock('Illuminate\Http\Response'));

    // Act
    ($this->collector)($event);

    // Assert
    $requestData = Context::get('request');
    expect($requestData)->toHaveKey('files');
    expect($requestData['files'])->toHaveKey('document');
    expect($requestData['files'])->toHaveKey('photo');

    // Verify document file data
    expect($requestData['files']['document'])
        ->toHaveKey('name')
        ->toHaveKey('size')
        ->toHaveKey('mime_type');
    expect($requestData['files']['document']['name'])->toBe('document.pdf');
    expect($requestData['files']['document']['size'])->toBeGreaterThan(0);
    expect($requestData['files']['document']['mime_type'])->toBe('application/pdf');

    // Verify photo file data
    expect($requestData['files']['photo'])
        ->toHaveKey('name')
        ->toHaveKey('size')
        ->toHaveKey('mime_type');
    expect($requestData['files']['photo']['name'])->toBe('photo.jpg');
    expect($requestData['files']['photo']['size'])->toBeGreaterThan(0);
    expect($requestData['files']['photo']['mime_type'])->toContain('image');
});

it('handles multiple files with same name using UploadedFile fake', function () {
    // Arrange
    $request = Request::create('https://example.com/upload', 'POST');

    // Create multiple files with the same name (array of files)
    $files = [
        UploadedFile::fake()->create('file.txt', 512),
        UploadedFile::fake()->create('file.txt', 768),
    ];

    $request->files->set('files', $files);

    $event = new RequestHandled($request, Mockery::mock('Illuminate\Http\Response'));

    // Act
    ($this->collector)($event);

    // Assert
    $requestData = Context::get('request');
    expect($requestData['files'])->toHaveKey('files');
    expect($requestData['files']['files'])->toBeArray();
    expect($requestData['files']['files'])->toHaveCount(2);

    // Verify both files are formatted correctly
    expect($requestData['files']['files'][0])
        ->toHaveKey('name')
        ->toHaveKey('size')
        ->toHaveKey('mime_type');
    expect($requestData['files']['files'][0]['name'])->toBe('file.txt');
    expect($requestData['files']['files'][0]['size'])->toBeGreaterThan(0);

    expect($requestData['files']['files'][1])
        ->toHaveKey('name')
        ->toHaveKey('size')
        ->toHaveKey('mime_type');
    expect($requestData['files']['files'][1]['name'])->toBe('file.txt');
    expect($requestData['files']['files'][1]['size'])->toBeGreaterThan(0);

    // Verify files have different sizes (even if fake creates different sizes than specified)
    expect($requestData['files']['files'][0]['size'])
        ->not->toBe($requestData['files']['files'][1]['size']);
});
