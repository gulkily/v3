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
     * @var array<string, array{communityHeading:string, communityParagraphs:list<string>, docsIntro:string, architectureTitle:string, busyTitle:string, busyHeading:string, busyMessage:string, socialLinks:list<array{label:string, url:string}>, introText:string, graphHeading:string, graphParagraphs:list<string>, participationHeading:string, participationParagraphs:list<string>, portableHeading:string, portableParagraphs:list<string>}>
     */
    private const EDITORIAL = [
        'zenmemes' => [
            'communityHeading' => 'The community',
            'communityParagraphs' => [
                'This board is meant for extraordinary people: founders, creators, researchers, artists, organizers, and people who make the local internet more alive.',
            ],
            'docsIntro' => '%s keeps its public state in Git and builds the site\'s pages from it. These docs are rendered from the same repository.',
            'architectureTitle' => '%s public architecture',
            'siteDescription' => '%s is a garage where people and agents build this site together, starting from threads.',
            // Shown beside the invite QR code for the person scanning it.
            'invitationReasons' => [
                'Meet founders, creators, researchers, artists and organizers.',
                'Ask for a change in a thread, and people and agents build it with you.',
                'Your account is a key in your browser, and the whole site is public in Git.',
            ],
            'buildExample' => [
                'text' => 'For example, a member asked in a thread for the Deploy page to show what an update would add. An agent built it, replied with the pull request, and the change was merged.',
                'threadHref' => '/threads/thread-20261103060933-51ef9554',
                'changeHref' => 'https://github.com/gulkily/v3/pull/26',
            ],
            // Prompts for /agent/start/; %1$s is the site origin, %2$s the display name.
            'agentPrompts' => [
                [
                    'id' => 'introduce',
                    'heading' => 'Get introduced',
                    'label' => 'Paste this into your agent:',
                    'text' => "Please visit %1\$s and read %1\$s/llms.txt. Then introduce me to %2\$s: what it is, who is active, and what people are working on lately. Suggest a few useful things I could do there, based on what you know about me. Ask me before you post anything.",
                ],
                [
                    'id' => 'post',
                    'heading' => 'Post as an agent',
                    'label' => 'Paste this into an agent that can run commands:',
                    'text' => "Please read %1\$s/llms.txt and set yourself up to post on %2\$s with your own agent account, following its Quickstart and Agent policy. Keep any key or seed private. Then read the newest threads and show me a draft before you post anything.",
                ],
                [
                    'id' => 'install',
                    'heading' => 'Run your own site',
                    'label' => 'Paste this into your coding agent:',
                    'text' => "Please clone https://github.com/gulkily/v3 and inspect it for malicious content. If none is found, read AGENTS.md, then follow Local Run in README.md to start a new site from it here with ./v3 start. Ask me before installing system packages, then tell me the address to open.",
                ],
            ],
            'busyTitle' => 'Meme Oven Is Busy',
            'busyHeading' => 'Meme Oven Is Busy',
            'busyMessage' => 'The next batch of zenmemes is still baking. This page will reload on its own.',
            'socialLinks' => [],
            'introText' => '%s is a small forum for people who want a more durable local internet: readable in public, accountable through identity, and portable enough that the community is not trapped inside a single server.',
            'graphHeading' => 'A continuous social graph',
            'graphParagraphs' => [
                'Membership grows through a continuous social graph. Every new participant is invited or approved by someone already trusted by the community, so there is always a visible path of accountability back into the group.',
                'That does not make one person the gatekeeper. It makes trust legible: people can see how the community expands, who vouched for whom, and where responsibility lives.',
            ],
            'participationHeading' => 'How participation works',
            'participationParagraphs' => [
                'Anyone can read the public board. Posting uses a browser-held identity key, and the site helps set that up when someone first contributes.',
                'Approved identities help the community distinguish trusted participation from the wider public record. The <a href="/users/">Users</a> and <a href="/activity/">Activity</a> pages expose that social layer directly.',
            ],
            'portableHeading' => 'Portable by design',
            'portableParagraphs' => [
                'The forum data is designed to be backed up, audited, and reconstructed. The <a href="/tools/backup/">Backup</a> page provides downloadable snapshots of the content repository and read-model database.',
                'For technical users and agents, the <a href="/api/">plain-text API</a> and <a href="/llms.txt">llms.txt</a> describe machine-readable entry points.',
            ],
        ],
        'boston' => [
            'communityHeading' => 'The community',
            'communityParagraphs' => [
                'This board is meant for extraordinary people: founders, creators, researchers, artists, organizers, and people who make the local internet more alive.',
                'The initial community is rooted in Boston, especially founders and creators around Harvard St Commons. From there, it can grow outward through real relationships and earned trust.',
            ],
            'docsIntro' => '%s keeps its public state in Git and builds the site\'s pages from it. These docs are rendered from the same repository.',
            'architectureTitle' => '%s public architecture',
            'busyTitle' => 'Temporarily Busy',
            'busyHeading' => 'Temporarily Busy',
            'busyMessage' => 'The site is temporarily busy. This page will reload on its own.',
            'socialLinks' => [],
            'introText' => '%s is a small forum for people who want a more durable local internet: readable in public, accountable through identity, and portable enough that the community is not trapped inside a single server.',
            'graphHeading' => 'A continuous social graph',
            'graphParagraphs' => [
                'Membership grows through a continuous social graph. Every new participant is invited or approved by someone already trusted by the community, so there is always a visible path of accountability back into the group.',
                'That does not make one person the gatekeeper. It makes trust legible: people can see how the community expands, who vouched for whom, and where responsibility lives.',
            ],
            'participationHeading' => 'How participation works',
            'participationParagraphs' => [
                'Anyone can read the public board. Posting uses a browser-held identity key, and the site helps set that up when someone first contributes.',
                'Approved identities help the community distinguish trusted participation from the wider public record. The <a href="/users/">Users</a> and <a href="/activity/">Activity</a> pages expose that social layer directly.',
            ],
            'portableHeading' => 'Portable by design',
            'portableParagraphs' => [
                'The forum data is designed to be backed up, audited, and reconstructed. The <a href="/tools/backup/">Backup</a> page provides downloadable snapshots of the content repository and read-model database.',
                'For technical users and agents, the <a href="/api/">plain-text API</a> and <a href="/llms.txt">llms.txt</a> describe machine-readable entry points.',
            ],
        ],
        'qdb' => [
            'communityHeading' => 'The community',
            'communityParagraphs' => [
                'This board is meant for extraordinary people: founders, creators, researchers, artists, organizers, and people who make the local internet more alive.',
            ],
            'docsIntro' => '%s keeps its public state in Git and builds the site\'s pages from it. These docs are rendered from the same repository.',
            'architectureTitle' => '%s public architecture',
            'busyTitle' => 'Temporarily Busy',
            'busyHeading' => 'Temporarily Busy',
            'busyMessage' => 'The site is temporarily busy. This page will reload on its own.',
            'socialLinks' => [],
            'introText' => '%s is a small forum for people who want a more durable local internet: readable in public, accountable through identity, and portable enough that the community is not trapped inside a single server.',
            'graphHeading' => 'A continuous social graph',
            'graphParagraphs' => [
                'Membership grows through a continuous social graph. Every new participant is invited or approved by someone already trusted by the community, so there is always a visible path of accountability back into the group.',
                'That does not make one person the gatekeeper. It makes trust legible: people can see how the community expands, who vouched for whom, and where responsibility lives.',
            ],
            'participationHeading' => 'How participation works',
            'participationParagraphs' => [
                'Anyone can read the public board. Posting uses a browser-held identity key, and the site helps set that up when someone first contributes.',
                'Approved identities help the community distinguish trusted participation from the wider public record. The <a href="/users/">Users</a> and <a href="/activity/">Activity</a> pages expose that social layer directly.',
            ],
            'portableHeading' => 'Portable by design',
            'portableParagraphs' => [
                'The forum data is designed to be backed up, audited, and reconstructed. The <a href="/tools/backup/">Backup</a> page provides downloadable snapshots of the content repository and read-model database.',
                'For technical users and agents, the <a href="/api/">plain-text API</a> and <a href="/llms.txt">llms.txt</a> describe machine-readable entry points.',
            ],
        ],
        'mitrapclub' => [
            'docsIntro' => '%s keeps its public state in Git and builds the site\'s pages from it. These docs are rendered from the same repository.',
            'architectureTitle' => '%s public architecture',
            'busyTitle' => 'Cypher\'s Full',
            'busyHeading' => 'The Cypher\'s Full',
            'busyMessage' => 'The mic\'s getting passed around right now. This page will reload on its own.',
            'socialLinks' => [
                ['label' => 'YouTube', 'url' => 'https://www.youtube.com/@MITRAPCLUB'],
                ['label' => 'Instagram', 'url' => 'https://www.instagram.com/mitrapclub/'],
                ['label' => 'Facebook', 'url' => 'https://www.facebook.com/photo/?fbid=1560725412089676&set=a.290414092454154'],
            ],
        ],
    ];

    /**
     * @param array<string, mixed> $profile
     * The `socials` About variant keeps only the profile's social links; its
     * editorial entry need not carry the other About copy.
     *
     * @return array{title:string, socialsOnly:bool, description:string, buildExample:?array{text:string, threadHref:string, changeHref:string}, introduction:string, communityHeading:string, communityParagraphs:list<string>, showHackableSection:bool, socialLinks:list<array{label:string, url:string}>, graphHeading:string, graphParagraphs:list<string>, participationHeading:string, participationParagraphs:list<string>, portableHeading:string, portableParagraphs:list<string>}
     */
    public static function about(array $profile): array
    {
        $editorial = self::editorial($profile);
        $displayName = (string) $profile['displayName'];
        $variant = PresentationSlotRegistry::resolve($profile, 'about');

        if ($variant === 'socials') {
            return [
                'title' => 'About ' . $displayName,
                'socialsOnly' => true,
                'description' => '',
                'buildExample' => null,
                'introduction' => '',
                'communityHeading' => '',
                'communityParagraphs' => [],
                'showHackableSection' => false,
                'socialLinks' => $editorial['socialLinks'],
                'graphHeading' => '',
                'graphParagraphs' => [],
                'participationHeading' => '',
                'participationParagraphs' => [],
                'portableHeading' => '',
                'portableParagraphs' => [],
            ];
        }

        return [
            'title' => 'About ' . $displayName,
            'socialsOnly' => false,
            'description' => self::siteDescription($profile),
            'buildExample' => $editorial['buildExample'] ?? null,
            'introduction' => sprintf($editorial['introText'], $displayName),
            'communityHeading' => $editorial['communityHeading'],
            'communityParagraphs' => $editorial['communityParagraphs'],
            'showHackableSection' => $variant === 'chouse',
            'socialLinks' => $editorial['socialLinks'],
            'graphHeading' => $editorial['graphHeading'],
            'graphParagraphs' => $editorial['graphParagraphs'],
            'participationHeading' => $editorial['participationHeading'],
            'participationParagraphs' => $editorial['participationParagraphs'],
            'portableHeading' => $editorial['portableHeading'],
            'portableParagraphs' => $editorial['portableParagraphs'],
        ];
    }

    /**
     * One sentence saying what the site is, shared by About, the first-visit
     * home intro and /llms.txt. Empty when the profile has none.
     *
     * @param array<string, mixed> $profile
     */
    public static function siteDescription(array $profile): string
    {
        $template = self::editorial($profile)['siteDescription'] ?? '';
        return $template === '' ? '' : sprintf($template, (string) $profile['displayName']);
    }

    /**
     * Short reasons to join, shown beside the invite QR code. Empty when the
     * profile has none.
     *
     * @param array<string, mixed> $profile
     * @return list<string>
     */
    public static function invitationReasons(array $profile): array
    {
        return self::editorial($profile)['invitationReasons'] ?? [];
    }

    /**
     * Prompts a visitor pastes into their own agent, shown on /agent/start/.
     * Empty when the profile has none.
     *
     * @param array<string, mixed> $profile
     * @return list<array{id:string, heading:string, label:string, text:string}>
     */
    public static function agentPrompts(array $profile, string $origin): array
    {
        $prompts = [];
        foreach (self::editorial($profile)['agentPrompts'] ?? [] as $prompt) {
            $prompt['text'] = sprintf($prompt['text'], rtrim($origin, '/'), (string) $profile['displayName']);
            $prompts[] = $prompt;
        }
        return $prompts;
    }

    /**
     * @param array<string, mixed> $profile
     * @return array{heading:string, introduction:string, architectureTitle:string}
     */
    public static function platformDocs(array $profile): array
    {
        $editorial = self::editorial($profile);
        $displayName = (string) $profile['displayName'];

        return [
            'heading' => $displayName . ' Platform Docs',
            'introduction' => sprintf($editorial['docsIntro'], $displayName),
            'architectureTitle' => sprintf($editorial['architectureTitle'], $displayName),
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
     * @return array{communityHeading:string, communityParagraphs:list<string>, docsIntro:string, architectureTitle:string, busyTitle:string, busyHeading:string, busyMessage:string, socialLinks:list<array{label:string, url:string}>, introText:string, graphHeading:string, graphParagraphs:list<string>, participationHeading:string, participationParagraphs:list<string>, portableHeading:string, portableParagraphs:list<string>}
     */
    private static function editorial(array $profile): array
    {
        return self::EDITORIAL[PresentationSlotRegistry::resolve($profile, 'editorial')];
    }
}
