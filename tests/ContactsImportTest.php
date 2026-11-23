<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/ContactsTestServer.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

final class ContactsImportTest
{
    public function testPreviewsLegacyAndReducedCsvWithoutSaving(): void
    {
        $workspace = new ImportTestWorkspace();
        $server = new ContactsTestServer($workspace->root . '/contacts');
        try {
            [$status, $preview] = $server->upload('Connections.csv',
                "\xEF\xBB\xBFNotes: Your first-degree connections\r\n\r\n"
                . "First Name,Last Name,Email Address,Company,Position,Connected On\r\n"
                . "Amélie,\"Ng, Jr.\",am@example.invalid,\"Example\r\nStudio\",Designer,\"10/22/14, 10:30 PM\"\r\n"
                . ",,bad@example.invalid,,,\r\n");
            assertSame(200, $status);
            assertSame(1, $preview['counts']['added']);
            assertSame(1, $preview['counts']['skipped']);
            assertStringContains('Amélie Ng, Jr.', json_encode($preview['rows'], JSON_UNESCAPED_UNICODE));
            assertStringContains('Example Studio', json_encode($preview['rows']));
            assertStringContains('Missing name', json_encode($preview['rows']));

            [$status, $reduced] = $server->upload('Connections.csv',
                "Connected On,URL,Last Name,First Name\n05 Oct 2026,https://www.linkedin.com/in/example-a,Li,River\n");
            assertSame(200, $status);
            assertSame(1, $reduced['counts']['added']);
            assertStringContains('River Li', json_encode($reduced['rows']));
            assertSame([], json_decode($server->request('/api/contacts')[1], true)['contacts']);
        } finally {
            $server->stop();
        }
    }

    public function testRefusedAndCrossOriginUploadsAllowRetry(): void
    {
        $workspace = new ImportTestWorkspace();
        $server = new ContactsTestServer($workspace->root . '/contacts');
        $csv = "First Name,Last Name,URL\nRiver,Li,https://www.linkedin.com/in/example-a\n";
        try {
            assertSame(403, $server->upload('Connections.csv', $csv, ['Origin' => 'http://127.0.0.1:9'])[0]);
            [$status, $bad] = $server->upload('Connections.csv', "Name,Contact\nExample,person@example.invalid\n");
            assertSame(422, $status);
            assertStringContains('Connections.csv', $bad['error']);
            assertSame(422, $server->upload('Connections.csv', "First Name,Last Name,URL\nRiver,\"unclosed,example")[0]);
            assertSame(413, $server->upload('Connections.csv', str_repeat('a', 16 * 1024 * 1024 + 1))[0]);
            [$status, $preview] = $server->upload('Connections.csv', $csv);
            assertSame(200, $status);
            assertSame(1, $preview['counts']['added']);
            assertSame([], json_decode($server->request('/api/contacts')[1], true)['contacts']);
        } finally {
            $server->stop();
        }
    }
}
