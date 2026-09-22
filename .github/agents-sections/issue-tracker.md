# Issue tracker: GitHub

https://github.com/lipemat/limit-logins/issues

Tickets and plans for this repo live as GitHub issues. Use the `gh` CLI for all operations; it infers the repo from `git remote -v` when run inside a clone.

## Lifecycle

Every issue and ticket carries exactly one lifecycle state, applied as a label of the same name:

| State | Meaning | Set by |
| --- | --- | --- |
| `ready` | Available to pick up. | `plan-save`, `plan-to-tickets` |
| `in-progress` | Being implemented right now. | `plan-implement` |
| `pending-qa` | Ready for qa. Not a blocker. | `plan-implement` |

Moving an item to a state means removing the state label it currently holds and adding the new one. Moving a ticket to `pending-qa` also means ticking every satisfied acceptance-criteria checkbox in the ticket body.

`pending-qa` is terminal for the skills. Release is manual, so no skill closes an issue or moves it past this state; closing after release is the human's call.

## Conventions

- Use the `gh issue` commands for all operations (create, view, list, edit, comment, close, labels, `--parent` for sub-issues).
- When editing a body to check off acceptance criteria, preserve the rest of the body.

## When a skill says "publish to the issue tracker"

Create a GitHub issue.

## When a skill says "fetch the relevant ticket"

Run `gh issue view <number>`. When the ticket is a sub-issue, fetch its parent the same way.

## GitHub Project

Issue status is managed in a GitHub project. Any time an issue's lifecycle state changes, update both its label and its status in the project.

The ids of the project and the status field:

| Key | Value |
| --- | --- |
| Owner | `lipemat` |
| Project ID | `PVT_kwHOABnCdc4BkTy0` |
| Project Number | `12` |
| Status field ID | `PVTSSF_lAHOABnCdc4BkTy0zhjE0Zg` |

Each lifecycle state maps to one status option:

| Lifecycle state | Project status | Option ID |
| --- | --- | --- |
| `ready` | Ready | `61e4505c` |
| `in-progress` | In progress | `47fc9ee4` |
| `pending-qa` | Pending QA | `8922e42f` |

### Updating the status of an issue

1. Find the item's `<task-id>` via `gh project item-list 12 --owner lipemat --format json --jq ".items[] | select(.content.number==<issue number>) | .id"`.
2. Run `gh project item-edit --id <task-id> --project-id PVT_kwHOABnCdc4BkTy0 --field-id PVTSSF_lAHOABnCdc4BkTy0zhjE0Zg --single-select-option-id <option id>`, with "Option ID" from the lifecycle table above. No `tail` or the output will be blank.
