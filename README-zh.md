<div align="center">

![DLoad](./resources/logo.svg)

</div>

<p align="center">轻松下载工件</p>

<div align="center">

[![Support](https://img.shields.io/static/v1?style=flat-square&label=Support&message=%E2%9D%A4&logo=GitHub&color=%23fe0086)](https://patreon.com/roxblnfk)

</div>

<br />

DLoad 简化了为您的项目下载和管理二进制工件的过程。非常适合需要特定工具（如 RoadRunner、Temporal 或自定义二进制文件）的开发环境。

[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%BA%F0%9F%87%B8-moccasin?style=flat-square)](README.md)
[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![Russian readme](https://img.shields.io/badge/README-Русский%20%F0%9F%87%B7%F0%9F%87%BA-moccasin?style=flat-square)](README-ru.md)
[![Spanish readme](https://img.shields.io/badge/README-Español%20%F0%9F%87%AA%F0%9F%87%B8-moccasin?style=flat-square)](README-es.md)

## 为什么选择 DLoad？

DLoad 解决了 PHP 项目中的一个常见问题：如何在 PHP 代码的同时分发和安装必要的二进制工具和资产。
使用 DLoad，您可以：

- 在项目初始化期间自动下载所需工具
- 确保所有团队成员使用相同版本的工具
- 通过自动化环境设置简化新人入职
- 管理跨平台兼容性，无需手动配置
- 将二进制文件和资产与版本控制分开

### 目录

- [安装](#安装)
- [快速开始](#快速开始)
- [命令行使用](#命令行使用)
    - [初始化配置](#初始化配置)
    - [下载软件](#下载软件)
    - [查看软件](#查看软件)
    - [构建自定义软件](#构建自定义软件)
- [配置指南](#配置指南)
    - [交互式配置](#交互式配置)
    - [手动配置](#手动配置)
    - [下载类型](#下载类型)
    - [版本约束](#版本约束)
    - [高级配置选项](#高级配置选项)
- [构建自定义 RoadRunner](#构建自定义-roadrunner)
    - [构建操作配置](#构建操作配置)
    - [Velox 操作属性](#velox-操作属性)
    - [构建过程](#构建过程)
    - [配置文件生成](#配置文件生成)
    - [使用下载的 Velox](#使用下载的-velox)
    - [DLoad 配置](#dload-配置)
    - [构建 RoadRunner](#构建-roadrunner)
- [自定义软件注册表](#自定义软件注册表)
    - [定义软件](#定义软件)
    - [软件元素](#软件元素)
- [用例](#用例)
    - [开发环境设置](#开发环境设置)
    - [新项目设置](#新项目设置)
    - [CI/CD 集成](#cicd-集成)
    - [跨平台团队](#跨平台团队)
    - [PHAR 工具管理](#phar-工具管理)
    - [前端资产分发](#前端资产分发)
- [GitHub API 速率限制](#github-api-速率限制)
- [贡献](#贡献)


## 安装

```bash
composer require internal/dload -W
```

[![PHP](https://img.shields.io/packagist/php-v/internal/dload.svg?style=flat-square&logo=php)](https://packagist.org/packages/internal/dload)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/internal/dload.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/internal/dload)
[![License](https://img.shields.io/packagist/l/internal/dload.svg?style=flat-square)](LICENSE.md)
[![Total DLoads](https://img.shields.io/packagist/dt/internal/dload.svg?style=flat-square)](https://packagist.org/packages/internal/dload/stats)

## 快速开始

1. **通过 Composer 安装 DLoad**：

    ```bash
    composer require internal/dload -W
    ```

或者，您可以从 [GitHub 发布页面](https://github.com/php-internal/dload/releases) 下载最新版本。

2. **交互式创建配置文件**：

    ```bash
    ./vendor/bin/dload init
    ```

    此命令将指导您选择软件包并创建 `dload.xml` 配置文件。您也可以手动创建：

    ```xml
    <?xml version="1.0"?>
    <dload xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/php-internal/dload/refs/heads/1.x/dload.xsd"
   >
       <actions>
           <download software="rr" version="^2025.1.0"/>
           <download software="temporal" version="^1.3"/>
       </actions>
    </dload>
    ```

3. **下载配置的软件**：

    ```bash
    ./vendor/bin/dload get
    ```

4. **与 Composer 集成**（可选）：

    ```json
    {
        "scripts": {
            "post-update-cmd": "dload get --no-interaction -v || \"echo can't dload binaries\""
        }
    }
    ```

## 命令行使用

### 初始化配置

```bash
# 交互式创建配置文件
./vendor/bin/dload init

# 在指定位置创建配置
./vendor/bin/dload init --config=./custom-dload.xml

# 创建最小配置，无提示
./vendor/bin/dload init --no-interaction

# 覆盖现有配置而不确认
./vendor/bin/dload init --overwrite
```

### 下载软件

```bash
# 从配置文件下载
./vendor/bin/dload get

# 下载特定包
./vendor/bin/dload get rr temporal

# 使用选项下载
./vendor/bin/dload get rr --stability=beta --force
```

#### 下载选项

| 选项 | 描述 | 默认值 |
|--------|-------------|---------|
| `--path` | 存储二进制文件的目录 | 当前目录 |
| `--arch` | 目标架构 (amd64, arm64) | 系统架构 |
| `--os` | 目标操作系统 (linux, darwin, windows) | 当前操作系统 |
| `--stability` | 发布稳定性 (stable, beta) | stable |
| `--config` | 配置文件路径 | ./dload.xml |
| `--force`, `-f` | 即使二进制文件存在也强制下载 | false |

### 查看软件

```bash
# 列出可用的软件包
./vendor/bin/dload software

# 显示已下载的软件
./vendor/bin/dload show

# 显示特定软件详情
./vendor/bin/dload show rr

# 显示所有软件（已下载和可用的）
./vendor/bin/dload show --all
```

### 构建自定义软件

```bash
# 使用配置文件构建自定义软件
./vendor/bin/dload build

# 使用特定配置文件构建
./vendor/bin/dload build --config=./custom-dload.xml
```

#### 构建选项

| 选项 | 描述 | 默认值 |
|--------|-------------|---------|
| `--config` | 配置文件路径 | ./dload.xml |

`build` 命令执行配置文件中定义的构建操作，例如创建具有特定插件的自定义 RoadRunner 二进制文件。
有关构建自定义 RoadRunner 的详细信息，请参阅 [构建自定义 RoadRunner](#构建自定义-roadrunner) 部分。

## 配置指南

### 交互式配置

创建配置文件的最简单方法是使用交互式 `init` 命令：

```bash
./vendor/bin/dload init
```

这将：

- 指导您选择软件包
- 显示可用软件及其描述和仓库
- 生成格式正确的 `dload.xml` 文件并进行模式验证
- 优雅地处理现有配置文件

### 手动配置

在项目根目录创建 `dload.xml`：

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

### 下载类型

DLoad 支持三种下载类型，决定资产的处理方式：

#### 类型属性

```xml
<!-- 显式类型指定 -->
<download software="psalm" type="phar" />        <!-- 下载 .phar 而不解包 -->
<download software="frontend" type="archive" />  <!-- 强制归档提取 -->
<download software="rr" type="binary" />         <!-- 二进制特定处理 -->

<!-- 自动类型处理（推荐） -->
<download software="rr" />           <!-- 使用所有可用处理器 -->
<download software="frontend" />     <!-- 基于软件配置的智能处理 -->
```

#### 默认行为（未指定类型）

当未指定 `type` 时，DLoad 自动使用所有可用的处理器：

- **二进制处理**：如果软件有 `<binary>` 部分，执行二进制存在性和版本检查
- **文件处理**：如果软件有 `<file>` 部分且资产已下载，在解包期间处理文件
- **简单下载**：如果没有部分存在，下载资产而不解包

```xml
<!-- 注册表列表 -->
<software name="complex-tool">
    <binary name="tool" pattern="/^tool-.*/" />
    <file pattern="/^config\..*/" extract-path="config" />
</software>

<!-- 操作列表 -->
<!-- 使用二进制和文件处理 -->
<download software="complex-tool" />
```

#### 显式类型行为

| 类型      | 行为                                                     | 用例                       |
|-----------|--------------------------------------------------------------|--------------------------------|
| `binary`  | 二进制检查、版本验证、可执行权限  | CLI 工具、可执行文件         |
| `phar`    | 下载 `.phar` 文件作为可执行文件**而不解包** | PHP 工具如 Psalm、PHPStan  |
| `archive` | **强制解包即使是 .phar 文件**                    | 当您需要归档内容时 |

> [!NOTE]
> 对于应保持为 `.phar` 文件的 PHP 工具，使用 `type="phar"`。
> 使用 `type="archive"` 将解包甚至 `.phar` 归档。

### 版本约束

使用 Composer 风格的版本约束：

```xml
<actions>
    <!-- 精确版本 -->
    <download software="rr" version="2.12.3" />
    
    <!-- 范围约束 -->
    <download software="temporal" version="^1.20.0" />
    <download software="dolt" version="~0.50.0" />
    
    <!-- 稳定性约束 -->
    <download software="tool" version="^1.0.0@beta" />
    
    <!-- 功能发布（自动设置预览稳定性） -->
    <download software="experimental" version="^1.0.0-experimental" />
</actions>
```

### 高级配置选项

```xml
<dload temp-dir="./runtime">
    <actions>
        <!-- 不同的提取路径 -->
        <download software="frontend" extract-path="public/assets" />
        <download software="config" extract-path="config" />
        
        <!-- 针对不同环境 -->
        <download software="prod-tool" version="^2.0.0@stable" />
        <download software="dev-tool" version="^2.0.0@beta" />
    </actions>
</dload>
```

## 构建自定义 RoadRunner

DLoad 支持使用 Velox 构建工具构建自定义 RoadRunner 二进制文件。当您需要具有预构建版本中不可用的自定义插件组合的 RoadRunner 时，这非常有用。

### 构建操作配置

```xml
<actions>
    <!-- 使用本地 velox.toml 的基本配置 -->
    <velox config-file="./velox.toml" />
    
    <!-- 使用特定版本 -->
    <velox config-file="./velox.toml" 
          velox-version="^1.4.0" 
          golang-version="^1.22" 
          binary-version="2024.1.5" 
          binary-path="./bin/rr" />
</actions>
```

### Velox 操作属性

| 属性 | 描述 | 默认值 |
|-----------|-------------|---------|
| `velox-version` | Velox 构建工具版本 | 最新 |
| `golang-version` | 所需的 Go 版本 | 最新 |
| `binary-version` | 在 `rr --version` 中显示的 RoadRunner 版本 | 最新 |
| `config-file` | 本地 velox.toml 文件路径 | `./velox.toml` |
| `binary-path` | 保存构建的 RoadRunner 二进制文件的路径 | `./rr` |

### 构建过程

DLoad 自动处理构建过程：

1. **Golang 检查**：验证 Go 是否全局安装（必需依赖项）
2. **Velox 准备**：使用全局安装的 Velox、本地下载或在需要时自动下载
3. **配置**：将您的本地 velox.toml 复制到构建目录
4. **构建**：使用指定配置执行 `vx build` 命令
5. **安装**：将构建的二进制文件移动到目标位置并设置可执行权限
6. **清理**：删除临时构建文件

> [!NOTE]
> DLoad 需要在您的系统上全局安装 Go (Golang)。它不会下载或管理 Go 安装。

### 配置文件生成

您可以使用 https://build.roadrunner.dev/ 上的在线构建器生成 `velox.toml` 配置文件

有关 Velox 配置选项和示例的详细文档，请访问 https://docs.roadrunner.dev/docs/customization/build

此 Web 界面帮助您选择插件并为您的自定义 RoadRunner 构建生成适当的配置。

### 使用下载的 Velox

您可以将 Velox 作为构建过程的一部分下载，而不是依赖全局安装的版本：

```xml
<actions>
    <download software="velox" extract-path="bin" version="2025.1.1" />
    <velox config-file="velox.toml"
          golang-version="^1.22"
          binary-version="2024.1.5" />
</actions>
```

这确保在不同环境和团队成员之间使用一致的 Velox 版本。

### DLoad 配置

```xml
<?xml version="1.0"?>
<dload xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
       xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/php-internal/dload/refs/heads/1.x/dload.xsd">
    <actions>
        <velox config-file="./velox.toml" 
              velox-version="^1.4.0"
              golang-version="^1.22"
              binary-version="2024.1.5"
              binary-path="./bin/rr" />
    </actions>
</dload>
```

### 构建 RoadRunner

```bash
# 使用 velox.toml 配置构建 RoadRunner
./vendor/bin/dload build

# 使用特定配置文件构建
./vendor/bin/dload build --config=custom-rr.xml
```

构建的 RoadRunner 二进制文件将仅包含您在 `velox.toml` 文件中指定的插件，从而减少二进制文件大小并提高特定用例的性能。

## 自定义软件注册表

### 定义软件

```xml
<dload>
    <registry overwrite="false">
        <!-- 二进制可执行文件 -->
        <software name="RoadRunner" alias="rr" 
                  homepage="https://roadrunner.dev"
                  description="高性能应用服务器">
            <repository type="github" uri="roadrunner-server/roadrunner" asset-pattern="/^roadrunner-.*/" />
            <binary name="rr" pattern="/^roadrunner-.*/" />
        </software>

        <!-- 包含文件的归档 -->
        <software name="frontend" description="前端资产">
            <repository type="github" uri="my-org/frontend" asset-pattern="/^artifacts.*/" />
            <file pattern="/^.*\.js$/" />
            <file pattern="/^.*\.css$/" />
        </software>

        <!-- 混合：二进制文件 + 文件 -->
        <software name="development-suite" description="完整的开发工具">
            <repository type="github" uri="my-org/dev-tools" />
            <binary name="cli-tool" pattern="/^cli-tool.*/" />
            <file pattern="/^config\.yml$/" extract-path="config" />
            <file pattern="/^templates\/.*/" extract-path="templates" />
        </software>

        <!-- PHAR 工具 -->
        <software name="psalm" description="静态分析工具">
            <repository type="github" uri="vimeo/psalm" />
            <binary name="psalm.phar" pattern="/^psalm\.phar$/" />
        </software>
    </registry>
</dload>
```

### 软件元素

#### 仓库配置

- **type**：目前支持 "github"
- **uri**：仓库路径（例如，"username/repo"）
- **asset-pattern**：匹配发布资产的正则表达式模式

#### 二进制元素

- **name**：用于引用的二进制名称
- **pattern**：匹配资产中二进制文件的正则表达式模式
- 自动处理操作系统/架构过滤

#### 文件元素

- **pattern**：匹配文件的正则表达式模式
- **extract-path**：可选的提取目录
- 在任何系统上工作（无操作系统/架构过滤）

## 用例

### 开发环境设置

```bash
# 新开发者的一次性设置
composer install
./vendor/bin/dload init  # 仅第一次
./vendor/bin/dload get
```

### 新项目设置

```bash
# 使用 DLoad 启动新项目
composer init
composer require internal/dload -W
./vendor/bin/dload init
./vendor/bin/dload get
```

### CI/CD 集成

```yaml
# GitHub Actions
- name: Download tools
  run: GITHUB_TOKEN=${{ secrets.GITHUB_TOKEN }} ./vendor/bin/dload get
```

### 跨平台团队

每个开发者获得适合其系统的正确二进制文件：

```xml
<actions>
    <download software="rr" />        <!-- Linux 的 Linux 二进制文件，Windows 的 Windows .exe -->
    <download software="temporal" />   <!-- macOS 的 macOS 二进制文件等 -->
</actions>
```

### PHAR 工具管理

```xml
<actions>
    <!-- 下载为可执行的 .phar 文件 -->
    <download software="psalm" type="phar" />
    <download software="phpstan" type="phar" />
    
    <!-- 改为提取内容 -->
    <download software="psalm" type="archive" />  <!-- 解包 psalm.phar -->
</actions>
```

### 前端资产分发

```xml
<software name="ui-kit">
    <repository type="github" uri="company/ui-components" />
    <file pattern="/^dist\/.*/" extract-path="public/components" />
</software>

<actions>
    <download software="ui-kit" type="archive" />
</actions>
```

## GitHub API 速率限制

使用个人访问令牌以避免速率限制：

```bash
GITHUB_TOKEN=your_token_here ./vendor/bin/dload get
```

将其添加到 CI/CD 环境变量中以进行自动下载。

## 贡献

欢迎贡献！提交拉取请求以：

- 向预定义注册表添加新软件
- 改进 DLoad 功能  
- 增强文档并将其翻译为[其他语言](docs/guidelines/how-to-translate-readme-docs.md)
