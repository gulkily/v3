<?php

declare(strict_types=1);

namespace ForumRewrite;

/**
 * Closed, profile-selected copy for the shared presentation surfaces.
 *
 * This deliberately exposes content data rather than template paths: a
 * profile may select a known editorial or about variant, but cannot load an
 * arbitrary renderer or asset.
 */
final class ProfilePresentationContent
{
    /**
     * @var array<string, array{communityHeading:string, communityParagraphs:list<string>, docsIntro:string, architectureTitle:string, architectureDescription:string, busyTitle:string, busyHeading:string, busyMessage:string}>
     */
    private const EDITORIAL = [
        'zenmemes' => [
            'communityHeading' => 'The community',
            'communityParagraphs' => [
                'This board is meant for extraordinary people: founders, creators, researchers, artists, organizers, and people who make the local internet more alive.',
                'The initial community is rooted in Boston, especially founders and creators around Harvard St Commons. From there, it can grow outward through real relationships and earned trust.',
            ],
            'docsIntro' => '%s keeps its public state in Git and builds the site\'s pages from it. These docs are rendered from the same repository.',
            'architectureTitle' => '%s public architecture',
            'architectureDescription' => 'A member\'s browser creates signed writes to records in Git, which produce SQLite and static HTML views for readers.',
            'busyTitle' => 'Meme Oven Is Busy',
            'busyHeading' => 'Meme Oven Is Busy',
            'busyMessage' => 'The next batch of zenmemes is still baking. Try again in a moment.',
        ],
        'boston' => [
            'communityHeading' => 'The community',
            'communityParagraphs' => [
                'This board is meant for extraordinary people: founders, creators, researchers, artists, organizers, and people who make the local internet more alive.',
                'The initial community is rooted in Boston, especially founders and creators around Harvard St Commons. From there, it can grow outward through real relationships and earned trust.',
            ],
            'docsIntro' => '%s keeps its public state in Git and builds the site\'s pages from it. These docs are rendered from the same repository.',
            'architectureTitle' => '%s public architecture',
            'architectureDescription' => 'A member\'s browser creates signed writes to records in Git, which produce SQLite and static HTML views for readers.',
            'busyTitle' => 'Temporarily Busy',
            'busyHeading' => 'Temporarily Busy',
            'busyMessage' => 'The site is temporarily busy. Try again in a moment.',
        ],
        'qdb' => [
            'communityHeading' => 'The community',
            'communityParagraphs' => [
                'This board is meant for extraordinary people: founders, creators, researchers, artists, organizers, and people who make the local internet more alive.',
                'The initial community is rooted in Boston, especially founders and creators around Harvard St Commons. From there, it can grow outward through real relationships and earned trust.',
            ],
            'docsIntro' => '%s keeps its public state in Git and builds the site\'s pages from it. These docs are rendered from the same repository.',
            'architectureTitle' => '%s public architecture',
            'architectureDescription' => 'A member\'s browser creates signed writes to records in Git, which produce SQLite and static HTML views for readers.',
            'busyTitle' => 'Temporarily Busy',
            'busyHeading' => 'Temporarily Busy',
            'busyMessage' => 'The site is temporarily busy. Try again in a moment.',
        ],
    ];

    /**
     * @param array<string, mixed> $profile
     * @return array{title:string, introduction:string, communityHeading:string, communityParagraphs:list<string>, showHackableSection:bool}
     */
    public static function about(array $profile): array
    {
        $editorial = self::editorial($profile);
        $displayName = (string) $profile['displayName'];

        return [
            'title' => 'About ' . $displayName,
            'introduction' => $displayName . ' is a small forum for people who want a more durable local internet: readable in public, accountable through identity, and portable enough that the community is not trapped inside a single server.',
            'communityHeading' => $editorial['communityHeading'],
            'communityParagraphs' => $editorial['communityParagraphs'],
            'showHackableSection' => PresentationSlotRegistry::resolve($profile, 'about') === 'chouse',
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @return array{heading:string, introduction:string, architectureTitle:string, architectureDescription:string}
     */
    public static function platformDocs(array $profile): array
    {
        $editorial = self::editorial($profile);
        $displayName = (string) $profile['displayName'];

        return [
            'heading' => $displayName . ' Platform Docs',
            'introduction' => sprintf($editorial['docsIntro'], $displayName),
            'architectureTitle' => sprintf($editorial['architectureTitle'], $displayName),
            'architectureDescription' => $editorial['architectureDescription'],
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @return array{title:string, heading:string, message:string}
     */
    public static function busy(array $profile): array
    {
        $editorial = self::editorial($profile);

        return [
            'title' => $editorial['busyTitle'],
            'heading' => $editorial['busyHeading'],
            'message' => $editorial['busyMessage'],
        ];
    }

    /**
     * @param array<string, mixed> $profile
     * @return array{communityHeading:string, communityParagraphs:list<string>, docsIntro:string, architectureTitle:string, architectureDescription:string, busyTitle:string, busyHeading:string, busyMessage:string}
     */
    private static function editorial(array $profile): array
    {
        return self::EDITORIAL[PresentationSlotRegistry::resolve($profile, 'editorial')];
    }
}
