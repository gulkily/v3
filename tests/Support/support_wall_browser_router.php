<?php

declare(strict_types=1);

// The signed-reaction browser router, with the seeded campaign as the support thread.
if (PHP_SAPI !== 'cli-server' || !getenv('SIGNED_REACTION_TEST_ROOT')) { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/autoload.php';
$profiles = \ForumRewrite\SiteProfileRegistry::all();
$supportThread = trim((string) @file_get_contents(getenv('SIGNED_REACTION_TEST_ROOT') . '/support-thread.txt'));
foreach ($profiles as $siteId => $profile) $profiles[$siteId]['supportThread'] = $supportThread;
\ForumRewrite\SiteProfileRegistry::setProfilesForTesting($profiles);
require __DIR__ . '/signed_reaction_browser_router.php';
