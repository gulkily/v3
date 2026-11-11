<?php

declare(strict_types=1);

namespace ForumRewrite\Write;

use ForumRewrite\Canonical\PostRecord;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\Support\UnicodeTextPolicy;
use RuntimeException;

/** Strict authoring subset; parsing a canonical record alone does not authorize it. */
final class SignedPostBundleValidator
{
    public function __construct(private readonly bool $unicode = false, private readonly bool $emoji = false) {}

    public function validateRecord(string $bytes, bool $bootstrap = false, bool $enforcePolicy = true): PostRecord
    {
        try {
            $record = (new PostRecordParser())->parse($bytes);
        } catch (\ForumRewrite\Canonical\CanonicalRecordParseException $error) {
            // The generic parser can reject controls before authored-text validation.
            // Report field-relative diagnostics without accepting or rewriting the record.
            $parts = explode("\n\n", $bytes, 2);
            if (count($parts) === 2) {
                $fields = ['body' => $parts[1]];
                if (preg_match('/^Subject: (.*)$/m', $parts[0], $match)) $fields['subject'] = $match[1];
                foreach ($fields as $field => $value) {
                    if ($this->unicode) {
                        $policy = new UnicodeTextPolicy($this->emoji);
                        $field === 'body' ? $policy->normalizeBody($value, $field) : $policy->normalizeLine($value, $field);
                    } else {
                        \ForumRewrite\Support\TextValidationException::ascii($value, trim($value), $field, $field === 'body', "{$field} must be ASCII only.");
                    }
                }
            }
            throw $error;
        }
        $kind = $bootstrap ? 'bootstrap' : ($record->threadId === null ? 'thread' : 'reply');
        if (!preg_match('/^' . $kind . '-([0-9]{14})-[a-f0-9]{32}$/D', $record->postId, $match)
            || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $record->createdAt)
            || gmdate('YmdHis', strtotime($record->createdAt)) !== $match[1]) {
            throw new RuntimeException('Invalid client post ID or timestamp. Use kind-YYYYMMDDHHMMSS-32lowercasehex matching Created-At.');
        }
        if (!preg_match('/^openpgp:[a-f0-9]{40,64}$/D', $record->authorIdentityId ?? '')) {
            throw new RuntimeException('Author-Identity-ID must identify the signing key.');
        }
        $tags = implode(' ', $record->boardTags);
        if ($bootstrap) {
            if ($record->threadId !== null || $tags !== 'identity internal' || $record->subject !== 'account bootstrap'
                || $record->body !== "Automatic account bootstrap anchor.\n") {
                throw new RuntimeException('Bootstrap must be the dedicated internal account bootstrap record.');
            }
        } elseif ($enforcePolicy) {
            if (!preg_match('/^[a-z0-9-]+(?: [a-z0-9-]+)*$/D', $tags) || in_array('identity', $record->boardTags, true)) {
                throw new RuntimeException('Invalid ordinary post board tags. Identity records require their dedicated contract.');
            }
            if (str_contains($record->body, \ForumRewrite\Images\ImageReference::PREFIX)) {
                throw new RuntimeException('Image attachments require the prepared posting flow.');
            }
            if (!$this->unicode) {
                \ForumRewrite\Support\TextValidationException::ascii($record->body, str_replace("\r\n", "\n", trim($record->body)), 'body', true, 'body must be ASCII only.');
                if ($record->subject !== null) \ForumRewrite\Support\TextValidationException::ascii($record->subject, trim($record->subject), 'subject', false, 'subject must be printable ASCII.');
            }
            $body = $this->unicode ? (new UnicodeTextPolicy($this->emoji))->normalizeBody($record->body, 'body')
                : str_replace("\r\n", "\n", trim($record->body)) . "\n";
            if ($body !== $record->body || trim($body) === '' || (!$this->unicode && preg_match('/[^\x0A\x20-\x7E]/', $body))) {
                throw new RuntimeException('Body must already satisfy the instance text and canonical newline rules.');
            }
            if ($record->subject !== null && $record->subject !== '') {
                $subject = $this->unicode ? (new UnicodeTextPolicy($this->emoji))->normalizeLine($record->subject, 'subject') : trim($record->subject);
                if ($subject !== $record->subject || (!$this->unicode && preg_match('/[^\x20-\x7E]/', $subject))) {
                    throw new RuntimeException('Subject must already satisfy the instance text rules.');
                }
            }
        }
        $canonical = "Post-ID: {$record->postId}\nCreated-At: {$record->createdAt}\nBoard-Tags: {$tags}\n";
        if ($record->threadId !== null) {
            $canonical .= "Thread-ID: {$record->threadId}\nParent-ID: {$record->parentId}\n";
        }
        $canonical .= "Author-Identity-ID: {$record->authorIdentityId}\n";
        if ($record->threadId === null && ($record->subject ?? '') !== '') $canonical .= "Subject: {$record->subject}\n";
        if ($record->bodyFormat !== null) $canonical .= "Body-Format: {$record->bodyFormat}\n";
        if ($record->theme !== null) $canonical .= "Theme: {$record->theme}\n";
        $canonical .= "\n{$record->body}";
        // Every reader parses headers in any order, so order alone is stored as signed.
        if ($canonical !== $bytes && !self::sameHeadersInAnyOrder($canonical, $bytes)) {
            throw new RuntimeException('Record contains unsupported headers or noncanonical bytes.');
        }
        return $record;
    }

    private static function sameHeadersInAnyOrder(string $canonical, string $bytes): bool
    {
        $expected = explode("\n\n", $canonical, 2);
        $actual = explode("\n\n", $bytes, 2);
        if (count($actual) !== 2 || $actual[1] !== $expected[1]) {
            return false;
        }
        $expectedHeaders = explode("\n", $expected[0]);
        $actualHeaders = explode("\n", $actual[0]);
        sort($expectedHeaders);
        sort($actualHeaders);
        return $expectedHeaders === $actualHeaders;
    }
}
