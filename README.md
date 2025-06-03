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

## Command Line Usage

DLoad offers three main commands:

### List Available Software

```bash
# View all available software packages
./vendor/bin/dload software
```

This displays a list of all registered software packages with their IDs, names, repository information, and descriptions.

DLoad comes with a pre-configured list of popular tools and software packages ready for download.
You can contribute to this list by submitting issues or pull requests to the DLoad repository.

### Show Downloaded Software

```bash
# View all downloaded software
./vendor/bin/dload show

# Show detailed information about specific software
./vendor/bin/dload show rr

# Show all available software, including those not downloaded
./vendor/bin/dload show --all
```

This command displays information about downloaded software.

### Download Software

```bash
# Basic usage
./vendor/bin/dload get rr

# Download multiple packages
./vendor/bin/dload get rr dolt temporal

# Download with specific stability
./vendor/bin/dload get rr --stability=beta

# Use configuration from file (without specifying software)
./vendor/bin/dload get

# Force download even if binary exists
./vendor/bin/dload get rr --force
```

#### Download Command Options

| Option | Description | Default |
|--------|-------------|---------|
| `--path` | Directory to store binaries | Current directory |
| `--arch` | Target architecture (amd64, arm64) | System architecture |
| `--os` | Target OS (linux, darwin, windows) | Current OS |
| `--stability` | Release stability (stable, beta) | stable |
| `--config` | Path to configuration file | ./dload.xml |
| `--force`, `-f` | Force download even if binary exists | false |

## Project Configuration

### Setting Up Your Project

The `dload.xml` file in your project root is essential for automation. It defines the tools and assets required by your project, allowing for automatic initialization of development environments.

When a new developer joins your project, they can simply run `dload get` to download all necessary binaries and assets without manual configuration.

Create `dload.xml` in your project root:

```xml
<?xml version="1.0"?>
<dload xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/php-internal/dload/refs/heads/1.x/dload.xsd"
       temp-dir="./runtime"
>
    <actions>
        <download software="rr" version="^2.12.0" />
        <download software="dolt" />
        <download software="temporal" />
        <download software="frontend" extract-path="frontend"/>
    </actions>
</dload>
```

Then run:

```bash
./vendor/bin/dload get
```

### Configuration Options

The `dload.xml` file supports several options:

- **temp-dir**: Directory for temporary files during download (default: system temp dir)
- **actions**: List of download actions to perform

#### Download Action Options

Each `<download>` action supports:

- **software**: Name or alias of the software to download (required)
- **version**: Target version using Composer versioning syntax (e.g., `^2.12.0`, `~1.0`, `1.2.3`)
- **extract-path**: Directory where files will be extracted (useful for non-binary assets)

The **version** attribute supports advanced constraints for targeting specific release types.
You can use feature suffixes to target releases with custom tags like `^2.12.0-experimental` for experimental builds,
or minimum-stability constraints to filter by release stability such as `^2.12.0@beta` for beta or more stable releases.

### Handling Different File Types

DLoad handles both binary executables and regular files:

```xml
<software name="my-app">
    <!-- Binary executable that depends on OS/architecture -->
    <binary name="app-cli" pattern="/^app-cli-.*/" />
    
    <!-- Regular file that works on any system -->
    <file pattern="/^config.yml$/" />
</software>
```

### Automatic Binary Downloads with Composer Update

Integrate DLoad with Composer to automatically download required binaries whenever dependencies are updated.
This ensures your team always has the necessary tools without manual intervention.

Add the following to your `composer.json`:

```json
{
    "scripts": {
        "post-update-cmd": "dload get --no-interaction -v || echo can't dload binaries",
        "get:binaries": "dload get --no-interaction --force -vv"
    }
}
```

This configuration:

- Automatically downloads required binaries after `composer update`
- Provides a custom command `composer get:binaries` to force download all binaries with detailed output

## Custom Software Registry

### Defining Custom Software

Create your own software definitions:

