# Version registry — dload's release cache

dload stores the release lists it fetches from GitHub/GitLab in a local **version registry**: one JSON file per repository with its releases and asset links. Releases never expire from it; only the **last check** of a repository does. While the last check is younger than `cache-ttl`, `dload get` makes zero API requests for that repository. Once it is older, dload asks only for releases published since — usually one request. Older release pages are fetched lazily, when a version constraint actually needs them.

The registry holds metadata only — tags, names, download links. Downloads bypass it and tokens are never written to it, so the directory is safe to share between tokens, machines and CI runs.

## Settings

On by default, in the per-user cache directory: `$XDG_CACHE_HOME/dload`, `%LOCALAPPDATA%\dload\cache` on Windows, `~/.cache/dload` otherwise.

| `dload.xml` attribute | Environment variable | Default | Meaning |
|---|---|---|---|
| `cache-dir` | `DLOAD_CACHE_DIR` | user cache directory | Registry directory. A relative path resolves against the working directory. |
| `cache-ttl` | `DLOAD_CACHE_TTL` | `600` | Seconds a repository's last check stays valid. `0` disables the registry. |

The environment variable wins over the attribute.

```xml
<dload temp-dir="./runtime" cache-dir="./runtime/dload-cache" cache-ttl="3600">
```

A longer TTL trades freshness for fewer requests: with a floating constraint (`^2.0`) dload keeps resolving to the newest *known* release until the check expires. Raise it only when versions are pinned or lag is acceptable.

## Commands

```bash
./vendor/bin/dload get --refresh        # check every repository now, ignoring the TTL
./vendor/bin/dload cache:clear rr       # forget the repositories rr is served from
./vendor/bin/dload cache:clear --force  # wipe the whole registry
```

- A release published after the last check → `--refresh`.
- A release inserted *below* the top of the listing (e.g. a GitLab release with a backdated `released_at`) is invisible to a check → `cache:clear <alias>`.

## Failure behaviour

- **Stale-on-error.** A failed check (network error, rate limit) on a repository the registry already knows serves the stored releases and reports it. A repository never seen before fails loudly.
- A stored release whose assets vanished upstream is dropped as soon as its download fails, and the list is re-fetched before the run gives up.
- GitHub draft releases are never served.

## CI

Two things together keep CI off the rate limit: **a token** for every request dload does make, and **a carried registry** so it makes few.

- **Token.** Pass `GITHUB_TOKEN` (and `GITLAB_TOKEN` for GitLab sources, `DLOAD_TOKEN_<HOST>` for a [self-hosted `server`](registry-entry.md#server)) into the environment of every step that runs dload — including steps where dload runs indirectly, e.g. from a test suite. Anonymous access is limited to 60 requests per hour per runner IP, which shared runners exhaust quickly. In GitHub Actions `secrets.GITHUB_TOKEN` shares a 1,000 requests/hour limit across all jobs of the repository; a wide matrix that still runs out needs a personal access token.
- **Registry.** Point `DLOAD_CACHE_DIR` at a directory inside the workspace, as an absolute path, and let the CI cache save and restore it.

### GitHub Actions

```yaml
permissions:
  contents: read

env:
  DLOAD_CACHE_DIR: ${{ github.workspace }}/runtime/dload-cache

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      # … install PHP and Composer dependencies …

      - name: Restore the dload version registry
        uses: actions/cache@v4
        with:
          path: runtime/dload-cache
          key: dload-registry-${{ github.run_id }}
          restore-keys: dload-registry-

      - run: vendor/bin/dload get
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

- `permissions: contents: read` is all dload needs from `GITHUB_TOKEN`: reading releases and downloading their assets. Releases of public repositories are readable with any token — there the token only lifts the rate limit — while releases of the workflow's own private repository need `contents: read`. A personal access token for the same job is a fine-grained token with read-only access to public repositories and no extra permissions.
- Workflow-level `env` with `github.workspace` gives every job and step the same absolute path, whatever their working directory.
- `github.run_id` in the key makes every run save a fresh registry; `restore-keys` starts the next run from the most recent one. A cache key is immutable, so a fixed key would freeze the registry at its first save.
- Jobs of one run do not see each other's cache — `actions/cache` saves when a job ends. In a matrix the first job to finish saves the run's registry.

Real-world example: [`yiisoft/yii-runner-rapira` build workflow](https://github.com/yiisoft/yii-runner-rapira/blob/master/.github/workflows/build.yml) — dload runs inside the test suite on a Linux/Windows matrix.

### GitLab CI

GitLab caches only paths inside the project directory:

```yaml
variables:
  DLOAD_CACHE_DIR: $CI_PROJECT_DIR/runtime/dload-cache

test:
  cache:
    key: dload-registry
    paths: [runtime/dload-cache/]
  script:
    - vendor/bin/dload get
```

Store `GITHUB_TOKEN` (for GitHub-hosted tools) and `GITLAB_TOKEN` as masked CI/CD variables.

Add the cache directory to `.gitignore` in either setup.
