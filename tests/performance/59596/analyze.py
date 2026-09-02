#!/usr/bin/env python3
"""Compact before/after table for the #59596 A/B results.

Usage: analyze.py <artifacts dir> [--deaccumulate]

--deaccumulate undoes the metric accumulation bug in the upstream specs
(fixed in this branch): for each result entry only the last len(timeToFirstByte)
values of every metric belong to that test case.
"""
import json, statistics as st, sys

d = sys.argv[1].rstrip('/') + '/'
deacc = '--deaccumulate' in sys.argv
before = {e['title']: e['results'] for e in json.load(open(d + 'before-performance-results.json'))}
after = {e['title']: e['results'] for e in json.load(open(d + 'performance-results.json'))}
metrics = ['wpTotal', 'wpBeforeTemplate', 'wpTemplate', 'wpFilesizeCalls', 'wpInlineCandidates',
           'wpInlineStylesUs', 'wpRegisterBlockStylesUs', 'wpBlockCssTransientBytes']

def acc(res, m):
    v = []
    for r in res:
        vals = r.get(m, [])
        if deacc:
            n = len(r.get('timeToFirstByte', vals))
            vals = vals[-n:]
        v += vals
    return v

def mad(v):
    med = st.median(v)
    return st.median([abs(x - med) for x in v])

print(f"{'case':50} {'metric':24} {'n':>3} {'before':>9} {'after':>9} {'diff':>8} {'MAD_b':>7} {'MAD_a':>7}")
for t in before:
    for m in metrics:
        b = acc(before[t], m); a = acc(after[t], m)
        if not b or not a:
            continue
        mb, ma = st.median(b), st.median(a)
        print(f"{t[:50]:50} {m:24} {len(b):>3} {mb:>9.1f} {ma:>9.1f} {ma-mb:>+8.1f} {mad(b):>7.1f} {mad(a):>7.1f}")
    print()
