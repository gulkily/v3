<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class CanonicalRecordFamily
{
    public const POST = 'post';
    public const IDENTITY = 'identity';
    public const PUBLIC_KEY = 'public_key';
    public const INSTANCE_PUBLIC = 'instance_public';

    private function __construct()
    {
    }
}
