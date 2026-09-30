# Archive mode

`<download type="archive">` unpacks the **whole** asset into `extract-path`, preserving the archive's directory structure. The default type instead pulls only the files matched by `<binary>`/`<file>` rules, by file name, and places them side by side in `extract-path` — the right choice when a couple of flat files is all the tool needs.

Rules of archive mode:

- A single top-level directory wrapping the whole archive is stripped, like `tar --strip-components=1` (`pkg-1.2.3/bin/app` lands as `bin/app`).
- `<file>` rules, when present, act as an **include filter** matched by file name; without them everything is extracted.
- A `<binary>`, if given, only **locates** the executable inside the extracted tree (for the version check) and marks it executable; it stays in its subdirectory.

```xml
<!-- registry: a binary plus its shared library -->
<software name="Rapira" alias="rapira" description="…" homepage="https://rapira.rs/">
    <repository type="github" uri="rapira-rs/rapira" asset-pattern="/^rapira-v.*-linux-.*/" />
    <binary name="rapira" />
</software>

<!-- action: keep bin/ and lib/ nested under ./runtime -->
<download software="rapira" type="archive" extract-path="./runtime" />
```

Preserved layout is what keeps relative references intact — e.g. a binary resolving its shared library through an `$ORIGIN/../lib` rpath needs `bin/` and `lib/` side by side.
