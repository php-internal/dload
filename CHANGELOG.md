# Changelog

## [1.18.1](https://github.com/php-internal/dload/compare/1.18.0...1.18.1) (2026-10-10)


### Bug Fixes

* **downloader:** name the skipped OS packages in the failure report and warn when an older release is taken instead ([2ead813](https://github.com/php-internal/dload/commit/2ead8136161084446f2fd5e1932e5f25dfbffe81))
* **downloader:** skip OS packages when a binary is expected ([#149](https://github.com/php-internal/dload/issues/149)) ([2ead813](https://github.com/php-internal/dload/commit/2ead8136161084446f2fd5e1932e5f25dfbffe81))


### Documentation

* note that OS packages are not selected for a binary and how deep the fallback goes ([2ead813](https://github.com/php-internal/dload/commit/2ead8136161084446f2fd5e1932e5f25dfbffe81))


### Code Refactoring

* **installer:** install downloads through a pipeline ([4df3181](https://github.com/php-internal/dload/commit/4df31811b103cf2a48aed199bc1a31ae52f5d077))
* **installer:** make the installation steps readonly classes ([f259dc6](https://github.com/php-internal/dload/commit/f259dc6f62f0b745e2808abe86dfca9fd1403f7d))
* **installer:** prepare the destination around the steps, not in one ([5174300](https://github.com/php-internal/dload/commit/5174300c2ad8a7001873b68b962405c987e596af))
* **software:** collect the software registry through a pipeline ([51a3d7e](https://github.com/php-internal/dload/commit/51a3d7ec67a1a12f3f3fc1d8e80e142c644faa2d))
* **velox:** build the config through the common pipeline ([6e7998e](https://github.com/php-internal/dload/commit/6e7998e8ce8919c79ce9bec1bb865a66d3102993))
* **velox:** drop the unused config pipeline metadata ([#146](https://github.com/php-internal/dload/issues/146)) ([c61c8f0](https://github.com/php-internal/dload/commit/c61c8f0a475378499556d1daefad1567bca113e4))

## [1.18.0](https://github.com/php-internal/dload/compare/1.17.0...1.18.0) (2026-10-06)


### Features

* **downloader:** fall back to x86-64 builds on ARM macOS and Windows ([b045f25](https://github.com/php-internal/dload/commit/b045f250df57d0e923834d073cbb4406c6f8ec5c))
* **downloader:** prefer the build for the host libc ([a5ea3e0](https://github.com/php-internal/dload/commit/a5ea3e00dd377aafc7f041300053d7ce78d80c1f))
* **downloader:** prefer the plain build over its variants ([0d5092a](https://github.com/php-internal/dload/commit/0d5092a99500e99d5b4482e582ed75d4fd1206b2))
* **downloader:** recognize Android builds ([9ae238f](https://github.com/php-internal/dload/commit/9ae238f8f7fd0d28e8a121355556142e83a44683))
* **downloader:** warn when an installed build needs x86-64 emulation ([e4e9459](https://github.com/php-internal/dload/commit/e4e94597316d10d5098532d3ad92070db2c439d1))
* **get:** show the libc of Linux and Android hosts ([410e738](https://github.com/php-internal/dload/commit/410e7387a3da701abaf754fef281b8ed227e5526))
* **registry:** select Bun assets with a broad pattern ([0d5092a](https://github.com/php-internal/dload/commit/0d5092a99500e99d5b4482e582ed75d4fd1206b2)), closes [#134](https://github.com/php-internal/dload/issues/134)


### Bug Fixes

* **downloader:** do not detect musl when the loader lookup fails ([92db68e](https://github.com/php-internal/dload/commit/92db68e4499d7ec8907cbd52a2604005af07fed4))
* **downloader:** never select checksums and signatures ([535540d](https://github.com/php-internal/dload/commit/535540df1c181942d2d8dd16a6f5a4b3d14f39d3))
* **downloader:** prefer musl builds on Android ([7345551](https://github.com/php-internal/dload/commit/7345551ebd2b6ad7d9c60ce9599d9431a6c88140))


### Documentation

* **skill:** describe when Android builds are dropped ([c155632](https://github.com/php-internal/dload/commit/c155632edbdf541808bf292b74b6793cdea60631))


### Code Refactoring

* **downloader:** drop a bogus preg_match flag ([c155632](https://github.com/php-internal/dload/commit/c155632edbdf541808bf292b74b6793cdea60631))
* **downloader:** name the libc of every host ([410e738](https://github.com/php-internal/dload/commit/410e7387a3da701abaf754fef281b8ed227e5526))
* **downloader:** read the host libc lazily in asset selection ([666e6cd](https://github.com/php-internal/dload/commit/666e6cdfb040a131cab135dd2a8eebc22c1e4678))
* **downloader:** select assets through a ranking pipeline ([144de75](https://github.com/php-internal/dload/commit/144de751d97c53c054b25eef2817576e3f2d366c))

## [1.17.0](https://github.com/php-internal/dload/compare/1.16.6...1.17.0) (2026-10-05)


### Features

* Add Deno to default registry ([#129](https://github.com/php-internal/dload/issues/129)) ([bdb1a1a](https://github.com/php-internal/dload/commit/bdb1a1a47b5b887283f9b0969d87641edae7b645))
* **get:** exit with status 0 under --quiet when there is nothing to download ([#132](https://github.com/php-internal/dload/issues/132)) ([c9217e3](https://github.com/php-internal/dload/commit/c9217e3e80f1a3cd62cf721633a9398237cea157))
* **registry:** add Bun to the default software registry ([7352181](https://github.com/php-internal/dload/commit/7352181202549d6675a59285005e21993df1a6e6)), closes [#130](https://github.com/php-internal/dload/issues/130)
* **registry:** select releases by a tag prefix ([298a29c](https://github.com/php-internal/dload/commit/298a29cb10dab3f8975eb9cd0104844a07c9427b))


### Bug Fixes

* **gitlab:** download release assets by the tag, not the release name ([35ee4ba](https://github.com/php-internal/dload/commit/35ee4bae2d5d6121705997bec4d0ecd6f80f649f))

## [1.16.6](https://github.com/php-internal/dload/compare/1.16.5...1.16.6) (2026-09-30)


### Documentation

* **skills:** split dload-fetch-tool into references and cover the version registry ([a408e53](https://github.com/php-internal/dload/commit/a408e539a3cfe8c30b56abddd5aed5c2a4e20d90))

## [1.16.5](https://github.com/php-internal/dload/compare/1.16.4...1.16.5) (2026-09-15)


### Bug Fixes

* **ci:** authenticate spc on the Windows build ([a30284c](https://github.com/php-internal/dload/commit/a30284c75528e9b47b3189c21d01fa45a295f714))
* **ci:** upload release binaries with the gh CLI ([fe60082](https://github.com/php-internal/dload/commit/fe6008221e541832a4a13a5ccc5cd59ba50e8dd6))

## [1.16.4](https://github.com/php-internal/dload/compare/1.16.3...1.16.4) (2026-09-15)


### Bug Fixes

* **ci:** download only the binary artifacts when publishing a release ([90bfd17](https://github.com/php-internal/dload/commit/90bfd17c6683dbea833a24c645017f5d17923caa))

## [1.16.3](https://github.com/php-internal/dload/compare/1.16.2...1.16.3) (2026-09-15)


### Bug Fixes

* **ci:** add build timeouts and make the release publish best-effort ([955cd24](https://github.com/php-internal/dload/commit/955cd24422f111b72b5c4ccac5adccc01e4d3ef2))
* **ci:** drop the macOS x64 binary build ([02d4f9b](https://github.com/php-internal/dload/commit/02d4f9be29146b4a4c52352ba94797a56a1a343d))
* **ci:** publish release binaries from one job to stop the upload race ([56685ae](https://github.com/php-internal/dload/commit/56685ae6e6fc4b804360d5e280113cf26b0de2e6))

## [1.16.2](https://github.com/php-internal/dload/compare/1.16.1...1.16.2) (2026-09-15)


### Bug Fixes

* **ci:** pin the Windows binary build to windows-2022 ([50d3f09](https://github.com/php-internal/dload/commit/50d3f09b82d31bacf846d99b5ef4b14b4acdb938))
* **ci:** run the macOS x64 binary build on a real Intel runner ([1db2385](https://github.com/php-internal/dload/commit/1db238578cb11e9d646f260dd8dda43af317e112))

## [1.16.1](https://github.com/php-internal/dload/compare/1.16.0...1.16.1) (2026-09-15)


### Bug Fixes

* **ci:** pass GITHUB_TOKEN to acceptance tests so they stop hitting the rate limit ([71c6ae1](https://github.com/php-internal/dload/commit/71c6ae14014e6c0b8c1d294f5d47fecd781dc76c))

## [1.16.0](https://github.com/php-internal/dload/compare/1.15.2...1.16.0) (2026-09-15)


### Features

* add version registry that keeps release lists between runs ([#119](https://github.com/php-internal/dload/issues/119)) ([c1d5df9](https://github.com/php-internal/dload/commit/c1d5df90be2ce0a37d346bffc3be2a631952e89a))
* **get:** add `--refresh|-r` to ignore the registry TTL once ([c1d5df9](https://github.com/php-internal/dload/commit/c1d5df90be2ce0a37d346bffc3be2a631952e89a))

## [1.15.2](https://github.com/php-internal/dload/compare/1.15.1...1.15.2) (2026-09-08)


### Code Refactoring

* replace the in-tree DI container with internal/container ([#116](https://github.com/php-internal/dload/issues/116)) ([e0e241d](https://github.com/php-internal/dload/commit/e0e241da1bd9d8d82c4207dc03e15badc49f2d74))

## [1.15.1](https://github.com/php-internal/dload/compare/1.15.0...1.15.1) (2026-09-05)


### Maintenance

* Add Windows repository for Rapira ([f09b6d9](https://github.com/php-internal/dload/commit/f09b6d931ea42695a8cfa7892803c871e8eed8ea))

## [1.15.0](https://github.com/php-internal/dload/compare/1.14.1...1.15.0) (2026-08-18)


### Features

* Add Rapira ([c553864](https://github.com/php-internal/dload/commit/c553864477e481644211874e97505719d2dd8988))
* unpack `type="archive"` preserving arch directory structure ([#114](https://github.com/php-internal/dload/issues/114)) ([453e5cd](https://github.com/php-internal/dload/commit/453e5cd9121bc87046281c01070a0dddcfe06fae))


### Bug Fixes

* **ci:** call the asset builds from the release workflow ([81d9a12](https://github.com/php-internal/dload/commit/81d9a129a656e329de74c4ecc495131fa30f61f8))
* **registry:** narrow the built-in rapira asset-pattern to php8.5 builds ([453e5cd](https://github.com/php-internal/dload/commit/453e5cd9121bc87046281c01070a0dddcfe06fae))


### Documentation

* document structure-preserving archive extraction in the README and skill ([453e5cd](https://github.com/php-internal/dload/commit/453e5cd9121bc87046281c01070a0dddcfe06fae))

## [1.14.1](https://github.com/php-internal/dload/compare/1.14.0...1.14.1) (2026-08-11)


### Bug Fixes

* **get:** return a non-zero exit code when a download fails ([#110](https://github.com/php-internal/dload/issues/110)) ([0f52a0f](https://github.com/php-internal/dload/commit/0f52a0f439058ac3e32a4dde3998727f637a4272))
* **repository:** turn API errors into actionable messages instead of an empty release list ([0f52a0f](https://github.com/php-internal/dload/commit/0f52a0f439058ac3e32a4dde3998727f637a4272))
* **schema:** remove unimplemented version-path attribute and velox download type ([330c22c](https://github.com/php-internal/dload/commit/330c22c87199ba19ddff615e670c334a5d2490e5))


### Documentation

* **skills:** drop dead version-path, velox and dload-example.xml references ([330c22c](https://github.com/php-internal/dload/commit/330c22c87199ba19ddff615e670c334a5d2490e5))

## [1.14.0](https://github.com/php-internal/dload/compare/1.13.1...1.14.0) (2026-07-03)


### Features

* **registry:** add YiiPress engine ([947d5e8](https://github.com/php-internal/dload/commit/947d5e8184cff6749d09e61a37cd30b663705ab7))


### Bug Fixes

* **ci:** push Vibe Index badge to PR branch with authenticated git ([42bf7ca](https://github.com/php-internal/dload/commit/42bf7caa7bfbf8fba7a4b05ad40830c088588c5d))
* **ci:** push Vibe Index badge to release PR via action auto-commit ([11109b2](https://github.com/php-internal/dload/commit/11109b2e9e4e9a9b792620f81176aa9e3da6ec41))

## [1.13.1](https://github.com/php-internal/dload/compare/1.13.0...1.13.1) (2026-05-15)


### Bug Fixes

* **show:** respect destination and extract-path when locating binaries ([5f0c445](https://github.com/php-internal/dload/commit/5f0c445d52afb30eadd1f6b6f708b76826b1ab56))


### Documentation

* Add AI skills ([56c191c](https://github.com/php-internal/dload/commit/56c191cdaaea5c07ad23b1c49afba33f6ca09a81))

## [1.13.0](https://github.com/php-internal/dload/compare/1.12.0...1.13.0) (2026-05-15)


### Features

* Add Buf to software registry ([35fb4f3](https://github.com/php-internal/dload/commit/35fb4f306a21c0d10871d818a1a2d1a109efdcdd))

## [1.12.0](https://github.com/php-internal/dload/compare/1.11.0...1.12.0) (2026-04-28)


### Features

* Add Buggregator server into the registry ([#105](https://github.com/php-internal/dload/issues/105)) ([7035ef0](https://github.com/php-internal/dload/commit/7035ef0c2a7992ad0a514ca0a6daf7f69f9946a2))


### Code Refactoring

* Add more debug info into GitHub API implementation ([17bb1fa](https://github.com/php-internal/dload/commit/17bb1fa3b7903e6260927a12204d17dc47b549bf))
* Remove symfony/http-client 4 and 5 from composer.json ([7035ef0](https://github.com/php-internal/dload/commit/7035ef0c2a7992ad0a514ca0a6daf7f69f9946a2))

## [1.11.0](https://github.com/php-internal/dload/compare/1.10.0...1.11.0) (2026-03-30)


### Features

* Support `.gz` archives ([fcdc71d](https://github.com/php-internal/dload/commit/fcdc71d4b4debb6c7b82fe036fa9a6eb8d827f49))
* Validate file extensions in renaming logic ([7bf11e1](https://github.com/php-internal/dload/commit/7bf11e189d600fc650f57b251a9a05e06934e3e5))

## [1.10.0](https://github.com/php-internal/dload/compare/1.9.0...1.10.0) (2026-03-21)


### Features

* support macos operation system ([#98](https://github.com/php-internal/dload/issues/98)) ([5d60329](https://github.com/php-internal/dload/commit/5d603293b661f22a8adf0ccd378ccc023d593af6))

## [1.9.0](https://github.com/php-internal/dload/compare/1.8.0...1.9.0) (2026-02-13)


### Features

* add DoltgreSQL entry ([#95](https://github.com/php-internal/dload/issues/95)) ([91e8181](https://github.com/php-internal/dload/commit/91e8181a9e77ae2b8ffcfd5628c72581b067fc77))


### Documentation

* **Readme:** Sync translations with GitLab support changes ([6514fda](https://github.com/php-internal/dload/commit/6514fdafbb0ddce0361e6734d47516ce4f83ed97))

## [1.8.0](https://github.com/php-internal/dload/compare/1.7.3...1.8.0) (2025-12-17)


### Features

* Add support for Gitlab ([#92](https://github.com/php-internal/dload/issues/92)) ([d68a2a1](https://github.com/php-internal/dload/commit/d68a2a1cf17ec122f7f55f2d68d8956fd4cefebe))

## [1.7.3](https://github.com/php-internal/dload/compare/1.7.2...1.7.3) (2025-12-14)


### Bug Fixes

* Stop using deprecated `Command::getDefaultName()` ([#90](https://github.com/php-internal/dload/issues/90)) ([8b22008](https://github.com/php-internal/dload/commit/8b22008edb4e0948c60062abbbc929fbcd4ca87d))

## [1.7.2](https://github.com/php-internal/dload/compare/1.7.1...1.7.2) (2025-12-03)


### Code Refactoring

* Use `internal/path` instead of local implementation ([2aba0bd](https://github.com/php-internal/dload/commit/2aba0bd25d232b1f2da0d285769477d47d9b7a7c))

## 1.7.1 (2025-11-30)

**Full Changelog**: https://github.com/php-internal/dload/compare/1.7.0...1.7.1

## 1.7.0 (2025-11-11)

## What's Changed
* Support `velox.plugin.replace` option by @roxblnfk in https://github.com/php-internal/dload/pull/84


**Full Changelog**: https://github.com/php-internal/dload/compare/1.6.5...1.7.0

## 1.6.5 (2025-11-07)

**Full Changelog**: https://github.com/php-internal/dload/compare/1.6.4...1.6.5

## 1.6.4 (2025-11-06)

## What's Changed
* Use TOML library to work with Velox config by @roxblnfk in https://github.com/php-internal/dload/pull/81


**Full Changelog**: https://github.com/php-internal/dload/compare/1.6.3...1.6.4

## 1.6.3 (2025-10-23)

## What's Changed
* Process GitHub message with assoc content by @roxblnfk in https://github.com/php-internal/dload/pull/78


**Full Changelog**: https://github.com/php-internal/dload/compare/1.6.2...1.6.3

## 1.6.2 (2025-09-23)

## What's Changed
* Fix `--force` option by @ERuban in https://github.com/php-internal/dload/pull/76

## New Contributors
* @ERuban made their first contribution in https://github.com/php-internal/dload/pull/76

**Full Changelog**: https://github.com/php-internal/dload/compare/1.6.1...1.6.2

## 1.6.1 (2025-08-29)

## What's Changed
* Fix processing of `--path` flag in the `get` command by @roxblnfk in https://github.com/php-internal/dload/pull/70


**Full Changelog**: https://github.com/php-internal/dload/compare/1.6.0...1.6.1

## 1.6.0 (2025-08-29)

## What's Changed
* Enhance version syntax for get command by @roxblnfk in https://github.com/php-internal/dload/pull/68


**Full Changelog**: https://github.com/php-internal/dload/compare/1.5.0...1.6.0

## 1.5.0 (2025-07-28)

## What's Changed
* Separate HTTP Client module by @roxblnfk in https://github.com/php-internal/dload/pull/63
* Add RoadRunner building by @roxblnfk in https://github.com/php-internal/dload/pull/65


**Full Changelog**: https://github.com/php-internal/dload/compare/1.4.1...1.5.0

## 1.4.1 (2025-06-27)

## What's Changed
* fix config injection path in Container by @roxblnfk in https://github.com/php-internal/dload/pull/61


**Full Changelog**: https://github.com/php-internal/dload/compare/1.4.0...1.4.1

## 1.4.0 (2025-06-23)

## What's Changed
* Add readme translations by @DimaTiunov in https://github.com/php-internal/dload/pull/55
* Clean up temporary downloaded files after extraction by @roxblnfk in https://github.com/php-internal/dload/pull/58
* Add `init` console command by @roxblnfk in https://github.com/php-internal/dload/pull/59

## New Contributors
* @DimaTiunov made their first contribution in https://github.com/php-internal/dload/pull/55

**Full Changelog**: https://github.com/php-internal/dload/compare/1.3.0...1.4.0

## 1.3.0 (2025-06-15)

## What's Changed
* Add ability to load `phar` archives by @roxblnfk in https://github.com/php-internal/dload/pull/52


**Full Changelog**: https://github.com/php-internal/dload/compare/1.2.3...1.3.0

## 1.2.3 (2025-06-14)

## What's Changed
* Downloader hotfixes by @roxblnfk in https://github.com/php-internal/dload/pull/48
* Downloader refactoring by @roxblnfk in https://github.com/php-internal/dload/pull/50


**Full Changelog**: https://github.com/php-internal/dload/compare/1.2.2...1.2.3

## 1.2.2 (2025-06-12)

## What's Changed
* Remove deprecated E_STRICT constant by @roxblnfk in https://github.com/php-internal/dload/pull/45


**Full Changelog**: https://github.com/php-internal/dload/compare/1.2.1...1.2.2

## 1.2.1 (2025-06-12)

## What's Changed
* Increase memory limit and adjust error reporting settings by @roxblnfk in https://github.com/php-internal/dload/pull/43


**Full Changelog**: https://github.com/php-internal/dload/compare/1.2.0...1.2.1

## 1.2.0 (2025-06-03)

## What's Changed
* Enhanced Version Constraints by @roxblnfk in https://github.com/php-internal/dload/pull/41


**Full Changelog**: https://github.com/php-internal/dload/compare/1.1.0...1.2.0

## 1.1.0 (2025-05-04)

## What's Changed
* Add more stability markers by @roxblnfk in https://github.com/php-internal/dload/pull/37


**Full Changelog**: https://github.com/php-internal/dload/compare/1.0.2...1.1.0

## 1.0.2 (2025-04-14)

## What's Changed
* Remove script time limit by @roxblnfk in https://github.com/php-internal/dload/pull/32


**Full Changelog**: https://github.com/php-internal/dload/compare/1.0.1...1.0.2

## 1.0.1 (2025-04-13)

## What's Changed
* Hotfixes by @roxblnfk in https://github.com/php-internal/dload/pull/28
    - skip binary download if file exists and no version detected
    - use Composer's version comparator in `Binary::satisfies()`
    - fix version checker for `dolt `and `protoc`

**Full Changelog**: https://github.com/php-internal/dload/compare/1.0.0...1.0.1

## 1.0.0-RC3 (2025-04-13)

## What's Changed
* Added software versions checker by @roxblnfk in https://github.com/php-internal/dload/pull/23
  Added `dload show` command
  Added a new software `trap`

## 1.0.0-RC2 (2025-04-12)

## What's Changed
- Support loading not archived binaries
- Binary config separated into embedded entity
- Mon-binary files can be loaded without arch/os checks
- Added param `extract-path` to download action

## 1.0.0-RC1 (2025-04-07)

## What's Changed
* Fix PHAR building by @roxblnfk in https://github.com/php-internal/dload/pull/16
* Make lazy loading of releases pages; maintenance by @roxblnfk in https://github.com/php-internal/dload/pull/18
* Check binaries before downloading by @roxblnfk in https://github.com/php-internal/dload/pull/19

## 1.0.0-alpha (2024-07-20)

## What's Changed
* Fix compatibility with synfony console 4-5 by @roxblnfk in https://github.com/php-internal/dload/pull/9
* Add `protoc`, `protoc-gen-php-grpc` and `tigerbeetle` software  by @roxblnfk in https://github.com/php-internal/dload/pull/13

**Full Changelog**: https://github.com/php-internal/dload/compare/0.2.1...0.2.2

## 0.2.1 (2024-07-20)

## What's Changed
* Fixed asset file extension detection by @roxblnfk
* Updated min version of `yiisoft/injector` by @roxblnfk

**Full Changelog**: https://github.com/php-internal/dload/compare/0.2.0...0.2.1

## 0.2.0 (2024-07-19)

## What's Changed
* Add `software` command by @roxblnfk in https://github.com/php-internal/dload/pull/5
* Support custom XML config by @roxblnfk in https://github.com/php-internal/dload/pull/6

## New Contributors
* @roxblnfk made their first contribution in https://github.com/php-internal/dload/pull/5

**Full Changelog**: https://github.com/php-internal/dload/compare/0.1.0...0.2.0
