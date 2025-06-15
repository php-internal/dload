<div align="center">

![DLoad](./resources/logo.svg)

</div>

<p align="center">Download artifacts easily</p>

<div align="center">

[![Support](https://img.shields.io/static/v1?style=flat-square&label=Support&message=%E2%9D%A4&logo=GitHub&color=%23fe0086)](https://patreon.com/roxblnfk)
[![Chinese readme](https://camo.githubusercontent.com/e9dee61c90a40244e7f37bf6a2599f4d047703887c5f0a5d11461e7dcbc4a01c/68747470733a2f2f696d672e736869656c64732e696f2f62616467652f524541444d452d2545342542382541442545362539362538372532302546302539462538372541382546302539462538372542332d6d6f63636173696e3f7374796c653d666c61742d737175617265)](README-zh.md)
[![English readme](https://camo.githubusercontent.com/b314871e659ba22c274756c3be9e9df0d7216fe55c6d4a8099e6412efadab525/68747470733a2f2f696d672e736869656c64732e696f2f62616467652f524541444d452d456e676c6973682532302546302539462538372542412546302539462538372542382d6d6f63636173696e3f7374796c653d666c61742d737175617265)](README.md)
[![Русский readme](https://camo.githubusercontent.com/4738967b86b85dc692816380ff7e688cebaa2269d8520d6526de2762dd5b322a/68747470733a2f2f696d672e736869656c64732e696f2f62616467652f524541444d452d2544302541302544312538332544312538312544312538312544302542412544302542382544302542392532302546302539462538372542372546302539462538372542412d6d6f63636173696e3f7374796c653d666c61742d737175617265)](README-ru.md)
[![Español readme](https://camo.githubusercontent.com/92ce47b3489ae195ec65fd1f94576d3c9e5899672ff55ea475d83ae4afa2102e/68747470733a2f2f696d672e736869656c64732e696f2f62616467652f524541444d452d457370612543332542316f6c2532302546302539462538372541412546302539462538372542382d6d6f63636173696e3f7374796c653d666c61742d737175617265)](README-es.md)

</div>

<br />

DLoad упрощает загрузку и управление бинарными артефактами для ваших проектов. Идеально подходит для сред разработки, которые требуют специфические инструменты, такие как RoadRunner, Temporal или кастомные бинарные файлы.

## Почему DLoad?

DLoad решает распространённую проблему в PHP-проектах: как распространять и устанавливать необходимые бинарные инструменты и ресурсы вместе с вашим PHP-кодом.
С помощью DLoad вы можете:

- Автоматически загружать нужные инструменты при инициализации проекта
- Гарантировать, что все члены команды используют одинаковые версии инструментов
- Упростить адаптацию новых участников, автоматизируя настройку окружения
- Управлять кроссплатформенной совместимостью без ручной настройки
- Хранить бинарные файлы и ресурсы отдельно от системы контроля версий


## Установка

```bash
composer require internal/dload -W
```

[![PHP](https://img.shields.io/packagist/php-v/internal/dload.svg?style=flat-square&logo=php)](https://packagist.org/packages/internal/dload)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/internal/dload.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/internal/dload)
[![License](https://img.shields.io/packagist/l/internal/dload.svg?style=flat-square)](LICENSE.md)
[![Total DLoads](https://img.shields.io/packagist/dt/internal/dload.svg?style=flat-square)](https://packagist.org/packages/internal/dload/stats)

## Быстрый старт

1. **Инициализируйте конфигурацию проекта**:

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

2. **Скачайте настроенное программное обеспечение**:

    ```bash
    ./vendor/bin/dload get
    ```

3. **Интеграция с Composer** (опционально):

    ```json
    {
        "scripts": {
            "post-update-cmd": "dload get --no-interaction -v || echo can't dload binaries"
        }
    }
    ```


## Использование из командной строки

### Загрузка программного обеспечения

```bash
# Загрузка из конфигурационного файла
./vendor/bin/dload get

# Загрузка конкретных пакетов
./vendor/bin/dload get rr temporal

# Загрузка с опциями
./vendor/bin/dload get rr --stability=beta --force
```


#### Опции загрузки

| Опция           | Описание                                              | По умолчанию        |
|:----------------|:------------------------------------------------------|:--------------------|
| `--path`        | Каталог для хранения бинарников                       | Текущая директория  |
| `--arch`        | Целевая архитектура (amd64, arm64)                    | Архитектура системы |
| `--os`          | Целевая ОС (linux, darwin, windows)                   | Текущая ОС          |
| `--stability`   | Стабильность релиза (stable, beta)                    | stable              |
| `--config`      | Путь к конфигурационному файлу                        | ./dload.xml         |
| `--force`, `-f` | Принудительная загрузка даже если бинарник существует | false               |

### Просмотр программного обеспечения

```bash
# Список доступных пакетов
./vendor/bin/dload software

# Показать загруженное ПО
./vendor/bin/dload show

# Показать детали конкретного ПО
./vendor/bin/dload show rr

# Показать всё ПО (загруженное и доступное)
./vendor/bin/dload show --all
```


## Руководство по конфигурации

### Базовая конфигурация

Создайте `dload.xml` в корне проекта:

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


### Типы загрузок

DLoad поддерживает три типа загрузок, которые определяют, как обрабатываются ресурсы:

#### Атрибут type

```xml
<!-- Явное указание типа -->
<download software="psalm" type="phar" />        <!-- Скачивает .phar без распаковки -->
<download software="frontend" type="archive" />  <!-- Принудительная распаковка архива -->
<download software="rr" type="binary" />         <!-- Специфическая обработка бинарников -->

<!-- Автоматическая обработка (рекомендуется) -->
<download software="rr" />           <!-- Использует все доступные обработчики -->
<download software="frontend" />     <!-- Умная обработка по конфигурации ПО -->
```


#### Поведение по умолчанию (без указания type)

Если `type` не указан, DLoad автоматически применяет все доступные обработчики:

- **Обработка бинарников**: Если у ПО есть секция `<binary>`, выполняется проверка наличия и версии бинарника
- **Обработка файлов**: Если у ПО есть секция `<file>` и артефакт загружен, файлы обрабатываются при распаковке
- **Простая загрузка**: Если секций нет, артефакт скачивается без распаковки

```xml
<!-- список реестра -->
<software name="complex-tool">
    <binary name="tool" pattern="/^tool-.*/" />
    <file pattern="/^config\..*/" extract-path="config" />
</software>

<!-- список действий -->
<!-- Использует и бинарную, и файловую обработку -->
<download software="complex-tool" />
```


#### Явное поведение по типу

| Тип       | Поведение                                                 | Случай использования                    |
|:----------|:----------------------------------------------------------|:----------------------------------------|
| `binary`  | Проверка бинарника, валидация версии, права на выполнение | CLI-инструменты, исполняемые файлы      |
| `phar`    | Скачивает `.phar` как исполняемый файл **без распаковки** | PHP-инструменты типа Psalm, PHPStan     |
| `archive` | **Принудительная распаковка даже для .phar файлов**       | Когда нужен доступ к содержимому архива |

> [!ПРИМЕЧАНИЕ]
> Используйте `type="phar"` для PHP-инструментов, которые должны оставаться `.phar` файлами.
> Использование `type="archive"` распакует даже `.phar` архивы.

### Ограничения по версиям

Используйте ограничения версий в стиле Composer:

```xml
<actions>
    <!-- Точная версия -->
    <download software="rr" version="2.12.3" />
    
    <!-- Диапазоны версий -->
    <download software="temporal" version="^1.20.0" />
    <download software="dolt" version="~0.50.0" />
    
    <!-- Ограничения по стабильности -->
    <download software="tool" version="^1.0.0@beta" />
    
    <!-- Фич-релизы (автоматически устанавливает preview стабильность) -->
    <download software="experimental" version="^1.0.0-experimental" />
</actions>
```


### Расширенные опции конфигурации

```xml
<dload temp-dir="./runtime">
    <actions>
        <!-- Разные пути распаковки -->
        <download software="frontend" extract-path="public/assets" />
        <download software="config" extract-path="config" />
        
        <!-- Целевые окружения -->
        <download software="prod-tool" version="^2.0.0@stable" />
        <download software="dev-tool" version="^2.0.0@beta" />
    </actions>
</dload>
```

## Пользовательский реестр ПО

### Определение ПО

```xml
<dload>
    <registry overwrite="false">
        <!-- Бинарный исполняемый файл -->
        <software name="RoadRunner" alias="rr" 
                  homepage="https://roadrunner.dev"
                  description="Высокопроизводительный сервер приложений">
            <repository type="github" uri="roadrunner-server/roadrunner" asset-pattern="/^roadrunner-.*/" />
            <binary name="rr" pattern="/^roadrunner-.*/" />
        </software>

        <!-- Архив с файлами -->
        <software name="frontend" description="Фронтенд-ресурсы">
            <repository type="github" uri="my-org/frontend" asset-pattern="/^artifacts.*/" />
            <file pattern="/^.*\.js$/" />
            <file pattern="/^.*\.css$/" />
        </software>

        <!-- Смешанное: бинарники + файлы -->
        <software name="development-suite" description="Полный набор инструментов разработки">
            <repository type="github" uri="my-org/dev-tools" />
            <binary name="cli-tool" pattern="/^cli-tool.*/" />
            <file pattern="/^config\.yml$/" extract-path="config" />
            <file pattern="/^templates\/.*/" extract-path="templates" />
        </software>

        <!-- PHAR инструменты -->
        <software name="psalm" description="Инструмент статического анализа">
            <repository type="github" uri="vimeo/psalm" />
            <binary name="psalm.phar" pattern="/^psalm\.phar$/" />
        </software>
    </registry>
</dload>
```


### Элементы ПО

#### Конфигурация репозитория

- **type**: Поддерживается "github"
- **uri**: Путь к репозиторию (например, "username/repo")
- **asset-pattern**: Регулярное выражение для выбора релизных артефактов


#### Элементы бинарников

- **name**: Имя бинарника для ссылки
- **pattern**: Регулярное выражение для выбора бинарника в артефактах
- Автоматическая фильтрация по ОС и архитектуре


#### Элементы файлов

- **pattern**: Регулярное выражение для выбора файлов
- **extract-path**: Опциональный каталог для распаковки
- Работает на любой системе (без фильтрации по ОС/архитектуре)


## Сценарии использования

### Настройка среды разработки

```bash
# Одноразовая настройка для новых разработчиков
composer install
./vendor/bin/dload get
```


### Интеграция CI/CD

```yaml
# GitHub Actions
- name: Download tools
  run: GITHUB_TOKEN=${{ secrets.GITHUB_TOKEN }} ./vendor/bin/dload get
```


### Кроссплатформенная команда

Каждый разработчик получает подходящие бинарники для своей системы:

```xml
<actions>
    <download software="rr" />        <!-- Linux-бинарник для Linux, Windows .exe для Windows -->
    <download software="temporal" />   <!-- macOS-бинарник для macOS и т.д. -->
</actions>
```


### Управление PHAR-инструментами

```xml
<actions>
    <!-- Скачивание как исполняемых .phar файлов -->
    <download software="psalm" type="phar" />
    <download software="phpstan" type="phar" />
    
    <!-- Распаковка содержимого -->
    <download software="psalm" type="archive" />  <!-- Распаковывает psalm.phar -->
</actions>
```


### Распространение фронтенд-ресурсов

```xml
<software name="ui-kit">
    <repository type="github" uri="company/ui-components" />
    <file pattern="/^dist\/.*/" extract-path="public/components" />
</software>

<actions>
    <download software="ui-kit" type="archive" />
</actions>
```


## Ограничения по API GitHub

Используйте персональный токен доступа, чтобы избежать ограничений:

```bash
GITHUB_TOKEN=your_token_here ./vendor/bin/dload get
```

Добавьте в переменные окружения CI/CD для автоматической загрузки.

## Вклад в проект

Приветствуются ваши вклады! Отправляйте Pull Requests для:

- Добавления нового ПО в предопределённый реестр
- Улучшения функционала DLoad
- Расширения документации и перевод документации [на другие языки](docs/guidelines/how-to-use-llm-for-translate-docs.md)
