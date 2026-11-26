# Presentation slots

Purpose: Select existing page and layout variants for each site profile.
Scenario: MIT Rap Club selects the short About variant, showing its introductory paragraphs and social links.
Sources: [profiles](../../src/ForumRewrite/SiteProfileRegistry.php), [registered choices](../../src/ForumRewrite/PresentationSlotRegistry.php), [editorial content](../../src/ForumRewrite/ProfilePresentationContent.php), [About template](../../templates/pages/about.php), [profile tests](../../tests/SiteProfileRegistryTest.php), [content tests](../../tests/ProfilePresentationContentTest.php).
Boundary: SiteProfileRegistry validates every profile before choosing the active one. Each explicit selection must match a registered choice. Renaming a choice requires updating its profile selections together; an invalid selection prevents all profiles from starting.
