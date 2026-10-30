<?php

declare(strict_types=1);
namespace ForumRewrite\Canonical;

/** Removable canonical-header adapter. Never use for machine read-model JSON. */
final class LegacyAgentResponseMetadata
{
    public static function decode(?string $json, ?string $parentId): ?array
    {
        // Preserve historical empty-header and optional-field interpretation.
        if ($json === null || $json === '') return null;
        try { $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new CanonicalRecordParseException('Invalid Agent-Response JSON.'); }
        return AgentResponseMetadata::validate($data, $parentId);
    }
}
