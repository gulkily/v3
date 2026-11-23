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

    public function testConfirmPersistsAcrossRestartAndCancelDoesNotWrite(): void
    {
        $workspace = new ImportTestWorkspace();
        $directory = $workspace->root . '/contacts';
        $csv = "First Name,Last Name,URL,Email Address,Company,Position,Connected On\n"
            . "River,Li,https://www.linkedin.com/in/example-a,river@example.invalid,Example Studio,Designer,05 Oct 2026\n";
        $server = new ContactsTestServer($directory);
        try {
            [, $preview] = $server->upload('Connections.csv', $csv);
            assertSame(200, $server->request('/api/cancel', json_encode(['id' => $preview['id'] ?? 'missing']))[0]);
            assertSame(409, $server->request('/api/confirm', json_encode(['id' => $preview['id']]))[0]);
            assertSame([], json_decode($server->request('/api/contacts')[1], true)['contacts']);

            [, $preview] = $server->upload('Connections.csv', $csv);
            $confirm = json_encode(['id' => $preview['id']]);
            assertSame(403, $server->request('/api/confirm', $confirm, ['Origin' => 'http://127.0.0.1:9'])[0]);
            assertSame(200, $server->request('/api/confirm', $confirm)[0]);
            assertSame(409, $server->request('/api/confirm', $confirm)[0]);
        } finally {
            $server->stop();
        }

        $server = new ContactsTestServer($directory);
        try {
            $contacts = json_decode($server->request('/api/contacts')[1], true)['contacts'];
            assertSame(1, count($contacts));
            assertSame('River', $contacts[0]['first_name']);
            assertSame('river@example.invalid', $contacts[0]['email']);
            assertSame('Designer', $contacts[0]['position']);
            assertSame('05 Oct 2026', $contacts[0]['connected_on']);
        } finally {
            $server->stop();
        }
    }

    public function testFailedCommitLeavesExistingContactsReadableAndCanRetry(): void
    {
        $workspace = new ImportTestWorkspace();
        $directory = $workspace->root . '/contacts';
        $server = new ContactsTestServer($directory);
        try {
            [, $first] = $server->upload('Connections.csv', "First Name,Last Name\nNorth,Example\n");
            assertSame(200, $server->request('/api/confirm', json_encode(['id' => $first['id'] ?? 'missing']))[0]);
            [, $second] = $server->upload('Connections.csv', "First Name,Last Name\nSouth,Example\n");
            $body = json_encode(['id' => $second['id']]);
            chmod($directory, 0500);
            try {
                assertSame(500, $server->request('/api/confirm', $body)[0]);
                $contacts = json_decode($server->request('/api/contacts')[1], true)['contacts'];
                assertSame(['North'], array_column($contacts, 'first_name'));
            } finally {
                chmod($directory, 0700);
            }
            assertSame(200, $server->request('/api/confirm', $body)[0]);
            $contacts = json_decode($server->request('/api/contacts')[1], true)['contacts'];
            assertSame(['North', 'South'], array_column($contacts, 'first_name'));
        } finally {
            $server->stop();
        }
    }

    public function testReimportPreservesOmittedValuesAndKeepsDistinctProfiles(): void
    {
        $workspace = new ImportTestWorkspace();
        $server = new ContactsTestServer($workspace->root . '/contacts');
        $header = "First Name,Last Name,URL,Email Address,Company,Position\n";
        try {
            [, $first] = $server->upload('Connections.csv', $header
                . "River,Li,https://www.linkedin.com/in/river-a,river@example.invalid,Studio,Designer\n");
            assertSame(200, $server->request('/api/confirm', json_encode(['id' => $first['id']]))[0]);
            [, $preview] = $server->upload('Connections.csv', "First Name,Last Name,URL,Company\n"
                . "River,Li,https://linkedin.com/in/River-A/?tracking=1,New Studio\n"
                . "River,Li,https://www.linkedin.com/in/river-b,Another Company\n");
            assertSame(1, $preview['counts']['updated']);
            assertSame(1, $preview['counts']['added']);
            assertSame(200, $server->request('/api/confirm', json_encode(['id' => $preview['id']]))[0]);
            $contacts = json_decode($server->request('/api/contacts')[1], true)['contacts'];
            assertSame(2, count($contacts));
            assertSame('river@example.invalid', $contacts[0]['email']);
            assertSame('Designer', $contacts[0]['position']);
            assertSame('New Studio', $contacts[0]['company']);
            assertSame('Another Company', $contacts[1]['company']);

            [, $repeat] = $server->upload('Connections.csv', "First Name,Last Name,URL,Company\n"
                . "River,Li,https://www.linkedin.com/in/river-a,\n");
            assertSame(1, $repeat['counts']['unchanged']);
            assertSame(null, $repeat['id']);
        } finally {
            $server->stop();
        }
    }

    public function testUnlinkedAndConflictingRowsAreFlaggedBeforeImport(): void
    {
        $workspace = new ImportTestWorkspace();
        $server = new ContactsTestServer($workspace->root . '/contacts');
        try {
            [, $first] = $server->upload('Connections.csv', "First Name,Last Name,Company\nRiley,Example,Old Studio\n");
            assertSame(200, $server->request('/api/confirm', json_encode(['id' => $first['id']]))[0]);
            [, $preview] = $server->upload('Connections.csv', "First Name,Last Name,URL,Company\n"
                . "Riley,Example,,Old Studio\nRiley,Example,,Different Studio\n"
                . "Other,Person,https://www.linkedin.com/in/other,First Company\n"
                . "Other,Person,https://www.linkedin.com/in/other,Second Company\n"
                . "Third,Person,javascript:example,Third Company\n");
            assertSame(1, $preview['counts']['unchanged']);
            assertSame(4, $preview['counts']['skipped']);
            assertSame(null, $preview['id']);
            assertStringContains('uncertain', strtolower($preview['rows'][1]['reason']));
            assertStringContains('conflicting', strtolower($preview['rows'][2]['reason']));
        } finally {
            $server->stop();
        }
    }

    public function testStalePreviewCannotApplyOverAnotherLaunch(): void
    {
        $workspace = new ImportTestWorkspace();
        $directory = $workspace->root . '/contacts';
        $left = new ContactsTestServer($directory);
        $right = new ContactsTestServer($directory);
        try {
            [, $older] = $left->upload('Connections.csv', "First Name,Last Name\nFirst,Example\n");
            [, $newer] = $right->upload('Connections.csv', "First Name,Last Name\nSecond,Example\n");
            assertSame(409, $right->request('/api/confirm', json_encode(['id' => $older['id']]))[0]);
            assertSame(200, $right->request('/api/confirm', json_encode(['id' => $newer['id']]))[0]);
            assertSame(409, $left->request('/api/confirm', json_encode(['id' => $older['id']]))[0]);
            $contacts = json_decode($left->request('/api/contacts')[1], true)['contacts'];
            assertSame(['Second'], array_column($contacts, 'first_name'));
        } finally {
            $left->stop();
            $right->stop();
        }
    }
}
