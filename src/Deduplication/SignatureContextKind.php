<?php

namespace Naoray\LaravelGithubMonolog\Deduplication;

enum SignatureContextKind: string
{
    case Http = 'http';
    case Job = 'job';
    case Command = 'command';
    case Other = 'other';
}
