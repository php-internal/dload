<div align="center">

![DLoad](./resources/logo.svg)

</div>

<p align="center">Легкая загрузка артефактов</p>

<div align="center">

[![Support](https://img.shields.io/static/v1?style=flat-square&label=Support&message=%E2%9D%A4&logo=GitHub&color=%23fe0086)](https://patreon.com/roxblnfk)

</div>

<br />

DLoad упрощает загрузку и управление бинарными артефактами для ваших проектов. Идеально подходит для сред разработки, которые требуют специфических инструментов, таких как RoadRunner, Temporal или пользовательские бинарные файлы.

[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%BA%F0%9F%87%B8-moccasin?style=flat-square)](README.md)
[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![Russian readme](https://img.shields.io/badge/README-Русский%20%F0%9F%87%B7%F0%9F%87%BA-moccasin?style=flat-square)](README-ru.md)
[![Spanish readme](https://img.shields.io/badge/README-Español%20%F0%9F%87%AA%F0%9F%87%B8-moccasin?style=flat-square)](README-es.md)

## Почему DLoad?

DLoad решает общую проблему в PHP-проектах: как распространять и устанавливать необходимые бинарные инструменты и ресурсы вместе с PHP-кодом.
С DLoad вы можете:

- Автоматически загружать необходимые инструменты во время инициализации проекта
- Обеспечить использование одинаковых версий инструментов всеми участниками команды
- Упростить адаптацию через автоматизацию настройки окружения
- Управлять кроссплатформенной совместимостью без ручной конфигурации
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

1. **Установите DLoad через Composer**:

    ```bash
    composer require internal/dload -W
    ```

2. **Создайте файл конфигурации интерактивно**:

    ```bash
    ./vendor/bin/dload init
    ```

    Эта команда проведет вас через выбор пакетов ПО и создаст файл конфигурации `dload.xml`. Вы также можете создать его вручную:

    ```xml
    <?xml version="1.0"?>
    <dload xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
           xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/php-internal/dload/refs/heads/1.x/dload.xsd">
       <actions>
           <download software="rr" version="^2025.1.0"/>
           <download software="temporal" version="^1.3"/>
       </actions>
    </dload>
    ```

3. **Загрузите настроенное ПО**:

    ```bash
    ./vendor/bin/dload get
    ```

4. **Интеграция с Composer** (опционально):

    ```json
    {
        "scripts": {
            "post-update-cmd": "dload get --no-interaction -v || echo can't dload binaries"
        }
    }
    ```

## Использование командной строки

### Инициализация конфигурации

```bash
# Создать файл конфигурации интерактивно
./vendor/bin/dload init

# Создать конфигурацию в определенном месте
./vendor/bin/dload init --config=./custom-dload.xml

# Создать минимальную конфигурацию без подсказок
./vendor/bin/dload init --no-interaction

# Перезаписать существующую конфигурацию без подтверждения
./vendor/bin/dload init --overwrite
```

### Загрузка ПО

```bash
# Загрузить из файла конфигурации
./vendor/bin/dload get

# Загрузить определенные пакеты
./vendor/bin/dload get rr temporal

# Загрузить с опциями
./vendor/bin/dload get rr --stability=beta --force
```

#### Опции загрузки

| Опция | Описание | По умолчанию |
|--------|-------------|---------|
| `--path` | Директория для хранения бинарных файлов | Текущая директория |
| `--arch` | Целевая архитектура (amd64, arm64) | Архитектура системы |
| `--os` | Целевая ОС (linux, darwin, windows) | Текущая ОС |
| `--stability` | Стабильность релиза (stable, beta) | stable |
| `--config` | Путь к файлу конфигурации | ./dload.xml |
| `--force`, `-f` | Принудительная загрузка даже если бинарный файл существует | false |

### Просмотр ПО

```bash
# Вывести список доступных пакетов ПО
./vendor/bin/dload software

# Показать загруженное ПО
./vendor/bin/dload show

# Показать детали определенного ПО
./vendor/bin/dload show rr

# Показать все ПО (загруженное и доступное)
./vendor/bin/dload show --all
```

## Руководство по конфигурации

### Интерактивная конфигурация

Самый простой способ создать файл конфигурации - использовать интерактивную команду `init`:

```bash
./vendor/bin/dload init
```

Это:

- Проведет вас через выбор пакетов ПО
- Покажет доступное ПО с описаниями и репозиториями
- Создаст правильно отформатированный файл `dload.xml` с валидацией схемы
- Корректно обработает существующие файлы конфигурации

### Ручная конфигурация

Создайте `dload.xml` в корне вашего проекта:

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

### Типы загрузки

DLoad поддерживает три типа загрузки, которые определяют, как обрабатываются ресурсы:

#### Атрибут типа

```xml
<!-- Явное указание типа -->
<download software="psalm" type="phar" />        <!-- Загрузить .phar без распаковки -->
<download software="frontend" type="archive" />  <!-- Принудительное извлечение архива -->
<download software="rr" type="binary" />         <!-- Специфичная обработка бинарных файлов -->

<!-- Автоматическая обработка типа (рекомендуется) -->
<download software="rr" />           <!-- Использует все доступные обработчики -->
<download software="frontend" />     <!-- Умная обработка на основе конфигурации ПО -->
```

#### Поведение по умолчанию (тип не указан)

Когда `type` не указан, DLoad автоматически использует все доступные обработчики:

- **Обработка бинарных файлов**: Если у ПО есть секция `<binary>`, выполняет проверку наличия и версии бинарного файла
- **Обработка файлов**: Если у ПО есть секция `<file>` и ресурс загружен, обрабатывает файлы во время распаковки
- **Простая загрузка**: Если секций нет, загружает ресурс без распаковки

```xml
<!-- список реестра -->
<software name="complex-tool">
    <binary name="tool" pattern="/^tool-.*/" />
    <file pattern="/^config\..*/" extract-path="config" />
</software>

<!-- список действий -->
<!-- Использует обработку как бинарных файлов, так и файлов -->
<download software="complex-tool" />
```

#### Поведение явных типов

| Тип      | Поведение                                                     | Случай использования                       |
|-----------|--------------------------------------------------------------|--------------------------------|
| `binary`  | Проверка бинарных файлов, валидация версии, права на выполнение  | CLI инструменты, исполняемые файлы         |
| `phar`    | Загружает `.phar` файлы как исполняемые **без распаковки** | PHP инструменты как Psalm, PHPStan  |
| `archive` | **Принудительная распаковка даже для .phar файлов**                    | Когда нужно содержимое архива |

> [!NOTE]
> Используйте `type="phar"` для PHP инструментов, которые должны остаться `.phar` файлами.
> Использование `type="archive"` распакует даже `.phar` архивы.

### Ограничения версий

Используйте ограничения версий в стиле Composer:

```xml
<actions>
    <!-- Точная версия -->
    <download software="rr" version="2.12.3" />
    
    <!-- Ограничения диапазона -->
    <download software="temporal" version="^1.20.0" />
    <download software="dolt" version="~0.50.0" />
    
    <!-- Ограничения стабильности -->
    <download software="tool" version="^1.0.0@beta" />
    
    <!-- Функциональные релизы (автоматически устанавливают предварительную стабильность) -->
    <download software="experimental" version="^1.0.0-experimental" />
</actions>
```

### Расширенные опции конфигурации

```xml
<dload temp-dir="./runtime">
    <actions>
        <!-- Различные пути извлечения -->
        <download software="frontend" extract-path="public/assets" />
        <download software="config" extract-path="config" />
        
        <!-- Целевые разные окружения -->
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
        <software name="frontend" description="Фронтенд ресурсы">
            <repository type="github" uri="my-org/frontend" asset-pattern="/^artifacts.*/" />
            <file pattern="/^.*\.js$/" />
            <file pattern="/^.*\.css$/" />
        </software>

        <!-- Смешанный: бинарные файлы + файлы -->
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

- **type**: В настоящее время поддерживает "github"
- **uri**: Путь репозитория (например, "username/repo")
- **asset-pattern**: Шаблон регулярного выражения для сопоставления ресурсов релиза

#### Элементы бинарных файлов

- **name**: Имя бинарного файла для ссылки
- **pattern**: Шаблон регулярного выражения для сопоставления бинарного файла в ресурсах
- Автоматически обрабатывает фильтрацию по ОС/архитектуре

#### Элементы файлов

- **pattern**: Шаблон регулярного выражения для сопоставления файлов
- **extract-path**: Необязательная директория извлечения
- Работает на любой системе (без фильтрации по ОС/архитектуре)

## Случаи использования

### Настройка среды разработки

```bash
# Одноразовая настройка для новых разработчиков
composer install
./vendor/bin/dload init  # Только в первый раз
./vendor/bin/dload get
```

### Настройка нового проекта

```bash
# Начать новый проект с DLoad
composer init
composer require internal/dload -W
./vendor/bin/dload init
./vendor/bin/dload get
```

### Интеграция CI/CD

```yaml
# GitHub Actions
- name: Download tools
  run: GITHUB_TOKEN=${{ secrets.GITHUB_TOKEN }} ./vendor/bin/dload get
```

### Кроссплатформенные команды

Каждый разработчик получает правильные бинарные файлы для своей системы:

```xml
<actions>
    <download software="rr" />        <!-- Linux бинарный файл для Linux, Windows .exe для Windows -->
    <download software="temporal" />   <!-- macOS бинарный файл для macOS и т.д. -->
</actions>
```

### Управление PHAR инструментами

```xml
<actions>
    <!-- Загрузить как исполняемые .phar файлы -->
    <download software="psalm" type="phar" />
    <download software="phpstan" type="phar" />
    
    <!-- Извлечь содержимое вместо этого -->
    <download software="psalm" type="archive" />  <!-- Распаковывает psalm.phar -->
</actions>
```

### Распространение фронтенд ресурсов

```xml
<software name="ui-kit">
    <repository type="github" uri="company/ui-components" />
    <file pattern="/^dist\/.*/" extract-path="public/components" />
</software>

<actions>
    <download software="ui-kit" type="archive" />
</actions>
```

## Ограничения API GitHub

Используйте персональный токен доступа, чтобы избежать ограничений скорости:

```bash
GITHUB_TOKEN=your_token_here ./vendor/bin/dload get
```

Добавьте в переменные окружения CI/CD для автоматических загрузок.

## Вклад в проект

Вклады приветствуются! Отправляйте Pull Request для:

- Добавления нового ПО в предопределенный реестр
- Улучшения функциональности DLoad
- Улучшения документации и ее перевода на [другие языки](docs/guidelines/how-to-translate-readme-docs.md)
