<div align="center">

![DLoad](./resources/logo.svg)

</div>

<p align="center">Download artifacts easily</p>

<div align="center">

[![Support](https://img.shields.io/static/v1?style=flat-square&label=Support&message=%E2%9D%A4&logo=GitHub&color=%23fe0086)](https://patreon.com/roxblnfk)

</div>

<br />

DLoad simplifies downloading and managing binary artifacts for your projects. Perfect for development environments that require specific tools like RoadRunner, Temporal, or custom binaries.

## Why DLoad?

DLoad solves a common problem in PHP projects: how to distribute and install necessary binary tools and assets alongside your PHP code.
With DLoad, you can:

- Automatically download required tools during project initialization
- Ensure all team members use the same versions of tools
- Simplify onboarding by automating environment setup
- Manage cross-platform compatibility without manual configuration
- Keep binaries and assets separate from your version control

## Installation

```bash
composer require internal/dload -W
```

[![PHP](https://img.shields.io/packagist/php-v/internal/dload.svg?style=flat-square&logo=php)](https://packagist.org/packages/internal/dload)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/internal/dload.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/internal/dload)
[![License](https://img.shields.io/packagist/l/internal/dload.svg?style=flat-square)](LICENSE.md)
[![Total DLoads](https://img.shields.io/packagist/dt/internal/dload.svg?style=flat-square)](https://packagist.org/packages/internal/dload/stats)

## Quick Start

1. **Initialize your project configuration**:

```xml
<?xml version="1.0"?>
<dload xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="vendor/internal/dload/dload.xsd"
>
   <actions>
       <download software="rr" version="^2025.1.0"/>
       <download software="temporal" version="^1.3"/>
   </actions>
</dload>
```

2. **Download configured software**:

```bash
./vendor/bin/dload get
```

3. **Integrate with Composer** (optional):

   ```json
   {
       "scripts": {
           "post-update-cmd": "dload get --no-interaction -v || echo can't dload binaries"
       }
   }
   ```

## Command Line Usage

### Download Software

```bash
# Download from configuration file
./vendor/bin/dload get

# Download specific packages
./vendor/bin/dload get rr temporal

# Download with options
./vendor/bin/dload get rr --stability=beta --force
```

#### Download Options

| Option | Description | Default |
|--------|-------------|---------|
| `--path` | Directory to store binaries | Current directory |
| `--arch` | Target architecture (amd64, arm64) | System architecture |
| `--os` | Target OS (linux, darwin, windows) | Current OS |
| `--stability` | Release stability (stable, beta) | stable |
| `--config` | Path to configuration file | ./dload.xml |
| `--force`, `-f` | Force download even if binary exists | false |

### View Software

```bash
# List available software packages
./vendor/bin/dload software

# Show downloaded software
./vendor/bin/dload show

# Show specific software details
./vendor/bin/dload show rr

# Show all software (downloaded and available)
./vendor/bin/dload show --all
```

## Configuration Guide

### Basic Configuration

Create `dload.xml` in your project root:

```xml
<?xml version="1.0"?>
<dload xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/php-internal/dload/refs/heads/1.x/dload.xsd"
       temp-dir="./runtime">
    <actions>
        <download software="rr" version="^2025.1" />
        <download software="temporal" />
        <download software="frontend" extract-path="frontend" />
    </actions>
</dload>
```

### Download Types

DLoad supports three download types that determine how assets are processed:

#### Type Attribute

```xml
<!-- Explicit type specification -->
<download software="psalm" type="phar" />        <!-- Download .phar without unpacking -->
<download software="frontend" type="archive" />  <!-- Force archive extraction -->
<download software="rr" type="binary" />         <!-- Binary-specific processing -->

<!-- Automatic type handling (recommended) -->
<download software="rr" />           <!-- Uses all available handlers -->
<download software="frontend" />     <!-- Smart processing based on software config -->
```

#### Default Behavior (No Type Specified)

When `type` is not specified, DLoad automatically uses all available handlers:

- **Binary processing**: If software has `<binary>` section, performs binary presence and version checking
- **Files processing**: If software has `<file>` section and asset is downloaded, processes files during unpacking
- **Simple download**: If no sections exist, downloads asset without unpacking

```xml
<!-- registry list -->
<software name="complex-tool">
    <binary name="tool" pattern="/^tool-.*/" />
    <file pattern="/^config\..*/" extract-path="config" />
</software>

<!-- actions list -->
<!-- Uses both binary and files processing -->
<download software="complex-tool" />
```

#### Explicit Type Behaviors

| Type      | Behavior                                                     | Use Case                       |
|-----------|--------------------------------------------------------------|--------------------------------|
| `binary`  | Binary checking, version validation, executable permissions  | CLI tools, executables         |
| `phar`    | Downloads `.phar` files as executables **without unpacking** | PHP tools like Psalm, PHPStan  |
| `archive` | **Forces unpacking even for .phar files**                    | When you need archive contents |

> [!NOTE]
> Use `type="phar"` for PHP tools that should remain as `.phar` files.
> Using `type="archive"` will unpack even `.phar` archives.

### Version Constraints

Use Composer-style version constraints:

```xml
<actions>
    <!-- Exact version -->
    <download software="rr" version="2.12.3" />
    
    <!-- Range constraints -->
    <download software="temporal" version="^1.20.0" />
    <download software="dolt" version="~0.50.0" />
    
    <!-- Stability constraints -->
    <download software="tool" version="^1.0.0@beta" />
    
    <!-- Feature releases (automatically sets preview stability) -->
    <download software="experimental" version="^1.0.0-experimental" />
</actions>
```

### Advanced Configuration Options

```xml
<dload temp-dir="./runtime">
    <actions>
        <!-- Different extraction paths -->
        <download software="frontend" extract-path="public/assets" />
        <download software="config" extract-path="config" />
        
        <!-- Target different environments -->
        <download software="prod-tool" version="^2.0.0@stable" />
        <download software="dev-tool" version="^2.0.0@beta" />
    </actions>
</dload>
```

## Custom Software Registry

### Defining Software

```xml
<dload>
    <registry overwrite="false">
        <!-- Binary executable -->
        <software name="RoadRunner" alias="rr" 
                  homepage="https://roadrunner.dev"
                  description="High performance Application server">
            <repository type="github" uri="roadrunner-server/roadrunner" asset-pattern="/^roadrunner-.*/" />
            <binary name="rr" pattern="/^roadrunner-.*/" />
        </software>

        <!-- Archive with files -->
        <software name="frontend" description="Frontend assets">
            <repository type="github" uri="my-org/frontend" asset-pattern="/^artifacts.*/" />
            <file pattern="/^.*\.js$/" />
            <file pattern="/^.*\.css$/" />
        </software>

        <!-- Mixed: binaries + files -->
        <software name="development-suite" description="Complete development tools">
            <repository type="github" uri="my-org/dev-tools" />
            <binary name="cli-tool" pattern="/^cli-tool.*/" />
            <file pattern="/^config\.yml$/" extract-path="config" />
            <file pattern="/^templates\/.*/" extract-path="templates" />
        </software>

        <!-- PHAR tools -->
        <software name="psalm" description="Static analysis tool">
            <repository type="github" uri="vimeo/psalm" />
            <binary name="psalm.phar" pattern="/^psalm\.phar$/" />
        </software>
    </registry>
</dload>
```

### Software Elements

#### Repository Configuration

- **type**: Currently supports "github"
- **uri**: Repository path (e.g., "username/repo")
- **asset-pattern**: Regex pattern to match release assets

#### Binary Elements

- **name**: Binary name for reference
- **pattern**: Regex pattern to match binary in assets
- Automatically handles OS/architecture filtering

#### File Elements

- **pattern**: Regex pattern to match files
- **extract-path**: Optional extraction directory
- Works on any system (no OS/architecture filtering)

## Use Cases

### Development Environment Setup

```bash
# One-time setup for new developers
composer install
./vendor/bin/dload get
```

### CI/CD Integration

```yaml
# GitHub Actions
- name: Download tools
  run: GITHUB_TOKEN=${{ secrets.GITHUB_TOKEN }} ./vendor/bin/dload get
```

### Cross-Platform Teams

Each developer gets the correct binaries for their system:

```xml
<actions>
    <download software="rr" />        <!-- Linux binary for Linux, Windows .exe for Windows -->
    <download software="temporal" />   <!-- macOS binary for macOS, etc. -->
</actions>
```

### PHAR Tools Management

```xml
<actions>
    <!-- Download as executable .phar files -->
    <download software="psalm" type="phar" />
    <download software="phpstan" type="phar" />
    
    <!-- Extract contents instead -->
    <download software="psalm" type="archive" />  <!-- Unpacks psalm.phar -->
</actions>
```

### Frontend Asset Distribution

```xml
<software name="ui-kit">
    <repository type="github" uri="company/ui-components" />
    <file pattern="/^dist\/.*/" extract-path="public/components" />
</software>

<actions>
    <download software="ui-kit" type="archive" />
</actions>
```

## GitHub API Rate Limits

Use a personal access token to avoid rate limits:

```bash
GITHUB_TOKEN=your_token_here ./vendor/bin/dload get
```

Add to CI/CD environment variables for automated downloads.

## Contributing

Contributions welcome! Submit Pull Requests to:

- Add new software to the predefined registry
- Improve DLoad functionality  
- Enhance documentation
