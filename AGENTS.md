## Agent skills

### Issue tracker

Issues are tracked in GitHub Issues on `lipemat/limit-logins`, with status synced to GitHub Project #12. See `.github/agents-sections/issue-tracker.md`.

## Benchmarks

`dev/bench/bench.sh` measures blocked login submissions with ApacheBench plus a timing mu-plugin. Environment overrides are listed in the script header, scenarios in its `load_scenario()`.

### Prerequisites

- The local site at `https://starting-point.loc` runs this checkout of the plugin, and `wp` reaches it from the current directory.
- `xdebug.mode=off` for the web server's PHP. Xdebug slows PHP code but not the native password hash, which skews every comparison.
- ApacheBench built with TLS. XAMPP on Windows ships it as `abs`, which the script prefers over `ab`.
- `curl`, and a web server that can write to the mu-plugins directory (the timing log lives there).

### Running

```bash
bash dev/bench/bench.sh <baseline|early-drop-disabled|early-drop>
```

The mode is a label only. Set up the plugin code and settings before each run:

| Mode                  | Setup                                                          |
|-----------------------|----------------------------------------------------------------|
| `baseline`            | Plugin code from before early drop.                            |
| `early-drop-disabled` | Plugin code with early drop, "Disable early drop" checked.     |
| `early-drop`          | Plugin code with early drop, "Disable early drop" unchecked.   |

When the baseline branch lacks `dev/bench/`, copy that directory outside the repo, check out the baseline branch and run the copy from the repo root. On Windows, keep the copy out of the system temp directory, where `ab` cannot read the request files.

A run takes about 5 minutes. It clears every logged failure before and after, uses a temporary `limitloginsbench` user, and aborts when a blocked scenario is not blocked or the reference gets blocked.

### Reading and comparing results

The results table on stdout is markdown, ready to paste into an issue or PR. Its header names the plugin `branch@commit` WordPress loaded and the Xdebug mode. Each row is one scenario at one concurrency:

- `ms/req`, `req/s`: from `ab`. At c=10, `ms/req` includes queueing behind the other clients.
- `Server ms`, `Peak MB`, `Queries`: per-request means from the mu-plugin. Server time runs from `REQUEST_TIME_FLOAT` to the last shutdown function, so it still counts requests that `exit` early.

Compare modes row by row on `Server ms` as `(mode - baseline) / baseline`. Run each mode twice on the same machine; a difference smaller than the reference row's run-to-run spread is noise. The reference row is the regression check for logins that are not blocked.
