#!/usr/bin/env python3
"""Hand-counted occurrence units; code syntax must not inflate prose counts."""
import importlib.util
from pathlib import Path
import sys
sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('metrics', Path(__file__).resolve().parents[1] / 'scripts/markdown_metrics.py')
metrics = importlib.util.module_from_spec(spec)
spec.loader.exec_module(metrics)
sample = '''# Header

- [x] item
  - nested **bold** and **twice**

[inline](https://example.test) and [ref][r] and `**not strong**`
[r]: https://example.test

| A | B |
| --- | --- |
| 1 | 2 |

```md
# Example
```
'''
counts, locations = metrics.scan(sample)
expected = {'heading_atx': 1, 'list_unordered_item': 2, 'list_nested_item': 1,
            'task_checked': 1, 'strong_asterisk_candidate': 2, 'link_inline_candidate': 1,
            'link_reference_explicit_candidate': 1, 'reference_definition': 1,
            'inline_code': 1, 'table_separator': 1, 'table_row_candidate': 2,
            'table_cell_candidate': 4, 'fence_backtick': 1, 'code_example_line': 1}
for key, value in expected.items():
    assert counts[key] == value, (key, counts[key], value)
assert locations['heading_atx'] == [1]
assert counts['image_markdown_candidate'] == 0
assert metrics.scan('~~~\n# example')[0]['unclosed_fence'] == 1
assert metrics.scan('---\nmarp: true\n---\n# Title')[0]['heading_atx'] == 1
print('PASS Markdown metrics: hand-counted occurrences, locations, zero use, metadata and unclosed fence')
