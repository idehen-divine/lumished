# Coding Principles

## 1. Think Before Coding

**Don't assume. Don't hide confusion. Surface tradeoffs.**

Before implementing:
- State your assumptions explicitly. If uncertain, ask.
- If multiple interpretations exist, present them - don't pick silently.
- If a simpler approach exists, say so. Push back when warranted.
- If something is unclear, stop. Name what's confusing. Ask.

## 2. Simplicity First

**Minimum code that solves the problem. Nothing speculative.**

- No features beyond what was asked.
- No abstractions for single-use code.
- No "flexibility" or "configurability" that wasn't requested.
- No error handling for impossible scenarios.
- If you write 200 lines and it could be 50, rewrite it.

Ask yourself: "Would a senior engineer say this is overcomplicated?" If yes, simplify.

## 3. Surgical Changes

**Touch only what you must. Clean up only your own mess.**

When editing existing code:
- Don't "improve" adjacent code, comments, or formatting.
- Don't refactor things that aren't broken.
- Match existing style, even if you'd do it differently.
- If you notice unrelated dead code, mention it - don't delete it.

When your changes create orphans:
- Remove imports/variables/functions that YOUR changes made unused.
- Don't remove pre-existing dead code unless asked.

The test: Every changed line should trace directly to the user's request.

## 4. Goal-Driven Execution

**Define success criteria. Loop until verified.**

Transform tasks into verifiable goals:
- "Add validation" → "Write tests for invalid inputs, then make them pass"
- "Fix the bug" → "Write a test that reproduces it, then make it pass"
- "Refactor X" → "Ensure tests pass before and after"

For multi-step tasks, state a brief plan:
```
1. [Step] → verify: [check]
2. [Step] → verify: [check]
3. [Step] → verify: [check]
```

Strong success criteria let you loop independently. Weak criteria ("make it work") require constant clarification.

## Skills

Do not load any skill by default. Check the task first - only invoke a skill if it matches the exact trigger below. Never
invoke a skill just because it exists.

- `/architect` - before building something non-trivial with no plan yet
- `/audit` - to bootstrap or refresh repo context and MEMORY.md files
- `/check` - when a feature is done and needs a production check or review
- `/debug` - when something is broken and the fix is not obvious
- `/develop` - when implementing an approved feature or slice
- `/document` - for PR, changelog, release note, or postmortem writing
- `/scope` - to turn an idea into a coarse product scope
- `/sync` - as the last step after a change to keep durable knowledge current
- `/test` - to write tests for code you just built or changed

## Durable Knowledge

Project-specific context and conventions are stored in `MEMORY.md`. This file is the durable knowledge base that skills maintain and reference — it persists across rebuilds of the Boost-generated files. All skills should read from and write to `MEMORY.md` for project memory.
