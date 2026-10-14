<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\PresentationSlotRegistry;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\View\FaviconRegistry;

final class PresentationSlotRegistryTest
{
    public function testProfilesSelectOnlyRegisteredSlots(): void
    {
        foreach (SiteProfileRegistry::all() as $profile) {
            foreach (PresentationSlotRegistry::all() as $slot => $definition) {
                assertSame(true, in_array(PresentationSlotRegistry::resolve($profile, $slot), $definition['choices'], true));
            }
        }
    }

    public function testInvalidSelectionsRecoverToSharedFallbackAndFailValidation(): void
    {
        $profile = SiteProfileRegistry::all()['zenmemes'];
        $profile['presentationSlots']['navigation'] = 'templates/evil.php';

        assertSame('forum', PresentationSlotRegistry::resolve($profile, 'navigation'));
        assertThrowsRuntime(static fn (): mixed => PresentationSlotRegistry::validate($profile['presentationSlots'], 'zenmemes'), 'Site profile zenmemes has an invalid presentation slot selection.');
    }

    public function testEveryMissingOrInvalidSlotUsesItsRegisteredFallback(): void
    {
        foreach (PresentationSlotRegistry::all() as $slot => $definition) {
            $missingSelection = SiteProfileRegistry::all()['zenmemes'];
            unset($missingSelection['presentationSlots'][$slot]);
            assertSame($definition['fallback'], PresentationSlotRegistry::resolve($missingSelection, $slot));

            $invalidSelection = SiteProfileRegistry::all()['zenmemes'];
            $invalidSelection['presentationSlots'][$slot] = 'untrusted-selection';
            assertSame($definition['fallback'], PresentationSlotRegistry::resolve($invalidSelection, $slot));
        }
    }

    public function testEachSiteResolvesItsOwnFaviconAndZenmemesKeepsTheDefault(): void
    {
        $expected = [
            'zenmemes' => '/favicon.ico',
            'chouse' => '/assets/favicon-chouse.ico',
            'qdb' => '/assets/favicon-qdb.ico',
            'mitrapclub' => '/assets/favicon-mitrapclub.ico',
        ];
        $profiles = SiteProfileRegistry::all();
        foreach ($expected as $site => $path) {
            assertSame($path, FaviconRegistry::resolve($profiles[$site]));
            assertSame(true, is_file(__DIR__ . '/../public' . $path));
        }

        $unselected = $profiles['chouse'];
        unset($unselected['presentationSlots']['favicon']);
        assertSame('/favicon.ico', FaviconRegistry::resolve($unselected));
    }
}
