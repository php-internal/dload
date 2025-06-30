# Changelog

## 1.5.0 (2025-06-30)

## What's Changed
* Separate HTTP Client module by @roxblnfk in https://github.com/php-internal/dload/pull/63


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
