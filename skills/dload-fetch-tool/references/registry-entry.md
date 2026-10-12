# Inline registry entry

How to describe a tool dload does not ship, as a `<software>` block inside `<registry overwrite="false">` in the project's `dload.xml`. `overwrite="false"` (the default) merges the entry with the built-in registry; `overwrite="true"` replaces the built-in registry entirely, along with the entries of [Composer packages](package-registry.md).

## Facts to collect

1. **GitHub repo** as `owner/repo` — or, on GitHub Enterprise Server or a self-hosted GitLab, the repository URL: see [self-hosted `uri`](#self-hosted-uri).
2. **A real release tag** to inspect — any recent one. Note any text before the version (`bun-v1.4.2`, `cli-v2.0.0`): it needs a [`tag-prefix`](#tag-prefix).
3. **The asset filename matrix**:
   ```bash
   curl -s https://api.github.com/repos/<owner>/<repo>/releases/tags/<tag> \
     | grep -oE '"name": "[^"]+"'
   ```
4. **Sibling tools in the same release to exclude** — e.g. `bufbuild/buf` ships `buf-*` alongside `protoc-gen-buf-breaking-*` / `protoc-gen-buf-lint-*`; only `buf-*` is wanted.
5. **The version command** — usually `--version`; some tools use `version` or have none. It is split on spaces and passed to the binary without a shell, so pipes and quotes do not work. dload reads the first `X.Y.Z` in its output with every further dotted part (`1.2.3.4`) and a pre-release or build suffix, and compares it with `version` to decide whether the installed binary is up to date; trailing zero parts don't count, so `1.2.3.0` satisfies `1.2.3`.
6. **Artefact type** — native binary (per OS/arch) or PHAR (one platform-independent `.phar`).

## `asset-pattern`

Filters the release's asset list. dload then runs OS/arch detection on every matching filename to pick the host's variant, so the regex leaves the platform out. It only has to:

- match all OS/arch variants of the tool;
- leave out sibling tools, checksums, signatures, source archives.

Build variants stay in the pattern too. Among the assets for the host, dload prefers the host libc (`musl` builds on Alpine and Android, the others elsewhere), then the name with the fewest extra tokens — so `bun-linux-x64.zip` wins over `-baseline`, `-profile` and `-debug` twins — then archives. Android builds are dropped on Linux whenever the entry has a `binary` (without one, every other platform is only ranked lower), and so are OS packages (`.deb`, `.rpm`, `.apk`, `.snap`, `.flatpak`, `.msi`, `.msix`, `.msixbundle`, `.appx`, `.appxbundle`, `.nupkg`, `.dmg`, `.pkg`) and archives dload has no extractor for (known archive formats other than `.zip`, `.tar.gz`, `.tgz`, `.tar`, `.gz`, `.phar` — such as `.tar.xz`, `.tar.bz2`, `.zst`, `.pkg.tar.zst`, `.7z`, `.rar`) everywhere: dload cannot unpack them, so a release that has only such assets for the host gives way to an older one among the 10 newest releases that satisfy `version` (pin `version` to go further back). On ARM macOS and Windows, x86-64 builds are a fallback: Rosetta 2 and the Windows emulation run them.

Tokens the OS/arch matchers recognise (case-insensitive, bounded by `_` or a word boundary):

| Kind | Tokens |
|---|---|
| OS   | `windows`, `linux`, `darwin`, `macos`, `alpine`, `bsd`, `freebsd`, `win32`, `win64`, `android` (wins over `linux`) |
| Arch | `amd64`, `arm64`, `aarch64`, `x86_64`, `x64`, `win64` |
| Libc | `musl`, `musleabi*`, `alpine`; `gnu`, `gnueabi*`, `glibc`; none means glibc |

| Situation | Pattern |
|---|---|
| Assets share a prefix | `/^<name>-.*/` (e.g. `/^buf-.*/`, `/^roadrunner-.*/`) |
| No stable prefix | Anchor on something stable, e.g. `/^artifacts.*/` for `buggregator/frontend` |
| Several tools in one release | Anchor with `-` after the name to cut siblings |
| PHAR | `/^<name>\.phar$/` or `/^.*\.phar$/` |

Patterns are slash-delimited, and in XML attributes backslashes are literal — write `\.exe`.

## `tag-prefix`

A literal string the release tags start with, cut off before the version is parsed. dload reads the version from the tag, so a tag like `bun-v1.4.2` is skipped as unparsable until `tag-prefix="bun-"` is set.

It also selects one component of a monorepo with per-component tags (release-please: `cli-v2.0.0`, `sdk-v1.4.0`): releases whose tag does not start with the prefix are ignored. Include the separator in the prefix (`cli-`, `cli/`), leave the `v` out — it is part of the version.

## Self-hosted `uri`

A repository on an instance other than `github.com` / `gitlab.com` is given by its URL in `uri`, scheme required: `uri="https://ghe.example.com/owner/repo"`, `uri="https://gitlab.example.com:8443/group/sub/project"`. Scheme, host and port are the server; the path is `owner/repo` for GitHub, the full project path for GitLab. A trailing `/` and `.git` are ignored (a clone URL works); a page URL (`.../releases`, `.../-/tags`) is rejected — trim it to the repository. Without a scheme the value is a path on the public host: `ghe.example.com/owner/repo` is an error for GitHub, never a server. `https://github.com/owner/repo` and `github.com/owner/repo` mean the public host. A GitLab under a relative URL root (`https://host/gitlab/...`) is not supported.

dload calls the API at `{server}/api/v3` for GitHub Enterprise Server and `{server}/api/v4` for GitLab, so inspect releases there: `curl -s https://<server>/api/v3/repos/<owner>/<repo>/releases/tags/<tag>`.

The token comes from `DLOAD_TOKEN_<HOST>` — the host upper-cased, `.` as `_`, `-` as `__`, a non-default port after `___`, an IPv6 address as `_` plus all eight groups: `DLOAD_TOKEN_GHE_EXAMPLE_COM`, `DLOAD_TOKEN_GIT__LAB_EXAMPLE_COM`, `DLOAD_TOKEN_GITLAB_EXAMPLE_COM___8443`, `DLOAD_TOKEN__0_0_0_0_0_0_0_1___8080` for `[::1]:8080`. The scheme is not part of the name. The token goes only to that exact scheme, host and port and is dropped on a redirect elsewhere. `GITHUB_TOKEN` / `GITLAB_TOKEN` stay with the public hosts. `http://` works (e.g. a local fake API in tests) and carries a token only to `localhost`, `127.0.0.0/8` and `[::1]`.

## `binary.pattern`

Matches the **executable on disk after the asset is downloaded and extracted**:

| Asset type | What ends up on disk | Pattern |
|---|---|---|
| Raw binary (no archive) | The asset filename itself | `/^<name>(-.*)?(\.exe)?$/` |
| Archive with `bin/<name>` inside | `<name>` or `<name>.exe` | `/^<name>(\.exe)?$/` |
| Both published | Either form | `/^<name>(-.*)?(\.exe)?$/` |

The broad form `/^<name>(-.*)?(\.exe)?$/` is the safe default. The attribute is optional: without it dload matches `binary.name` exactly, which is enough when the archive holds exactly one file of that name.

When the CLI name differs from the filename (RoadRunner ships as `roadrunner`, invoked as `rr`), set `binary.name` to the short alias and let the pattern match both.

## Native binary

```xml
<registry overwrite="false">
    <software name="MyTool" alias="mytool" description="…"
              homepage="https://example.com/">
        <repository type="github" uri="owner/repo" asset-pattern="/^mytool-.*/" />
        <binary name="mytool" pattern="/^mytool(-.*)?(\.exe)?$/" version-command="--version" />
    </software>
</registry>
```

## PHAR

No extraction, no OS/arch matching — the same shape, simpler:

```xml
<software name="Trap" alias="trap" description="…" homepage="https://buggregator.dev/">
    <repository type="github" uri="buggregator/trap" asset-pattern="/^trap\.phar$/" />
    <binary name="trap" pattern="/^.*\.phar$/" version-command="--version" />
</software>
```

Pair it with `type="phar"` on the `<download>` action.
