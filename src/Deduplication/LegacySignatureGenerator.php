<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

use Monolog\LogRecord;

/**
 * Computes the signature v3.10 gave a record that v3.11 signs differently.
 *
 * v3.11 signs job errors by job instead of by the route that dispatched the
 * job, so issues opened before upgrading no longer match. The handler searches
 * for this signature once to keep commenting on those issues.
 *
 * @internal Remove in v4.
 */
class LegacySignatureGenerator
{
    public function __construct(
        private readonly SignatureContextExtractor $contextExtractor = new SignatureContextExtractor,
        private readonly SignatureContextExtractor $legacyContextExtractor = new LegacySignatureContextExtractor,
        private readonly DefaultSignatureGenerator $legacyGenerator = new DefaultSignatureGenerator(
            contextExtractor: new LegacySignatureContextExtractor,
        ),
    ) {}

    /**
     * Return the v3.10 signature, or null when the signature did not change.
     */
    public function generate(LogRecord $record): ?string
    {
        // The only change was checking jobs before HTTP context.
        $reclassified = $this->contextExtractor->extract($record)['kind'] === SignatureContextKind::Job->value
            && $this->legacyContextExtractor->extract($record)['kind'] === SignatureContextKind::Http->value;

        return $reclassified ? $this->legacyGenerator->generate($record) : null;
    }
}
