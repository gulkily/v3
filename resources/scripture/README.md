# Verified KJV source

Pinned eBible.org standardized 1769 KJV text, retrieved 2026-10-16:
https://ebible.org/bible/details.php?id=eng-kjv
https://ebible.org/Scriptures/eng-kjv_usfm.zip

Archive SHA-256: `05b021ab465bd283e7df02409c78436487d582276b3a0b60d6fcf5d967a0af14`.
Imported JSON SHA-256: `ccb02dea3f3e2846d473e1423f2d5022609000123926aebdd46e9b7f6ae6895b`.
Reproduce with `python scripts/import_scripture_source.py ARCHIVE.zip resources/scripture`.
The importer refuses other archives. Runtime checks the imported digest.

Coverage: 66 books, Genesis through Revelation; 1,189 chapters, 31,102 verses.
Apocrypha and the translators' preface in the upstream package are not imported.
Full chapters are available for context; displayed verse text excludes footnotes,
lexical tags, headings, superscriptions outside numbered verses, and paragraph
markers. It preserves words and punctuation; whitespace is collapsed. Other
editions/translations are not silently substituted. An operator must deploy the
local files; no reader request downloads or updates the source.

See the original publisher's notice in `source-copyright.htm`, retained unmodified.
The source describes public-domain status outside the UK and a UK printing
qualification. Preserve its attribution and notices with redistributed files.
