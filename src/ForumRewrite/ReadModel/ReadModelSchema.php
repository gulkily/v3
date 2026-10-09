<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

final class ReadModelSchema
{
    /**
     * @return list<string>
     */
    public static function statements(): array
    {
        return [
            'CREATE TABLE metadata (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            )',
            'CREATE TABLE posts (
                post_id TEXT PRIMARY KEY,
                created_at TEXT NOT NULL,
                thread_id TEXT NOT NULL,
                parent_id TEXT NULL,
                subject TEXT NULL,
                body TEXT NOT NULL,
                board_tags_json TEXT NOT NULL,
                thread_type TEXT NULL,
                author_identity_id TEXT NULL,
                author_profile_slug TEXT NULL,
                author_label TEXT NOT NULL DEFAULT \'guest\',
                post_tags_json TEXT NOT NULL DEFAULT \'[]\',
                post_score_total INTEGER NOT NULL DEFAULT 0,
                approved_flag_count INTEGER NOT NULL DEFAULT 0,
                is_hidden INTEGER NOT NULL DEFAULT 0,
                hidden_reason TEXT NULL,
                sequence_number INTEGER NOT NULL
            )',
            'CREATE TABLE threads (
                root_post_id TEXT PRIMARY KEY,
                root_post_created_at TEXT NOT NULL,
                last_activity_at TEXT NOT NULL,
                subject TEXT NULL,
                body_preview TEXT NOT NULL,
                reply_count INTEGER NOT NULL,
                last_post_id TEXT NOT NULL,
                board_tags_json TEXT NOT NULL,
                thread_labels_json TEXT NOT NULL,
                score_total INTEGER NOT NULL DEFAULT 0,
                vote_count INTEGER NOT NULL DEFAULT 0,
                event_date TEXT NULL,
                event_location TEXT NULL,
                event_link TEXT NULL,
                event_time TEXT NULL
            )',
            'CREATE TABLE profiles (
                identity_id TEXT PRIMARY KEY,
                profile_slug TEXT NOT NULL UNIQUE,
                username TEXT NOT NULL,
                username_token TEXT NOT NULL,
                fallback_label TEXT NOT NULL,
                signer_fingerprint TEXT NOT NULL,
                bootstrap_post_id TEXT NOT NULL,
                bootstrap_thread_id TEXT NOT NULL,
                public_key TEXT NOT NULL,
                is_approved INTEGER NOT NULL DEFAULT 0,
                approved_by_identity_id TEXT NULL,
                approved_by_profile_slug TEXT NULL,
                approved_by_label TEXT NULL,
                post_count INTEGER NOT NULL DEFAULT 0,
                thread_count INTEGER NOT NULL DEFAULT 0
            )',
            'CREATE TABLE username_routes (
                username_token TEXT PRIMARY KEY,
                identity_id TEXT NOT NULL
            )',
            'CREATE TABLE instance_public (
                singleton INTEGER PRIMARY KEY CHECK (singleton = 1),
                instance_name TEXT NOT NULL,
                admin_name TEXT NOT NULL,
                admin_contact TEXT NOT NULL,
                retention_policy TEXT NOT NULL,
                install_date TEXT NOT NULL,
                body TEXT NOT NULL
            )',
            'CREATE TABLE activity (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at TEXT NOT NULL,
                kind TEXT NOT NULL,
                record_family TEXT NOT NULL DEFAULT \'post\',
                action_key TEXT NULL,
                post_id TEXT NULL,
                thread_id TEXT NULL,
                label TEXT NOT NULL,
                board_tags_json TEXT NOT NULL,
                author_identity_id TEXT NULL,
                author_profile_slug TEXT NULL,
                author_username_token TEXT NULL,
                author_label TEXT NOT NULL,
                author_is_approved INTEGER NOT NULL DEFAULT 0,
                source_path TEXT NULL,
                source_commit_sha TEXT NULL
            )',
            'CREATE INDEX activity_recent_idx ON activity (created_at DESC, post_id DESC, id DESC)',
            'CREATE INDEX activity_post_id_idx ON activity (post_id)',
            'CREATE INDEX activity_action_key_idx ON activity (action_key)',
            'CREATE TABLE commits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sha TEXT NOT NULL UNIQUE,
                author_name TEXT NOT NULL,
                author_email TEXT NOT NULL,
                committed_at TEXT NOT NULL,
                subject TEXT NOT NULL,
                file_count INTEGER NOT NULL
            )',
            'CREATE INDEX commits_committed_at_idx ON commits (committed_at DESC, id DESC)',
        ];
    }

    public static function fingerprint(): string
    {
        return hash('sha256', implode("\n", self::statements()));
    }
}
