# Troubleshooting a download

Every failure belongs to one of four selection stages. Walk them in order — fixing an earlier stage often clears the later symptoms. The failure report of `dload get` usually names the stage: an API error points to stage 1, "no assets matched" to stages 2–3, a missing executable after extraction to stage 4.

## Stage 1 — release selection

Confirm the expected tag is visible and satisfies the constraint:

```bash
curl -s "https://api.github.com/repos/<owner>/<repo>/releases?per_page=20" \
  | grep -E '"(tag_name|prerelease|draft)"'
```

- `prerelease: true` while `version` demands stable → lower the stability (`@beta`/`@RC`/`@alpha`).
- `draft: true` → invisible to dload until published.
- Tags carry text before the version (`bun-v1.4.2`, `cli-v2.0.0`) → set `tag-prefix` on the `<repository>`; without it the tags do not parse and every release is skipped. See [`registry-entry.md`](registry-entry.md#tag-prefix).
- Tag is on GitHub but dload picks an older one → the version registry still holds a fresh check; rerun with `dload get --refresh`. A release inserted below the top of the listing (e.g. a GitLab release with a backdated date) needs `dload cache:clear <alias>`. See [`version-registry.md`](version-registry.md).
- API errors (HTTP 401, 403, rate limit) → set a valid `GITHUB_TOKEN` / `GITLAB_TOKEN`. When a check fails but the registry already holds releases of the repository, dload serves the stored ones (a rate limit is reported as "newer ones may be missing"); a never-seen repository fails outright.

## Stage 2 — asset filtering

Fetch the assets for the target tag and apply the `asset-pattern` by eye:

```bash
curl -s "https://api.github.com/repos/<owner>/<repo>/releases/tags/<tag>" \
  | grep -oE '"name": "[^"]+"' | sort -u
```

- Pattern has slash delimiters (`/^.../`); a bare `^...` silently matches nothing.
- Backslashes in XML attributes are literal — `\.exe`.
- Pattern also catches sibling tools, checksums or signatures → tighten it.

## Stage 3 — platform matching

After filtering, dload picks the host's variant with:

- OS: `/(?:\b|_)(windows|linux|darwin|macos|alpine|bsd|freebsd|win32|win64)(?:\b|_)/i`
- Arch: `/(?:\b|_)(amd64|arm64|aarch64|x86_64|x64|win64)(?:\b|_)/i`

A candidate that matches neither is discarded. Common offenders:

- Non-standard tokens: `linux64`, `osx`, `darwin_universal`.
- No arch separator: `tool-linux64.tar.gz` — the OS matches, no arch token is extractable.

Compare with the host: `php -r "echo PHP_OS_FAMILY, ' / ', php_uname('m'), PHP_EOL;"`.

## Stage 4 — binary extraction

The chosen asset is unpacked into `temp-dir`, then `binary.pattern` selects the executable.

- No pattern → exact match on `binary.name`; a nested or suffixed binary needs a pattern.
- Pattern over-matches (README, LICENSE) → tighten.
- Pattern under-matches → list the archive and fit the pattern to what is inside:
  ```bash
  tar -tzf path/to/asset.tar.gz       # or: unzip -l path/to/asset.zip
  ```
  `/^<name>(-.*)?(\.exe)?$/` covers mixed raw-and-archive distributions.
