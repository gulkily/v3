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
     * @var array<string, array{communityHeading:string, communityParagraphs:list<string>, docsIntro:string, architectureTitle:string, architectureDescription:string, busyTitle:string, busyHeading:string, busyMessage:string, socialLinks:list<array{label:string, url:string}>, introText:string, graphHeading:string, graphParagraphs:list<string>, participationHeading:string, participationParagraphs:list<string>, portableHeading:string, portableParagraphs:list<string>}>
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
            'architectureDescription' => 'A member\'s browser creates signed writes to records in Git, which produce SQLite and static HTML views for readers.',
            'busyTitle' => 'Temporarily Busy',
            'busyHeading' => 'Temporarily Busy',
            'busyMessage' => 'The site is temporarily busy. Try again in a moment.',
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
                'The initial community is rooted in Boston, especially founders and creators around Harvard St Commons. From there, it can grow outward through real relationships and earned trust.',
            ],
            'docsIntro' => '%s keeps its public state in Git and builds the site\'s pages from it. These docs are rendered from the same repository.',
            'architectureTitle' => '%s public architecture',
            'architectureDescription' => 'A member\'s browser creates signed writes to records in Git, which produce SQLite and static HTML views for readers.',
            'busyTitle' => 'Temporarily Busy',
            'busyHeading' => 'Temporarily Busy',
            'busyMessage' => 'The site is temporarily busy. Try again in a moment.',
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
            'communityHeading' => 'The cypher',
            'communityParagraphs' => [
                'MIT Rap Club is a community for people who study rap as seriously as they perform it: cyphers, salons, and the Code Cypher hackathon pair live freestyle with rap theory and practice.',
                'The club grew out of MIT\'s rap-theory programming, including CMS/W and Lupe Fiasco\'s MLK Visiting Professorship, and keeps going through term-time cyphers, guest sessions, and events open to the wider MIT community.',
            ],
            'docsIntro' => '%s keeps its public state in Git and builds the site\'s pages from it. These docs are rendered from the same repository.',
            'architectureTitle' => '%s public architecture',
            'architectureDescription' => 'A member\'s browser creates signed writes to records in Git, which produce SQLite and static HTML views for readers.',
            'busyTitle' => 'Cypher\'s Full',
            'busyHeading' => 'The Cypher\'s Full',
            'busyMessage' => 'The mic\'s getting passed around right now. Try again in a moment.',
            'socialLinks' => [
                ['label' => 'YouTube', 'url' => 'https://www.youtube.com/@MITRAPCLUB'],
                ['label' => 'Instagram', 'url' => 'https://www.instagram.com/mitrapclub/'],
                ['label' => 'Facebook', 'url' => 'https://www.facebook.com/photo/?fbid=1560725412089676&set=a.290414092454154'],
            ],
            'introText' => '%s is where the rhymes get studied as hard as they get spit: a public record of cyphers, shows, and sessions, built so no bar gets lost to a feed\'s algorithm.',
            'graphHeading' => 'Built on who vouches for whom',
            'graphParagraphs' => [
                'The crew grows the same way a cypher does: someone already in the circle brings you in. Every new member is approved by someone the community already trusts, so there is always a clear line back to who vouched for whom.',
                'That is not one gatekeeper calling shots. It is the opposite: anyone can see how the crew grew and who stood behind who.',
            ],
            'participationHeading' => 'Anyone can watch, members hold the mic',
            'participationParagraphs' => [
                'The board is open to read for anyone. Posting means holding a browser-based key instead of just a username, and the site walks you through setting that up the first time you step up.',
                'Approved members are the ones the crew has actually vouched for. The <a href="/users/">Users</a> and <a href="/activity/">Activity</a> pages show exactly who that is and what they have been up to.',
            ],
            'portableHeading' => 'Nothing here lives or dies with one server',
            'portableParagraphs' => [
                'Every cypher, post, and thread is built to be backed up, checked, and rebuilt from scratch if it ever has to be. The <a href="/tools/backup/">Backup</a> page gives you a downloadable copy of the whole record.',
                'For anyone building their own tools on top of it, the <a href="/api/">plain-text API</a> and <a href="/llms.txt">llms.txt</a> are the machine-readable way in.',
            ],
        ],
    ];

    /**
     * @param array<string, mixed> $profile
     * @return array{title:string, introduction:string, communityHeading:string, communityParagraphs:list<string>, showHackableSection:bool, socialLinks:list<array{label:string, url:string}>, graphHeading:string, graphParagraphs:list<string>, participationHeading:string, participationParagraphs:list<string>, portableHeading:string, portableParagraphs:list<string>}
     */
    public static function about(array $profile): array
    {
        $editorial = self::editorial($profile);
        $displayName = (string) $profile['displayName'];

        return [
            'title' => 'About ' . $displayName,
            'introduction' => sprintf($editorial['introText'], $displayName),
            'communityHeading' => $editorial['communityHeading'],
            'communityParagraphs' => $editorial['communityParagraphs'],
            'showHackableSection' => PresentationSlotRegistry::resolve($profile, 'about') === 'chouse',
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
     * @return array{communityHeading:string, communityParagraphs:list<string>, docsIntro:string, architectureTitle:string, architectureDescription:string, busyTitle:string, busyHeading:string, busyMessage:string, socialLinks:list<array{label:string, url:string}>, introText:string, graphHeading:string, graphParagraphs:list<string>, participationHeading:string, participationParagraphs:list<string>, portableHeading:string, portableParagraphs:list<string>}
     */
    private static function editorial(array $profile): array
    {
        return self::EDITORIAL[PresentationSlotRegistry::resolve($profile, 'editorial')];
    }
}
