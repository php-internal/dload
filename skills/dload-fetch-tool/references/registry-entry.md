# Inline registry entry

How to describe a tool dload does not ship, as a `<software>` block inside `<registry overwrite="false">` in the project's `dload.xml`. `overwrite="false"` (the default) merges the entry with the built-in registry; `overwrite="true"` replaces the built-in registry entirely.

## Facts to collect

1. **GitHub repo** as `owner/repo`.
2. **A real release tag** to inspect — any recent one. Note any text before the version (`bun-v1.4.2`, `cli-v2.0.0`): it needs a [`tag-prefix`](#tag-prefix).
3. **The asset filename matrix**:
   ```bash
   curl -s https://api.github.com/repos/<owner>/<repo>/releases/tags/<tag> \
     | grep -oE '"name": "[^"]+"'
   ```
4. **Sibling tools in the same release to exclude** — e.g. `bufbuild/buf` ships `buf-*` alongside `protoc-gen-buf-breaking-*` / `protoc-gen-buf-lint-*`; only `buf-*` is wanted.
5. **The version command** — usually `--version`; some tools use `version` or have none. dload reads the first `X.Y.Z` in its output with every further dotted part (`1.2.3.4`) and a pre-release or build suffix, and compares it with `version` to decide whether the installed binary is up to date; trailing zero parts don't count, so `1.2.3.0` satisfies `1.2.3`.
6. **Artefact type** — native binary (per OS/arch) or PHAR (one platform-independent `.phar`).

## `asset-pattern`

Filters the release's asset list. dload then runs OS/arch detection on every matching filename to pick the host's variant, so the regex leaves the platform out. It only has to:

- match all OS/arch variants of the tool;
- leave out sibling tools, checksums, signatures, source archives.

Build variants stay in the pattern too. Among the assets for the host, dload prefers the host libc (`musl` builds on Alpine and Android, the others elsewhere), then the name with the fewest extra tokens — so `bun-linux-x64.zip` wins over `-baseline`, `-profile` and `-debug` twins — then archives. Android builds are dropped on Linux whenever the entry has a `binary` (without one, every other platform is only ranked lower). On ARM macOS and Windows, x86-64 builds are a fallback: Rosetta 2 and the Windows emulation run them.

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
