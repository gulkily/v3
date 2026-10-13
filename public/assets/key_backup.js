(function () {
  'use strict';
  const MAX_BYTES = 131072;
  const utf8 = new TextEncoder();
  async function api() {
    const loader = window.__forumOpenPgpLoader;
    return loader ? loader.load() : window.openpgp;
  }
  function password(value) {
    if (typeof value !== 'string' || !value.trim()) throw new Error('Enter a backup passphrase.');
  }
  async function inspect(privateKeyText) {
    if (typeof privateKeyText !== 'string' || utf8.encode(privateKeyText).length > 32768) {
      throw new Error('The private key is missing or too large for a backup.');
    }
    const pgp = await api();
    const key = await pgp.readPrivateKey({ armoredKey: privateKeyText });
    if (!key.isDecrypted()) throw new Error('Unlock the browser private key before backing it up.');
    return key.getFingerprint().toLowerCase();
  }
  async function readBackup(text, passphrase) {
    password(passphrase);
    if (typeof text !== 'string' || utf8.encode(text).length > MAX_BYTES || !text.startsWith('-----BEGIN PGP MESSAGE-----')) {
      throw new Error('Choose an original encrypted key backup text file or Starfield PNG.');
    }
    const pgp = await api();
    let envelope;
    try {
      const result = await pgp.decrypt({
        message: await pgp.readMessage({ armoredMessage: new ReadableStream({
          start(controller) { controller.enqueue(text); controller.close(); },
        }) }), passwords: [passphrase], format: 'binary',
        config: 'maxDecompressedMessageSize' in pgp.config ? { maxDecompressedMessageSize: 65536 } : {},
      });
      const reader = result.data.getReader();
      const parts = []; let size = 0;
      try {
        while (true) {
          const { value, done } = await reader.read();
          if (done) break;
          size += value.length;
          if (size > 65536) { await reader.cancel(); throw new Error('size'); }
          parts.push(value);
        }
      } finally { reader.releaseLock(); }
      const bytes = new Uint8Array(size); let offset = 0;
      for (const part of parts) { bytes.set(part, offset); offset += part.length; }
      envelope = JSON.parse(new TextDecoder('utf-8', { fatal: true }).decode(bytes));
    } catch (_) {
      throw new Error('Could not unlock this backup. Check the passphrase and use the original downloaded file.');
    }
    if (!envelope || envelope.type !== 'forum-key-backup' || envelope.version !== 1) {
      throw new Error('This file is not a supported key backup.');
    }
    const fingerprint = await inspect(envelope.privateKeyText);
    if (fingerprint !== envelope.fingerprint) throw new Error('Backup fingerprint does not match its private key.');
    return { privateKeyText: envelope.privateKeyText, fingerprint };
  }
  async function createBackup(privateKeyText, passphrase) {
    password(passphrase);
    const fingerprint = await inspect(privateKeyText);
    const pgp = await api();
    const data = utf8.encode(JSON.stringify({ type: 'forum-key-backup', version: 1, fingerprint, privateKeyText }));
    const text = await pgp.encrypt({
      message: await pgp.createMessage({ binary: data }), passwords: [passphrase], format: 'armored',
      config: { preferredCompressionAlgorithm: pgp.enums.compression.uncompressed },
    });
    const verified = await readBackup(text, passphrase);
    if (verified.privateKeyText !== privateKeyText || verified.fingerprint !== fingerprint) {
      throw new Error('Backup verification failed. Your browser key has not changed.');
    }
    return { text, fingerprint };
  }
  window.ForumKeyBackup = { createBackup, readBackup, MAX_BYTES };
})();