```xml
<?xml version="1.0"?>
<dload xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/php-internal/dload/refs/heads/1.x/dload.xsd"
>
    <registry overwrite="false">
        <!-- Binary software example -->
        <software name="RoadRunner" alias="rr"
                  homepage="https://roadrunner.dev"
                  description="High performant Application server">
            <repository type="github" uri="roadrunner-server/roadrunner" asset-pattern="/^roadrunner-.*/" />
            <binary name="rr" pattern="/^roadrunner-.*/" />
        </software>

        <!-- Non-binary files example -->
        <software name="frontend" description="Frontend assets">
            <repository type="github"
                        uri="my-org/frontend"
                        asset-pattern="/^artifacts.*/"
            />
            <file pattern="/^.*\.js$/" />
            <file pattern="/^.*\.css$/" />
        </software>

        <!-- Software with mixed file types -->
        <software name="my-tool" description="Complete tool suite">
            <repository type="github" uri="my-org/tool" />
            <!-- Binary executables -->
            <binary name="tool-cli" pattern="/^tool-cli.*/" />
            <binary name="tool-worker" pattern="/^worker.*/" />
            <!-- Configuration files -->
            <file pattern="/^config\.yml$/" extract-path="config" />
            <file pattern="/^templates\/.*/" extract-path="templates" />
        </software>
    </registry>
</dload>
```

### Software Configuration Options

Each `<software>` entry supports:

- **name**: Display name (required)
- **alias**: Short name for command line usage
- **description**: Brief description
- **homepage**: Website URL

#### Repository Options

The `<repository>` element configures where to download from:

- **type**: Currently supports "github"
- **uri**: Repository path (e.g., "username/repo")
- **asset-pattern**: Regex pattern to match release assets

#### Binary Options

The `<binary>` element defines executable files:

- **name**: Binary name that will be referenced
- **pattern**: Regex pattern to match the binary in release assets

Binary files are OS and architecture specific. DLoad will automatically download the correct version for your system.

#### File Options

The `<file>` element defines non-binary files:

- **pattern**: Regex pattern to match files
- **extract-path**: Optional subdirectory where files will be extracted

File assets don't have OS/architecture restrictions and work on any system.

## GitHub API Rate Limits

To avoid GitHub API rate limits, use a personal access token:

```bash
GITHUB_TOKEN=your_token_here ./vendor/bin/dload get
```

You can add this to your CI/CD pipeline environment variables for automated downloads.

## Use Cases

### Local Development Environment Setup

Automatically download required tools when setting up a development environment:

```bash
# Initialize project with all required tools
composer install
./vendor/bin/dload get
```

### CI/CD Pipeline Integration

In your GitHub Actions workflow:

```yaml
steps:
  - uses: actions/checkout@v2

  - name: Setup PHP
    uses: shivammathur/setup-php@v2
    with:
      php-version: '8.1'

  - name: Install dependencies
    run: composer install

  - name: Download binary tools
    run: |
      GITHUB_TOKEN=${{ secrets.GITHUB_TOKEN }} ./vendor/bin/dload get
```

### Cross-Platform Development Team

Configure once, use everywhere:

```xml
<dload xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/php-internal/dload/refs/heads/1.x/dload.xsd"
>
    <actions>
        <download software="rr" version="^2.12.0" />
        <download software="temporal" />
    </actions>
</dload>
```

Each team member runs `./vendor/bin/dload get` and gets the correct binaries for their system (Windows, macOS, or Linux).

### Distributed Frontend Assets

Keep your frontend assets separate from your PHP repository:

```xml
<software name="frontend-bundle">
    <repository type="github" uri="your-org/frontend-build" asset-pattern="/^dist.*/" />
    <file pattern="/^.*$/" extract-path="public/assets" />
</software>
```

### Advanced Development Workflows

Target specific release types for different environments:

```xml
<dload xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/php-internal/dload/refs/heads/1.x/dload.xsd"
>
    <actions>
        <!-- Production: stable releases only -->
        <download software="rr" version="^2.12.0@stable" />
        
        <!-- Staging: beta releases for testing -->
        <download software="temporal" version="^1.20.0@beta" />
        
        <!-- Development: experimental features -->
        <download software="dev-tool" version="^1.0.0-experimental@dev" />
        
        <!-- Feature branch testing -->
        <download software="app" version="^2.0.0-new-api@alpha" />
    </actions>
</dload>
```

## Contributing

Contributions are welcome!
Feel free to submit a Pull Request to add new software to the predefined list or improve the functionality of DLoad.
