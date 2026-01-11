<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Monolog\LogRecord;
use Throwable;

class DefaultSignatureGenerator implements SignatureGeneratorInterface
{
    public function __construct(
        private readonly VendorFrameDetector $vendorFrameDetector = new VendorFrameDetector,
        private readonly SignatureContextExtractor $contextExtractor = new SignatureContextExtractor,
        private readonly MessageTemplate $messageTemplate = new MessageTemplate,
        private readonly int $maxFrames = 5,
        private readonly int $maxExceptionChainDepth = 3
    ) {}

    /**
     * Generate a unique signature for the log record
     */
    public function generate(LogRecord $record): string
    {
        $exception = $record->context['exception'] ?? null;

        if ($exception instanceof Throwable) {
            return $this->generateFromException($exception, $record);
        }

        return $this->generateFromMessage($record);
    }

    /**
     * Generate a signature from a message and context
     */
    private function generateFromMessage(LogRecord $record): string
    {
        $context = $this->contextExtractor->extract($record);

        // Extract caller frame if available
        $caller = data_get($record->extra, 'caller');
        $callerData = null;
        if (is_array($caller)) {
            $callerData = [
                'file' => $this->normalizePath((string) ($caller['file'] ?? '')),
                'func' => (string) ($caller['func'] ?? ''),
            ];
        }

        $payload = [
            'v' => 2,
            'kind' => $context['kind'],
            'context' => $context['data'],
            'origin' => [
                'caller' => $callerData,
            ],
            'variant' => [
                'msg_tpl' => $this->messageTemplate->template($record->message),
            ],
        ];

        return $this->hashPayload($payload);
    }

    /**
     * Generate a signature from an exception
     */
    private function generateFromException(Throwable $exception, LogRecord $record): string
    {
        $frames = $this->inAppFrames($exception, $this->maxFrames);

        // If we can't determine any in-app frames, fall back to the first frame in the trace
        if ($frames === []) {
            $first = $this->firstFrame($exception);
            if (is_array($first)) {
                $frames = [$first];
            }
        }

        $context = $this->contextExtractor->extract($record);

        $payload = [
            'v' => 2,
            'kind' => $context['kind'],
            'context' => $context['data'],
            'origin' => [
                'ex_chain' => $this->exceptionChainSignature($exception, $this->maxExceptionChainDepth),
                'frames' => array_map(
                    fn (array $frame) => $this->frameSignature($frame),
                    $frames
                ),
                'culprit' => $frames[0] ? $this->frameSignature($frames[0]) : null,
            ],
            'variant' => [
                'msg_tpl' => $this->messageTemplate->template($exception->getMessage()),
            ],
        ];

        return $this->hashPayload($payload);
    }

    /**
     * Get the first frame from exception trace
     *
     * @return array<string, mixed>|null
     */
    private function firstFrame(Throwable $e): ?array
    {
        $trace = $e->getTrace();

        return $trace[0] ?? null;
    }

    /**
     * Collect up to $limit in-app (non-vendor) frames from an exception trace.
     *
     * @return array<int, array<string, mixed>>
     */
    private function inAppFrames(Throwable $e, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $frames = [];

        foreach ($e->getTrace() as $frame) {
            if ($this->vendorFrameDetector->isVendorFrame($frame)) {
                continue;
            }

            $frames[] = $frame;

            if (count($frames) >= $limit) {
                break;
            }
        }

        return $frames;
    }

    /**
     * Reduce a frame to stable, high-signal identifiers.
     *
     * @param  array<string, mixed>  $frame
     * @return array{file:string, func:string}
     */
    private function frameSignature(array $frame): array
    {
        $file = $this->normalizePath((string) ($frame['file'] ?? ''));
        $func = (string) (($frame['class'] ?? '').($frame['type'] ?? '').($frame['function'] ?? ''));

        // Intentionally avoid line numbers; they change frequently and cause under-grouping.
        return [
            'file' => $file,
            'func' => $func,
        ];
    }

    /**
     * Build a bounded signature for an exception chain (previous exceptions).
     *
     * @return array<int, array{ex:string, code:int|string, message:string}>
     */
    private function exceptionChainSignature(Throwable $e, int $maxDepth): array
    {
        if ($maxDepth <= 0) {
            $maxDepth = 1;
        }

        $out = [];
        $depth = 0;

        while ($e && $depth < $maxDepth) {
            $out[] = [
                'ex' => $e::class,
                'code' => $e->getCode(),
                'message' => $this->messageTemplate->template($e->getMessage()),
            ];

            $e = $e->getPrevious();
            $depth++;
        }

        return $out;
    }

    /**
     * Hash a payload array into a signature string
     *
     * @param  array<string, mixed>  $payload
     */
    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES));
    }

    /**
     * Normalize a file path by stripping base path and normalizing separators
     */
    private function normalizePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        // Strip base_path() if available (same approach as StackTraceFormatter)
        if (function_exists('base_path')) {
            $base = rtrim(base_path(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
            if (str_starts_with($path, $base)) {
                $path = substr($path, strlen($base));
            }
        }

        return str_replace('\\', '/', $path);
    }
}
