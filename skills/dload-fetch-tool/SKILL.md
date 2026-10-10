---
name: dload-fetch-tool
description: Get a CLI tool — native binary or PHAR — from a GitHub release into a project folder with dload (`vendor/bin/dload`). Use when the user wants tool X (rr, temporal, buf, a PHAR…) downloaded into the project or added to `dload.xml`, when a download picks the wrong asset or none, or when setting up dload's release cache (version registry) locally or in CI, including GitHub API rate limits.
---

# Download a GitHub binary or PHAR into a project with dload

The task — "tool X from GitHub, available in this project's folder" — turns on one question: **does dload already know this tool?** If yes, `dload.xml` gets one `<download>` line. If no, the tool is also described inline in the same file. Native binaries and `.phar` files are both first-class; they differ in a couple of fields.

Everything happens in the consuming project's own `dload.xml` (dload itself is installed via `composer require internal/dload`).

## Step 1 — check what dload already knows

```bash
./vendor/bin/dload software
```

Prints every built-in tool with its alias. Tool listed → Step 3. Not listed → Step 2.

## Step 2 — describe a missing tool inline

Add a `<software>` block to the `<registry overwrite="false">` element of the project's `dload.xml`. Read [`references/registry-entry.md`](references/registry-entry.md) first: it lists the facts to collect from the GitHub release and how to design `asset-pattern` and `binary.pattern` for binaries and PHARs.

Done when the block has a `<repository>` whose `asset-pattern` matches every OS/arch variant of the tool and nothing else in the release.

## Step 3 — add the `<download>` action

```xml
<?xml version="1.0"?>
<dload
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/php-internal/dload/refs/heads/main/dload.xsd"
    temp-dir="./runtime"
>
    <actions>
        <download software="buf" />
        <download software="trap" type="phar" version="^1.1" />
    </actions>
    <registry overwrite="false">
        <!-- inline <software> blocks from Step 2, if any -->
    </registry>
</dload>
```

| Attribute | Purpose |
|---|---|
| `software` | Alias or name of the tool (built-in or inline). Required. |
| `version` | Composer-style constraint: `^2025.1`, `~1.0.0`, `^2.12.0@beta`, `^2.12.0-hotfix@rc`, one pre-release like `2025.1.0-rc.2`, or a range from one like `^2025.1.0-rc.1`. Omit for latest stable. Pre-releases order by stability (`nightly < snapshot < dev < unstable < alpha < preview < beta < pre < RC < stable`), then by number; `stable` by default. A pre-release in a constraint sets the minimum stability for any operator, even `<3.5.0-beta.2`; lower it with `@alpha`/`@dev`. A tagged build like `2025.1.0-rc.2-linux` orders right after its pre-release: an exact `2025.1.0-rc.2` skips it, `>2025.1.0-rc.2` takes it. Numbers may have more than three parts (`1.2.3.4`), in constraints too (`>=1.2.3.4.5`); trailing zero parts don't count (`1.2.3` = `1.2.3.0`). |
| `extract-path` | Target folder (default: project root). |
| `type` | `binary` (default), `phar` (required for PHAR — skips extraction), `archive` (unpack the whole asset keeping its folder layout). |

`type="archive"` is for tools that ship more than one executable — frontend bundles, docs, a binary that loads sibling libraries by relative path. Read [`references/archive-mode.md`](references/archive-mode.md) before using it.

The authoritative schema is `vendor/internal/dload/dload.xsd`.

## Step 4 — pick the destination folder

```xml
<actions>
    <download software="rr" extract-path="./bin" />
    <download software="trap" type="phar" extract-path="./tools" />
</actions>
```

`extract-path` is the literal folder the file lands in, for binaries and PHARs alike. Keep the folder in VCS with a `.gitkeep` and put the downloaded file in `.gitignore` — only `dload.xml` belongs in git.

## Step 5 — run dload and verify

```bash
./vendor/bin/dload get             # every <download> entry in dload.xml
./vendor/bin/dload get <alias>     # one entry
```

Done when the tool runs: `./bin/rr --version`, `php ./tools/trap.phar --version`.

Release lists are answered from a local cache — the **version registry** — for 10 minutes after the last check of a repository, so a release published moments ago may need `dload get --refresh`. For cache location and TTL, and for any CI setup — passing `GITHUB_TOKEN` and carrying the registry between runs, with GitHub Actions and GitLab examples — read [`references/version-registry.md`](references/version-registry.md).

## When the download fails

`dload get` exits non-zero and prints, per failed tool, the API error, the releases it checked, their assets, and the filter that rejected them. Read that report, then walk the selection stages in [`references/troubleshooting.md`](references/troubleshooting.md).
