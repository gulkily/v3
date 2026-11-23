'use strict';

const fragment = location.hash.slice(1);
if (fragment) {
    history.replaceState(null, '', '/');
    sessionStorage.setItem('contacts-launch', fragment);
}
const credential = sessionStorage.getItem('contacts-launch');
const form = document.querySelector('#import-form');
const input = document.querySelector('#file');
const dropZone = document.querySelector('#drop-zone');
const status = document.querySelector('#status');
const preview = document.querySelector('#preview');
const previewRows = document.querySelector('#preview-rows');
const morePreview = document.querySelector('#more-preview');
const resultNames = {added: 'Add', updated: 'Update', unchanged: 'Unchanged', skipped: 'Skip'};
let reviewRows = [];
let rowsShown = 0;

function message(text) {
    status.textContent = text;
    status.hidden = !text;
}

async function request(path, body) {
    const headers = {Authorization: 'Bearer ' + credential};
    const response = await fetch('/api/' + path, {
        method: body === undefined ? 'GET' : 'POST',
        headers,
        body,
        credentials: 'omit',
        cache: 'no-store'
    });
    const result = await response.json();
    if (!response.ok) {
        throw new Error(result.error || 'Contacts could not complete that request. Try again.');
    }
    return result;
}

function appendRows() {
    for (const row of reviewRows.slice(rowsShown, rowsShown + 100)) {
        const line = previewRows.insertRow();
        const details = [row.company, row.position, row.email].filter(Boolean).join(' · ');
        const result = resultNames[row.status] + (row.reason ? ' · ' + row.reason : '');
        for (const value of [row.row, row.name, details, result]) {
            line.insertCell().textContent = value;
        }
        rowsShown++;
    }
    morePreview.hidden = rowsShown >= reviewRows.length;
}

function showPreview(result) {
    const counts = document.querySelector('#counts');
    counts.replaceChildren();
    for (const [key, label] of Object.entries(resultNames)) {
        const group = document.createElement('div');
        const term = document.createElement('dt');
        const count = document.createElement('dd');
        term.textContent = label;
        count.textContent = result.counts[key];
        group.append(term, count);
        counts.append(group);
    }
    reviewRows = result.rows;
    rowsShown = 0;
    previewRows.replaceChildren();
    appendRows();
    preview.hidden = false;
    document.querySelector('#preview-title').focus();
}

form.addEventListener('submit', async event => {
    event.preventDefault();
    const submit = form.querySelector('button');
    submit.disabled = true;
    preview.hidden = true;
    message('Reading connections…');
    try {
        showPreview(await request('preview', new FormData(form)));
        message('');
    } catch (error) {
        message(error.message);
    } finally {
        submit.disabled = false;
    }
});
morePreview.addEventListener('click', appendRows);
document.querySelector('#cancel-preview').addEventListener('click', () => {
    preview.hidden = true;
    previewRows.replaceChildren();
    reviewRows = [];
    form.reset();
    input.focus();
});

dropZone.addEventListener('dragover', event => {
    event.preventDefault();
    dropZone.dataset.dragging = '';
});
dropZone.addEventListener('dragleave', () => delete dropZone.dataset.dragging);
dropZone.addEventListener('drop', event => {
    event.preventDefault();
    delete dropZone.dataset.dragging;
    if (event.dataTransfer.files.length !== 1) {
        message('Choose one file to preview.');
        return;
    }
    input.files = event.dataTransfer.files;
    form.requestSubmit();
});

if (!credential) {
    form.querySelector('button').disabled = true;
    message('Open the URL printed by ./v3 contacts.');
}
