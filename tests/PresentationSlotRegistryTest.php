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

    public function testDefaultFaviconCarriesTabSizedImages(): void
    {
        // Browsers draw tab icons at 16 px; a lone 32 px image downscales into a muddy edge.
        $ico = (string) file_get_contents(__DIR__ . '/../public/favicon.ico');
        $header = unpack('vreserved/vtype/vcount', substr($ico, 0, 6));
        assertSame(1, $header['type']);
        $sizes = [];
        for ($i = 0; $i < $header['count']; $i++) {
            $width = ord($ico[6 + 16 * $i]);
            $sizes[] = $width === 0 ? 256 : $width;
        }
        sort($sizes);
        assertSame(true, in_array(16, $sizes, true), 'favicon has a 16 px image');
        assertSame(true, in_array(32, $sizes, true), 'favicon has a 32 px image');
    }
}
