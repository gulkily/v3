<?php

declare(strict_types=1);

use ForumRewrite\BrowserRuntimeProfile;
use ForumRewrite\PresentationSlotRegistry;
use ForumRewrite\SiteProfileRegistry;

final class ProfileRegressionContract
{
    /** @return array<string, array{profile: array<string, mixed>, runtime: array<string, string>, presentation: array<string, mixed>}> */
    public static function all(): array
    {
        $contracts = [];
        foreach (SiteProfileRegistry::all() as $profileId => $profile) {
            $contracts[$profileId] = [
                'profile' => $profile,
                'runtime' => BrowserRuntimeProfile::fromProfile($profile),
                'presentation' => self::presentation($profile),
            ];
        }

        return $contracts;
    }

    /** @param array<string, mixed> $profile
     *  @return array{boardPath: string, boardMarker: string, navigationMarker: string, composePath: string, composeMarker: string, aboutHackable: bool, brandedTheme: ?string}
     */
    private static function presentation(array $profile): array
    {
        $isQdbExperience = in_array('qdb', $profile['enabledExperienceKeys'] ?? [], true);
        $navigation = PresentationSlotRegistry::resolve($profile, 'navigation');
        $card = PresentationSlotRegistry::resolve($profile, 'boardCard');
        $compose = PresentationSlotRegistry::resolve($profile, 'compose');
        $about = PresentationSlotRegistry::resolve($profile, 'about');
        $stylesheet = PresentationSlotRegistry::resolve($profile, 'brandedStylesheet');

        return [
            'boardPath' => $isQdbExperience ? '/latest' : '/?view=all&sort=newest',
            'boardMarker' => $card === 'quote' ? 'class="card post-card quote-card"' : 'class="card thread-card"',
            'navigationMarker' => $navigation === 'qdb' ? '>Welcome</a>' : '>Board</a>',
            'composePath' => $isQdbExperience ? '/add' : '/compose/thread',
            'composeMarker' => $compose === 'qdb' ? 'class="stack qdb-add-page"' : '<h1>Compose Thread</h1>',
            'aboutHackable' => $about === 'chouse',
            'brandedTheme' => $stylesheet === 'site' ? null : $stylesheet,
        ];
    }
}
