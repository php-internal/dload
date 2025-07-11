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

### Содержание

- [Установка](#установка)
- [Быстрый старт](#быстрый-старт)
- [Использование командной строки](#использование-командной-строки)
    - [Инициализация конфигурации](#инициализация-конфигурации)
    - [Загрузка ПО](#загрузка-по)
    - [Просмотр ПО](#просмотр-по)
    - [Сборка пользовательского ПО](#сборка-пользовательского-по)
- [Руководство по конфигурации](#руководство-по-конфигурации)
    - [Интерактивная конфигурация](#интерактивная-конфигурация)
    - [Ручная конфигурация](#ручная-конфигурация)
    - [Типы загрузки](#типы-загрузки)
    - [Ограничения версий](#ограничения-версий)
    - [Расширенные опции конфигурации](#расширенные-опции-конфигурации)
- [Сборка пользовательского RoadRunner](#сборка-пользовательского-roadrunner)
    - [Конфигурация действия сборки](#конфигурация-действия-сборки)
    - [Атрибуты действия Velox](#атрибуты-действия-velox)
    - [Процесс сборки](#процесс-сборки)
    - [Генерация файла конфигурации](#генерация-файла-конфигурации)
    - [Использование загруженного Velox](#использование-загруженного-velox)
    - [Конфигурация DLoad](#конфигурация-dload)
    - [Сборка RoadRunner](#сборка-roadrunner)
- [Пользовательский реестр ПО](#пользовательский-реестр-по)
    - [Определение ПО](#определение-по)
    - [Элементы ПО](#элементы-по)
- [Случаи использования](#случаи-использования)
    - [Настройка среды разработки](#настройка-среды-разработки)
    - [Настройка нового проекта](#настройка-нового-проекта)
    - [Интеграция CI/CD](#интеграция-cicd)
    - [Кроссплатформенные команды](#кроссплатформенные-команды)
    - [Управление PHAR инструментами](#управление-phar-инструментами)
    - [Распространение фронтенд ресурсов](#распространение-фронтенд-ресурсов)
- [Ограничения API GitHub](#ограничения-api-github)
- [Вклад в проект](#вклад-в-проект)


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

Альтернативно, вы можете скачать последний релиз с [GitHub releases](https://github.com/php-internal/dload/releases).

2. **Создайте файл конфигурации интерактивно**:

    ```bash
    ./vendor/bin/dload init
    ```

    Эта команда проведет вас через выбор пакетов ПО и создаст файл конфигурации `dload.xml`. Вы также можете создать его вручную:

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

3. **Загрузите настроенное ПО**:

    ```bash
    ./vendor/bin/dload get
    ```

4. **Интеграция с Composer** (опционально):

    ```json
    {
        "scripts": {
            "post-update-cmd": "dload get --no-interaction -v || \"echo can't dload binaries\""
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

### Сборка пользовательского ПО

```bash
# Собрать пользовательское ПО, используя файл конфигурации
./vendor/bin/dload build

# Собрать с определенным файлом конфигурации
./vendor/bin/dload build --config=./custom-dload.xml
```

#### Опции сборки

| Опция | Описание | По умолчанию |
|--------|-------------|---------|
| `--config` | Путь к файлу конфигурации | ./dload.xml |

Команда `build` выполняет действия сборки, определенные в вашем файле конфигурации, такие как создание пользовательских бинарных файлов RoadRunner с определенными плагинами.
Для подробной информации о сборке пользовательского RoadRunner смотрите раздел [Сборка пользовательского RoadRunner](#сборка-пользовательского-roadrunner).

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

## Сборка пользовательского RoadRunner

DLoad поддерживает сборку пользовательских бинарных файлов RoadRunner с использованием инструмента сборки Velox. Это полезно, когда вам нужен RoadRunner с пользовательскими комбинациями плагинов, которые недоступны в готовых релизах.

### Конфигурация действия сборки

```xml
<actions>
    <!-- Базовая конфигурация с использованием локального velox.toml -->
    <velox config-file="./velox.toml" />
    
    <!-- С определенными версиями -->
    <velox config-file="./velox.toml" 
          velox-version="^1.4.0" 
          golang-version="^1.22" 
          binary-version="2024.1.5" 
          binary-path="./bin/rr" />
</actions>
```

### Атрибуты действия Velox

| Атрибут | Описание | По умолчанию |
|-----------|-------------|---------|
| `velox-version` | Версия инструмента сборки Velox | Последняя |
| `golang-version` | Требуемая версия Go | Последняя |
| `binary-version` | Версия RoadRunner для отображения в `rr --version` | Последняя |
| `config-file` | Путь к локальному файлу velox.toml | `./velox.toml` |
| `binary-path` | Путь для сохранения собранного бинарного файла RoadRunner | `./rr` |

### Процесс сборки

DLoad автоматически обрабатывает процесс сборки:

1. **Проверка Golang**: Проверяет, что Go установлен глобально (обязательная зависимость)
2. **Подготовка Velox**: Использует Velox из глобальной установки, локальной загрузки или автоматически загружает при необходимости
3. **Конфигурация**: Копирует ваш локальный velox.toml в директорию сборки
4. **Сборка**: Выполняет команду `vx build` с указанной конфигурацией
5. **Установка**: Перемещает собранный бинарный файл в целевое место и устанавливает права на выполнение
6. **Очистка**: Удаляет временные файлы сборки

> [!NOTE]
> DLoad требует, чтобы Go (Golang) был установлен глобально в вашей системе. Он не загружает и не управляет установками Go.

### Генерация файла конфигурации

Вы можете сгенерировать файл конфигурации `velox.toml`, используя онлайн-конструктор на https://build.roadrunner.dev/

Для подробной документации по опциям конфигурации Velox и примерам посетите https://docs.roadrunner.dev/docs/customization/build

Этот веб-интерфейс помогает выбрать плагины и создает соответствующую конфигурацию для вашей пользовательской сборки RoadRunner.

### Использование загруженного Velox

Вы можете загрузить Velox как часть процесса сборки вместо использования глобально установленной версии:

```xml
<actions>
    <download software="velox" extract-path="bin" version="2025.1.1" />
    <velox config-file="velox.toml"
          golang-version="^1.22"
          binary-version="2024.1.5" />
</actions>
```

Это обеспечивает согласованные версии Velox в разных окружениях и среди участников команды.

### Конфигурация DLoad

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

### Сборка RoadRunner

```bash
# Собрать RoadRunner, используя конфигурацию velox.toml
./vendor/bin/dload build

# Собрать с определенным файлом конфигурации
./vendor/bin/dload build --config=custom-rr.xml
```

Собранный бинарный файл RoadRunner будет включать только плагины, указанные в вашем файле `velox.toml`, уменьшая размер бинарного файла и улучшая производительность для вашего конкретного случая использования.

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
