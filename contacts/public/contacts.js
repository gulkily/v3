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
const cancelPreview = document.querySelector('#cancel-preview');
const confirmPreview = document.querySelector('#confirm-preview');
const search = document.querySelector('#search');
const listing = document.querySelector('#contact-list');
const moreContacts = document.querySelector('#more-contacts');
const detail = document.querySelector('#detail');
const editForm = document.querySelector('#edit-form');
const editStatus = document.querySelector('#edit-status');
const importButton = form.querySelector('button');
const resultNames = {added: 'Add', updated: 'Update', unchanged: 'Unchanged', skipped: 'Skip'};
const contactFields = {company: 'Company', position: 'Title', email: 'Email', url: 'LinkedIn', connected_on: 'Connected on'};
const contactOrder = new Intl.Collator(undefined, {sensitivity: 'base'});
let reviewRows = [];
let rowsShown = 0;
let previewId = null;
let contacts = [];
let results = [];
let contactsShown = 0;
let selectedId = null;
let revision = null;

function message(text) {
    status.textContent = text;
    status.hidden = !text;
}

async function request(path, body) {
    const headers = {Authorization: 'Bearer ' + credential};
    if (typeof body === 'string') {
        headers['Content-Type'] = 'application/json';
    }
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

function nameOf(contact) {
    return [contact.first_name, contact.last_name].filter(Boolean).join(' ');
}

function appendContacts() {
    for (const contact of results.slice(contactsShown, contactsShown + 100)) {
        const item = document.createElement('li');
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'person';
        button.dataset.id = contact.id;
        button.setAttribute('aria-pressed', String(contact.id === selectedId));
        const name = document.createElement('strong');
        name.textContent = nameOf(contact);
        button.append(name);
        const work = [contact.company, contact.position].filter(Boolean).join(' · ');
        if (work) {
            const summary = document.createElement('span');
            summary.textContent = work;
            button.append(summary);
        }
        item.append(button);
        listing.append(item);
        contactsShown++;
    }
    moreContacts.hidden = contactsShown >= results.length;
}

function showResults() {
    const query = search.value.trim().toLocaleLowerCase();
    results = contacts.filter(contact => [nameOf(contact), contact.company, contact.position, contact.email]
        .some(field => field && field.toLocaleLowerCase().includes(query)));
    const count = document.querySelector('#contact-count');
    count.textContent = contacts.length === 0 ? 'Import connections to start your address book.'
        : results.length === 0 ? 'No contacts match your search.'
        : results.length + (results.length === 1 ? ' contact' : ' contacts');
    listing.replaceChildren();
    contactsShown = 0;
    appendContacts();
}

function showContact(contact, focus = false) {
    selectedId = contact.id;
    const heading = document.querySelector('#contact-name');
    heading.textContent = nameOf(contact);
    const fields = document.querySelector('#contact-fields');
    fields.replaceChildren();
    for (const [key, label] of Object.entries(contactFields)) {
        if (!contact[key]) {
            continue;
        }
        const parts = document.createElement('div');
        const term = document.createElement('dt');
        const definition = document.createElement('dd');
        term.textContent = label;
        if (key === 'url' && /^https:\/\/www\.linkedin\.com\/in\/[a-z0-9_.~%\-]+$/i.test(contact[key])) {
            const link = document.createElement('a');
            link.href = contact[key];
            link.target = '_blank';
            link.rel = 'noreferrer';
            link.textContent = 'View profile';
            definition.append(link);
        } else {
            definition.textContent = contact[key];
        }
        parts.append(term, definition);
        fields.append(parts);
    }
    editForm.querySelector('#note').value = contact.note || '';
    editForm.querySelector('#date').value = contact.last_contacted || '';
    editStatus.hidden = true;
    detail.hidden = false;
    for (const button of listing.querySelectorAll('button')) {
        button.setAttribute('aria-pressed', String(button.dataset.id === selectedId));
    }
    if (focus) {
        heading.focus();
    }
}

async function loadBook() {
    const result = await request('contacts');
    revision = result.revision;
    contacts = result.contacts.sort((a, b) => contactOrder.compare(nameOf(a), nameOf(b)));
    showResults();
    const selected = contacts.find(contact => contact.id === selectedId);
    if (selected) {
        showContact(selected);
    }
    document.querySelector('#intake').hidden = false;
    document.querySelector('#book').hidden = false;
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
    previewId = result.id;
    confirmPreview.hidden = !previewId;
    rowsShown = 0;
    previewRows.replaceChildren();
    appendRows();
    preview.hidden = false;
    document.querySelector('#preview-title').focus();
}

form.addEventListener('submit', async event => {
    event.preventDefault();
    if (importButton.disabled) {
        return;
    }
    importButton.disabled = true;
    preview.hidden = true;
    message('Reading connections…');
    try {
        showPreview(await request('preview', new FormData(form)));
        message('');
    } catch (error) {
        message(error.message);
    } finally {
        importButton.disabled = false;
    }
});
function closePreview() {
    preview.hidden = true;
    previewRows.replaceChildren();
    reviewRows = [];
    previewId = null;
    form.reset();
    input.focus();
}

morePreview.addEventListener('click', appendRows);
cancelPreview.addEventListener('click', async () => {
    confirmPreview.disabled = cancelPreview.disabled = importButton.disabled = true;
    if (previewId) {
        try {
            await request('cancel', JSON.stringify({id: previewId}));
        } catch (error) {
            message(error.message);
        }
    }
    closePreview();
    confirmPreview.disabled = cancelPreview.disabled = importButton.disabled = false;
});

confirmPreview.addEventListener('click', async () => {
    confirmPreview.disabled = cancelPreview.disabled = importButton.disabled = true;
    try {
        const {counts} = await request('confirm', JSON.stringify({id: previewId}));
        closePreview();
        await loadBook();
        message(`${counts.added} added · ${counts.updated} updated`);
    } catch (error) {
        message(error.message);
    } finally {
        confirmPreview.disabled = cancelPreview.disabled = importButton.disabled = false;
    }
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

document.querySelector('#search-form').addEventListener('submit', event => event.preventDefault());
search.addEventListener('input', showResults);
moreContacts.addEventListener('click', appendContacts);
listing.addEventListener('click', event => {
    const button = event.target.closest('button[data-id]');
    const contact = contacts.find(item => item.id === button?.dataset.id);
    if (contact) {
        showContact(contact, true);
    }
});
editForm.addEventListener('submit', async event => {
    event.preventDefault();
    const save = editForm.querySelector('button');
    save.disabled = true;
    try {
        await request('edit', JSON.stringify({
            id: selectedId,
            revision,
            note: editForm.querySelector('#note').value,
            last_contacted: editForm.querySelector('#date').value,
        }));
        await loadBook();
        editStatus.textContent = 'Changes saved.';
    } catch (error) {
        editStatus.textContent = error.message;
    } finally {
        editStatus.hidden = false;
        save.disabled = false;
    }
});

if (credential) {
    loadBook().catch(error => message(error.message));
} else {
    message('Open the URL printed by ./v3 contacts.');
}
