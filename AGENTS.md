# DLoad

A PHP CLI (`vendor/bin/dload`, also shipped as a PHAR) that downloads binaries and PHARs from GitHub and GitLab releases into a project, driven by the project's `dload.xml`; it also builds custom RoadRunner binaries through Velox.

- `src/Module/*` holds the modules (Repository, Downloader, Registry, Installer, Velox…); `src/Bootstrap.php` wires the container.
- `resources/software.json` is the built-in software registry; `dload.xsd` is the schema of `dload.xml`.
- `1.x` is the development and release branch: branch from it and target pull requests at it.
- Tests run on Testo: `vendor/bin/testo --json`. Static analysis: `composer psalm`. Code style: `composer cs:fix`.

## Docs and skills follow behaviour

A change a user can observe — a new feature, attribute, option, environment variable, or a different default — lands together with its documentation in the same pull request:

- `README.md`, then the same change in every translation (`README-ru.md`, `README-es.md`, `README-zh.md`), keeping each file's anchor style.
- `dload.xsd` for anything new in `dload.xml`; `resources/software.schema.json` for anything new in `software.json`.
- `skills/dload-fetch-tool` — the skill shipped to projects that use DLoad; its references describe how entries, tokens and the version registry behave, so they go stale with the code.

`skills/` is the source of truth for skills. `.agents/skills` and `.claude/skills` are copies installed from `skills.json`; edit `skills/` only.

## Releases

release-please cuts releases from `1.x`: it writes `CHANGELOG.md` and `resources/version.json` from the commit history, so both files are generated — leave them to it.

Commit subjects follow conventional commits, and the type picks the changelog section (`.github/.release-please-config.json`): `feat`, `fix`, `perf`, `docs`, `refactor` are listed; `test`, `chore`, `ci`, `build`, `style` are hidden. A commit carrying several changes lists each as its own conventional line at the top of the body, so every change reaches its section.
