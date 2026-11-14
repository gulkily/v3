<?php

declare(strict_types=1);

// The signed-reaction browser router, with the fixture's root-001 thread as the guestbook.
if (PHP_SAPI !== 'cli-server' || !getenv('SIGNED_REACTION_TEST_ROOT')) { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/autoload.php';
$profiles = \ForumRewrite\SiteProfileRegistry::all();
foreach ($profiles as $siteId => $profile) $profiles[$siteId]['guestbookThread'] = 'root-001';
\ForumRewrite\SiteProfileRegistry::setProfilesForTesting($profiles);
require __DIR__ . '/signed_reaction_browser_router.php';
