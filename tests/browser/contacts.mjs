import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { createInterface } from 'node:readline';
import { chromium } from 'playwright-core';

const project = new URL('../../', import.meta.url);
const scratch = await mkdtemp(join(tmpdir(), 'v3-contacts-browser-'));
const data = join(scratch, 'contacts');
const devtools = process.env.NORI_BROWSER_CDP_URL || 'http://127.0.0.1:9222';

async function start(directory = data) {
    const process = spawn('./v3', ['contacts', '--data-dir=' + directory], {
        cwd: project,
        stdio: ['ignore', 'pipe', 'pipe'],
    });
    const output = createInterface({input: process.stdout});
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
            process.kill('SIGTERM');
            reject(new Error('The Contacts launcher did not report a URL.'));
        }, 5000);
        process.once('error', reject);
        process.once('exit', () => {
            clearTimeout(timeout);
            reject(new Error('The Contacts launcher exited.'));
        });
        output.once('line', line => {
            clearTimeout(timeout);
            const match = line.match(/http:\/\/127\.0\.0\.1:\d+\/#\S+/);
            if (match) {
                resolve({process, url: match[0]});
            } else {
                process.kill('SIGTERM');
                reject(new Error('The Contacts launcher returned an invalid URL.'));
            }
        });
    });
}

async function stop(server) {
    if (server && server.process.exitCode === null && server.process.signalCode === null) {
        const exit = once(server.process, 'exit');
        server.process.kill('SIGTERM');
        await exit;
    }
}

async function choose(page, csv, name = 'Connections.csv') {
    await page.locator('#file').setInputFiles({name, mimeType: 'text/csv', buffer: Buffer.from(csv)});
    await page.getByRole('button', {name: 'Preview import'}).click();
}

let server;
let browser;
let page;
try {
    server = await start();
    browser = await chromium.connectOverCDP(devtools);
    const context = browser.contexts()[0];
    page = await context.newPage();
    page.setDefaultTimeout(5000);
    const externalRequests = [];
    let appOrigin = new URL(server.url).origin;
    page.on('request', request => {
        if (new URL(request.url()).origin !== appOrigin) {
            externalRequests.push(request.url());
        }
    });
    await page.goto(server.url);
    assert.equal(new URL(page.url()).hash, '');
    await choose(page, 'Unknown,Columns\nExample,Text\n');
    await page.getByRole('status').getByText(/must have First Name and Last Name/).waitFor();
    const connections = 'First Name,Last Name,URL,Email Address,Company,Position\n'
        + 'River,Li,https://www.linkedin.com/in/example-river,river@example.invalid,<b>Example Studio</b>,Designer\n'
        + 'Amélie,Ng,https://www.linkedin.com/in/example-amelie,,Independent,Researcher\n';
    await choose(page, connections);
    await page.getByRole('heading', {name: 'Review import'}).waitFor();
    await page.getByText('Import connections to start your address book.').waitFor();
    await page.getByRole('button', {name: 'Cancel', exact: true}).click();
    await page.getByRole('heading', {name: 'Review import'}).waitFor({state: 'hidden'});
    await page.getByText('Import connections to start your address book.').waitFor();
    await choose(page, connections);
    await page.getByRole('heading', {name: 'Review import'}).waitFor();
    await page.getByRole('button', {name: 'Import contacts', exact: true}).click();

    const search = page.getByRole('searchbox', {name: 'Search contacts'});
    for (const query of ['River Li', 'Example Studio', 'Designer', 'river@example.invalid']) {
        await search.fill(query);
        await page.getByRole('button', {name: /River Li/}).waitFor();
        assert.equal(await page.getByRole('button', {name: /Amélie Ng/}).count(), 0);
    }
    await page.getByRole('button', {name: /River Li/}).press('Enter');
    await page.getByText('<b>Example Studio</b>', {exact: true}).waitFor();
    assert.equal(await page.locator('#contact-fields b').count(), 0);
    await page.getByRole('textbox', {name: 'Note', exact: true}).fill('Met at a design meetup.\n%%\nFollow up next month.');
    await page.getByLabel('Last contacted').fill('2026-11-15');
    await page.getByRole('button', {name: 'Save changes'}).click();
    await page.getByText('Changes saved.', {exact: true}).waitFor();

    await page.getByRole('textbox', {name: 'Note', exact: true}).fill('A draft to keep while importing.');
    await choose(page, 'First Name,Last Name,URL,Connected On\n'
        + 'River,Li,https://www.linkedin.com/in/example-river,15 Nov 2026\n');
    await page.getByRole('heading', {name: 'Review import'}).waitFor();
    await page.locator('#preview-rows').getByText(/15 Nov 2026/).waitFor();
    await page.getByRole('button', {name: 'Import contacts', exact: true}).click();
    await page.getByRole('heading', {name: 'Review import'}).waitFor({state: 'hidden'});
    await page.locator('#contact-fields').getByText('15 Nov 2026', {exact: true}).waitFor();
    assert.equal(await page.getByRole('textbox', {name: 'Note', exact: true}).inputValue(), 'A draft to keep while importing.');
    await stop(server);
    server = await start();
    appOrigin = new URL(server.url).origin;
    await page.goto(server.url);
    await page.getByRole('button', {name: /River Li/}).click();
    assert.equal(await page.getByRole('textbox', {name: 'Note', exact: true}).inputValue(), 'Met at a design meetup.\n%%\nFollow up next month.');
    assert.equal(await page.getByLabel('Last contacted').inputValue(), '2026-11-15');
    await page.getByText('river@example.invalid', {exact: true}).waitFor();
    const cdp = await context.newCDPSession(page);
    await cdp.send('Emulation.setDeviceMetricsOverride', {width: 390, height: 844, deviceScaleFactor: 1, mobile: true});
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
    await cdp.send('Emulation.clearDeviceMetricsOverride');
    if (process.env.CONTACTS_BROWSER_PROOF) {
        await page.screenshot({path: process.env.CONTACTS_BROWSER_PROOF, fullPage: true});
    }
    assert.deepEqual(externalRequests, []);
    console.log('Contacts browser flow passed: CSV recovery, import, search, edit, reduced reimport, and restart.');
} finally {
    await page?.close();
    await browser?.close();
    await stop(server);
    await rm(scratch, {recursive: true, force: true});
}
