"""Reproduce the pinned KJV corpus from its original archive; no network access."""
import hashlib
import io
import json
from pathlib import Path
import re
import sys
import zipfile

ARCHIVE_SHA256 = '05b021ab465bd283e7df02409c78436487d582276b3a0b60d6fcf5d967a0af14'
BOOKS = dict(zip(
    'GEN EXO LEV NUM DEU JOS JDG RUT 1SA 2SA 1KI 2KI 1CH 2CH EZR NEH EST JOB PSA PRO ECC SNG ISA JER LAM EZK DAN HOS JOL AMO OBA JON MIC NAM HAB ZEP HAG ZEC MAL MAT MRK LUK JHN ACT ROM 1CO 2CO GAL EPH PHP COL 1TH 2TH 1TI 2TI TIT PHM HEB JAS 1PE 2PE 1JN 2JN 3JN JUD REV'.split(),
    'Genesis|Exodus|Leviticus|Numbers|Deuteronomy|Joshua|Judges|Ruth|1 Samuel|2 Samuel|1 Kings|2 Kings|1 Chronicles|2 Chronicles|Ezra|Nehemiah|Esther|Job|Psalms|Proverbs|Ecclesiastes|Song of Solomon|Isaiah|Jeremiah|Lamentations|Ezekiel|Daniel|Hosea|Joel|Amos|Obadiah|Jonah|Micah|Nahum|Habakkuk|Zephaniah|Haggai|Zechariah|Malachi|Matthew|Mark|Luke|John|Acts|Romans|1 Corinthians|2 Corinthians|Galatians|Ephesians|Philippians|Colossians|1 Thessalonians|2 Thessalonians|1 Timothy|2 Timothy|Titus|Philemon|Hebrews|James|1 Peter|2 Peter|1 John|2 John|3 John|Jude|Revelation'.split('|')))


def verse_text(raw):
    # Footnotes include numbered references and quoted alternatives: remove entire
    # notes before processing inline words, never turn their contents into scripture.
    raw = re.sub(r'\\f .*?\\f\*', '', raw)
    raw = re.sub(r'\\(\+?)w ([^|\\]+)\|[^\\]*\\\1w\*', r'\2', raw)
    raw = re.sub(r'\\\+?(?:add|nd|wj|tl)\*', '', raw)
    raw = re.sub(r'\\\+?(?:add|nd|wj|tl) ', '', raw)
    raw = raw.replace('¶', '')
    if '\\' in raw or '|' in raw:
        raise ValueError('Unrecognized source markup')
    return ' '.join(raw.split())


def build(data):
    if hashlib.sha256(data).hexdigest() != ARCHIVE_SHA256:
        raise ValueError('Source archive digest mismatch')
    archive = zipfile.ZipFile(io.BytesIO(data))
    books = {}
    for code, title in BOOKS.items():
        filename, = [n for n in archive.namelist() if n.endswith(f'{code}eng-kjv.usfm')]
        chapters = {}
        chapter = None
        for line in archive.read(filename).decode('utf-8-sig').splitlines():
            if line.startswith('\\c '):
                chapter = line[3:].strip()
                if not chapter.isdigit() or chapter in chapters:
                    raise ValueError('Invalid chapter')
                chapters[chapter] = {}
            elif line.startswith('\\v '):
                number, raw = line[3:].split(' ', 1)
                if chapter is None or not number.isdigit() or number in chapters[chapter]:
                    raise ValueError('Invalid verse')
                chapters[chapter][number] = verse_text(raw)
        books[title] = chapters
    if sum(len(v) for c in books.values() for v in c.values()) != 31102:
        raise ValueError('Unexpected canonical verse coverage')
    corpus = {'edition': 'KJV (eBible.org 1769 text)', 'translation': 'KJV',
              'archive_sha256': ARCHIVE_SHA256, 'import_version': 1, 'books': books}
    return corpus, archive.read('copr.htm')


if __name__ == '__main__':
    corpus, notice = build(Path(sys.argv[1]).read_bytes())
    destination = Path(sys.argv[2])
    destination.mkdir(parents=True, exist_ok=True)
    data = (json.dumps(corpus, ensure_ascii=False, indent=2) + '\n').encode()
    (destination / 'kjv.json').write_bytes(data)
    (destination / 'source-copyright.htm').write_bytes(notice)
    print('Corpus SHA-256:', hashlib.sha256(data).hexdigest())
