<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Monolog\LogRecord;

/**
 * Detects the context kind the way v3.10 did, checking HTTP before jobs.
 *
 * @internal Only used by LegacySignatureGenerator. Remove in v4.
 */
class LegacySignatureContextExtractor extends SignatureContextExtractor
{
    protected function detectKind(LogRecord $record): SignatureContextKind
    {
        // Without HTTP context, both versions pick the same kind.
        return $this->hasHttpContext($record)
            ? SignatureContextKind::Http
            : parent::detectKind($record);
    }
}
