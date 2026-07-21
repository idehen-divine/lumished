# Backend Development Rules

Patterns and rules for this application. Always activate the `backend-development-rules` skill and read the relevant rule file before writing code.

**Skill:** `.agents/skills/backend-development-rules/SKILL.md`

## Consistency First

Check sibling files before applying any rule. If a pattern exists, follow it.

## Rules

| Area | Rule File |
|---|---|
| Repository-Service pattern | `rules/repository-service-pattern.md` |
| Controllers | `rules/controllers.md` |
| Services | `rules/services.md` |
| Repositories | `rules/repositories.md` |
| Models | `rules/models.md` |
| Form Requests | `rules/form-requests.md` |
| API Resources | `rules/resources.md` |
| Enums | `rules/enums.md` |
| Response Codes | `rules/response-code-enum.md` |
| Code Quality & PHPDoc | `rules/code-quality.md` |
| Testing | `rules/testing.md` |
| Helpers | `rules/helpers.md` |

## Non-Negotiables

- PHPDoc on all methods — `@param` and `@return` required; `/** @inheritDoc */` on implementations
- `ResponseCode` enum only — never hardcode HTTP status codes
- `$request->validated()` only — never `$request->all()`
- Run `vendor/bin/sail bin pint --dirty --format agent` after every PHP change
- Write or update tests before finalizing
