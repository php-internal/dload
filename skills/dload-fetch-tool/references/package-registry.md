# Registry entries shipped by a Composer package

A package declares `<software>` blocks once, and every project that requires it can `dload get` them without copying anything into its own `dload.xml`.

## Declare

In the package's `composer.json`, point to an XML file inside the package:

```json
{
    "extra": {
        "dload": {
            "registry": "resources/dload.xml"
        }
    }
}
```

The file has the `dload.xml` format; only its `<registry>` section is read, so the package's own `dload.xml` works when its registry is what consumers need. Write the blocks as in [`registry-entry.md`](registry-entry.md).

- The path stays inside the package: `..`, an absolute path or a symlink out of it is rejected. Remote files are not supported.
- The file ships in the dist: keep it out of `export-ignore` in `.gitattributes`.
- The ids must not contain `#` or `:`, nor start or end with a space.

Done when `dload software`, run in a project that requires the package (or in the package itself), lists the entry with the package name in brackets — or, when `dload.xml` or the official registry takes the id, lists `<package>#<id>` under that entry's `Shadows:`.

## Who is read

DLoad reads `composer.json` next to the loaded `dload.xml` (the working directory without one) and the installed packages from `<vendor-dir>/composer/installed.json` (Composer 2). Entries come from the root package and its direct dependencies (`require`, `require-dev`); a transitive dependency adds nothing. A broken declaration — missing file, invalid XML, an element without a required attribute such as `name`, `uri` or `binary.name`, a path in `binary.name` or `file.rename`, an invalid regex, a `version-command` other than one option such as `--version` or `version`, an id with `#`, `:` or surrounding spaces — is a warning and is skipped.

## Priority

`dload.xml` > official registry > packages. Among packages: the root package, then the order of `require`, then `require-dev`.

| Situation | What DLoad does on download |
|---|---|
| `dload.xml` declares the same id | Uses it, silently. |
| The official registry declares the same id | Uses the official one; when the repositories differ, warns and names `<package>#<id>` to get the package's one. |
| Several packages declare the id with the same repositories | Uses the first, silently. |
| Several packages declare the id with different repositories | Uses the first and lists every `<package>#<id>` to choose from. |

Warnings appear only for a tool that is actually downloaded. `<package>#<id>` (`dload get acme/framework#gh:^2.0`, `<download software="acme/framework#gh" />`) always picks that package's entry.

## Switch off

In the project's `dload.xml`: `<registry from-packages="false">` drops package entries and keeps the official registry; `<registry overwrite="true">` drops both.
