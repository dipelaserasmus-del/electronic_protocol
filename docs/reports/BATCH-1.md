
## Follow-up: automated review findings (PR "Batch 1 review fixes")
An automated reviewer (Codex) commented on the merged PRs. Every finding was verified against the code;
all were valid and are fixed with tests:

| Finding | Severity | Fix |
|---|---|---|
| Dev stack shared the database volume / container names with a normal installation in the same folder → `./dev.sh reset` could erase it | P1 | own Compose project `electronic-protocol-dev`, own containers `ep-dev-*` and volume `devdbdata` |
| S02 blocked the writer assigned for διεκπεραίωση from saving the outgoing reply (form allowed it) | P1 | policy keeps the form's exceptions (assignee, protocol without subject) + 2 tests |
| A cached config (`config:cache`) of a normal installation would be used by the dev stack | P2 | dev stack uses its own cache file paths |
| Header with an unknown charset threw an exception | P2 | caught; text kept readable + 2 tests |
| After a validation error the form regenerated the proposed number → untouched number treated as manual | P2 | form keeps the original proposed number + test |
| Tests deleted the dev stack's registration marker file | P2 | saved and restored around each test |
| Writers could choose numbers with a crafted request (B03) | P2 | already fixed in A02 |
