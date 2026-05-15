# Changelog

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
