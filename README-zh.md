<div align="center">

![DLoad](./resources/logo.svg)

</div>

<p align="center">轻松下载构建产物</p>

<div align="center">

[![Support](https://img.shields.io/static/v1?style=flat-square&label=Support&message=%E2%9D%A4&logo=GitHub&color=%23fe0086)](https://patreon.com/roxblnfk)

</div>

<br />

DLoad 简化了你项目中二进制构建产物的下载和管理。非常适合需要特定工具（如 RoadRunner、Temporal 或自定义二进制文件）的开发环境。

[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%BA%F0%9F%87%B8-moccasin?style=flat-square)](README.md)
[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![Russian readme](https://img.shields.io/badge/README-Русский%20%F0%9F%87%B7%F0%9F%87%BA-moccasin?style=flat-square)](README-ru.md)
[![Spanish readme](https://img.shields.io/badge/README-Español%20%F0%9F%87%AA%F0%9F%87%B8-moccasin?style=flat-square)](README-es.md)

## 为什么选择 DLoad？

DLoad 解决了 PHP 项目中的一个常见问题：如何将必要的二进制工具和资源与 PHP 代码一起分发和安装。
使用 DLoad，你可以：

- 在项目初始化时自动下载所需工具
- 确保所有团队成员使用相同版本的工具
- 通过自动化环境配置简化新成员入职流程
- 无需手动配置即可管理跨平台兼容性
- 将二进制文件和资源与版本控制分离


## 安装

```bash
composer require internal/dload -W
```

[![PHP](https://img.shields.io/packagist/php-v/internal/dload.svg?style=flat-square&logo=php)](https://packagist.org/packages/internal/dload)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/internal/dload.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/internal/dload)
[![License](https://img.shields.io/packagist/l/internal/dload.svg?style=flat-square)](LICENSE.md)
[![Total DLoads](https://img.shields.io/packagist/dt/internal/dload.svg?style=flat-square)](https://packagist.org/packages/internal/dload/stats)

## 快速开始

1. **初始化你的项目配置**：

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

2. **下载配置的软件**：

    ```bash
    ./vendor/bin/dload get
    ```

3. **与 Composer 集成**（可选）：

    ```json
    {
        "scripts": {
            "post-update-cmd": "dload get --no-interaction -v || echo can't dload binaries"
        }
    }
    ```


## 命令行用法

### 下载软件

```bash
# 从配置文件下载
./vendor/bin/dload get

# 下载指定包
./vendor/bin/dload get rr temporal

# 使用选项下载
./vendor/bin/dload get rr --stability=beta --force
```

#### 下载选项

| 选项              | 描述                             | 默认值         |
|:----------------|:-------------------------------|:------------|
| `--path`        | 存储二进制文件的目录                     | 当前目录        |
| `--arch`        | 目标架构（amd64, arm64）             | 系统架构        |
| `--os`          | 目标操作系统（linux, darwin, windows） | 当前操作系统      |
| `--stability`   | 发布稳定性（stable, beta）            | stable      |
| `--config`      | 配置文件路径                         | ./dload.xml |
| `--force`, `-f` | 即使二进制已存在也强制下载                  | false       |

### 查看软件

```bash
# 列出可用软件包
./vendor/bin/dload software

# 显示已下载软件
./vendor/bin/dload show

# 显示指定软件详情
./vendor/bin/dload show rr

# 显示所有软件（已下载和可用）
./vendor/bin/dload show --all
```


## 配置指南

### 基础配置

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

DLoad 支持三种下载类型，用于决定资源的处理方式：

#### type 属性

```xml
<!-- 明确指定类型 -->
<download software="psalm" type="phar" />        <!-- 下载 .phar 文件，不解包 -->
<download software="frontend" type="archive" />  <!-- 强制解压归档文件 -->
<download software="rr" type="binary" />         <!-- 针对二进制的处理 -->

<!-- 自动类型处理（推荐） -->
<download software="rr" />           <!-- 使用所有可用处理器 -->
<download software="frontend" />     <!-- 根据软件配置智能处理 -->
```

#### 默认行为（未指定 type）

当未指定 `type` 时，DLoad 会自动使用所有可用处理器：

- **二进制处理**：如果软件有 `<binary>` 节点，则检查二进制是否存在及其版本
- **文件处理**：如果有 `<file>` 节点且资源已下载，则在解包时处理文件
- **简单下载**：如无任何节点，仅下载资源，不解包

```xml
<!-- 注册表列表 -->
<software name="complex-tool">
    <binary name="tool" pattern="/^tool-.*/" />
    <file pattern="/^config\..*/" extract-path="config" />
</software>

<!-- 动作列表 -->
<!-- 同时使用二进制和文件处理 -->
<download software="complex-tool" />
```

#### 明确类型行为

| 类型 | 行为 | 使用场景 |
| :-- | :-- | :-- |
| `binary` | 检查二进制、验证版本、设置可执行权限 | CLI 工具、可执行文件 |
| `phar` | 以可执行文件形式下载 `.phar`，**不解包** | PHP 工具如 Psalm、PHPStan |
| `archive` | **即使是 .phar 文件也强制解包** | 需要归档内容时 |

> [!NOTE]
> 对于应保持为 `.phar` 文件的 PHP 工具，请使用 `type="phar"`。
> 使用 `type="archive"` 会解包 `.phar` 归档。

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

    <!-- 特性发布（自动设置为 preview 稳定性） -->
    <download software="experimental" version="^1.0.0-experimental" />
</actions>
```

### 高级配置选项

```xml
<dload temp-dir="./runtime">
    <actions>
        <!-- 不同的解压路径 -->
        <download software="frontend" extract-path="public/assets" />
        <download software="config" extract-path="config" />

        <!-- 目标不同环境 -->
        <download software="prod-tool" version="^2.0.0@stable" />
        <download software="dev-tool" version="^2.0.0@beta" />
    </actions>
</dload>
```


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

        <!-- 含文件的归档 -->
        <software name="frontend" description="前端资源">
            <repository type="github" uri="my-org/frontend" asset-pattern="/^artifacts.*/" />
            <file pattern="/^.*\.js$/" />
            <file pattern="/^.*\.css$/" />
        </software>

        <!-- 混合：二进制 + 文件 -->
        <software name="development-suite" description="完整开发工具集">
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
- **uri**：仓库路径（如 "username/repo"）
- **asset-pattern**：用于匹配发布资源的正则表达式

#### Binary 元素

- **name**：二进制名称，用于引用
- **pattern**：匹配资源中二进制文件的正则表达式
- 自动处理操作系统/架构过滤

#### File 元素

- **pattern**：匹配文件的正则表达式
- **extract-path**：可选，解压目录
- 适用于所有系统（不区分操作系统/架构）


## 使用场景

### 开发环境搭建

```bash
# 新开发者一次性环境搭建
composer install
./vendor/bin/dload get
```

### CI/CD 集成

```yaml
# GitHub Actions
- name: 下载工具
  run: GITHUB_TOKEN=${{ secrets.GITHUB_TOKEN }} ./vendor/bin/dload get
```

### 跨平台团队

每位开发者都能获得适合其系统的二进制文件：

```xml
<actions>
    <download software="rr" />        <!-- Linux 下为 Linux 二进制，Windows 下为 .exe -->
    <download software="temporal" />   <!-- macOS 下为 macOS 二进制等 -->
</actions>
```

### PHAR 工具管理

```xml
<actions>
    <!-- 以可执行 .phar 文件下载 -->
    <download software="psalm" type="phar" />
    <download software="phpstan" type="phar" />

    <!-- 也可以解包内容 -->
    <download software="psalm" type="archive" />  <!-- 解包 psalm.phar -->
</actions>
```

### 前端资源分发

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

使用个人访问令牌可避免速率限制：

```bash
GITHUB_TOKEN=your_token_here ./vendor/bin/dload get
```

在 CI/CD 环境变量中添加以实现自动下载。

## 贡献

欢迎贡献！提交 Pull Request 可：

- 向预定义注册表添加新软件
- 改进 DLoad 功能
- 改进文档并[翻译成其他语言](docs/guidelines/how-to-translate-readme-docs.md)
