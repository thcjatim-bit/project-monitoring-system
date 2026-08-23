# Domain Docs

How the engineering skills should consume this repo's domain documentation when exploring the codebase.

**Layout: single-context.** One `CONTEXT.md` and one `docs/adr/` at the repo root.

## Before exploring, read these

- **`CONTEXT.md`** at the repo root, and
- **`docs/adr/README.md`** — the ADR index: one line per ADR with **when it's relevant to open**. Read the index first, then open only the ADRs that touch the area you're about to work in. Don't open ADR files to find out what they contain — that's what the index is for.

If any of these files don't exist, **proceed silently**. Don't flag their absence; don't suggest creating them upfront. The `/domain-modeling` skill (reached via `/grill-with-docs` and `/improve-codebase-architecture`) creates them lazily when terms or decisions actually get resolved.

## File structure

```
/
├── CONTEXT.md
├── docs/adr/
│   ├── README.md        <- index: judul + kapan relevan
│   ├── 0001-....md
│   └── 0002-....md
└── src/
```

If this repo later grows into a multi-package monorepo with genuinely separate contexts, switch to a root `CONTEXT-MAP.md` pointing at one `CONTEXT.md` per context, with context-scoped `src/<context>/docs/adr/` directories alongside the system-wide `docs/adr/`.

## Use the glossary's vocabulary

When your output names a domain concept (in an issue title, a refactor proposal, a hypothesis, a test name), use the term as defined in `CONTEXT.md`. Don't drift to synonyms the glossary explicitly avoids.

If the concept you need isn't in the glossary yet, that's a signal — either you're inventing language the project doesn't use (reconsider) or there's a real gap (note it for `/domain-modeling`).

## Flag ADR conflicts

If your output contradicts an existing ADR, surface it explicitly rather than silently overriding:

> _Contradicts ADR-0007 (event-sourced orders) — but worth reopening because…_

## Adding an ADR

When `/domain-modeling` writes a new ADR, add its row to `docs/adr/README.md` in the same step — title plus when it's relevant to open. An ADR missing from the index is invisible to the next session.
