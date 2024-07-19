<div align="center">

![DLoad](./resources/logo.svg)

</div>

<p align="center">Download artifacts easily</p>

<div align="center">

[![Support](https://img.shields.io/static/v1?style=flat-square&label=Support&message=%E2%9D%A4&logo=GitHub&color=%23fe0086)](https://patreon.com/roxblnfk)

</div>

<br />

## Installation

```bash
composer require internal/dload -W
```

[![PHP](https://img.shields.io/packagist/php-v/internal/dload.svg?style=flat-square&logo=php)](https://packagist.org/packages/internal/dload)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/internal/dload.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/internal/dload)
[![License](https://img.shields.io/packagist/l/internal/dload.svg?style=flat-square)](LICENSE.md)
[![Total DLoads](https://img.shields.io/packagist/dt/internal/dload.svg?style=flat-square)](https://packagist.org/packages/internal/dload/stats)

## Usage

### Get predefined software list

```bash
./vendor/bin/dload list
```

### Download single software

```bash
./vendor/bin/dload get dolt
```

### Configure preset for the project

Create `dload.xml` file in the root of the project with the following content:

```xml
<?xml version="1.0"?>
<dload>
    <actions>
        <download software="rr"/>
        <download software="temporal"/>
    </actions>
</dload>
```

There are two software packages to download: `temporal` and `rr`.
To download all the software, run `dload get` without arguments:

```bash
./vendor/bin/dload get
```

### Custom software registry

```xml
<?xml version="1.0"?>
<dload>
    <registry>
        <software name="RoadRunner"
                  alias="rr"
                  description="High performant Application server"
        >
            <repository type="github"
                        uri="roadrunner-server/roadrunner"
                        asset-pattern="/^roadrunner-.*/"
            />
            <file pattern="/^(roadrunner|rr)(?:\.exe)?$/"
                  rename="rr"
            />
        </software>
    </registry>
</dload>
```
