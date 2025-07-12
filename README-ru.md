<div align="center">

![DLoad](./resources/logo.svg)

</div>

<p align="center">Скачивай артефакты на раз</p>

<div align="center">

[![Support](https://img.shields.io/static/v1?style=flat-square&label=Support&message=%E2%9D%A4&logo=GitHub&color=%23fe0086)](https://patreon.com/roxblnfk)

</div>

<br />

DLoad упрощает загрузку и управление бинарными артефактами в ваших проектах. Отлично подходит для dev-окружений, которым нужны специфические инструменты вроде RoadRunner, Temporal или собственные бинарники.

[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%BA%F0%9F%87%B8-moccasin?style=flat-square)](README.md)
[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![Russian readme](https://img.shields.io/badge/README-Русский%20%F0%9F%87%B7%F0%9F%87%BA-moccasin?style=flat-square)](README-ru.md)
[![Spanish readme](https://img.shields.io/badge/README-Español%20%F0%9F%87%AA%F0%9F%87%B8-moccasin?style=flat-square)](README-es.md)

## Зачем нужен DLoad?

DLoad решает распространённую проблему в PHP-проектах: как распространять и устанавливать нужные бинарные инструменты и ресурсы вместе с PHP-кодом.

С DLoad вы можете:

- Автоматически скачивать необходимые инструменты при инициализации проекта
- Обеспечить использование одинаковых версий инструментов всей командой
- Упростить онбординг через автоматизацию настройки окружения
- Управлять кроссплатформенной совместимостью без ручной настройки
- Хранить бинарники и ресурсы отдельно от системы контроля версий

### Содержание

- [Установка](#установка)
- [Быстрый старт](#быстрый-старт)
- [Использование в командной строке](#использование-в-командной-строке)
    - [Инициализация конфигурации](#инициализация-конфигурации)
    - [Загрузка ПО](#загрузка-по)
    - [Просмотр ПО](#просмотр-по)
    - [Сборка кастомного ПО](#сборка-кастомного-по)
- [Руководство по конфигурации](#руководство-по-конфигурации)
    - [Интерактивная конфигурация](#интерактивная-конфигурация)
    - [Ручная конфигурация](#ручная-конфигурация)
    - [Типы загрузки](#типы-загрузки)
    - [Ограничения версий](#ограничения-версий)
    - [Расширенные настройки](#расширенные-настройки)
- [Сборка кастомного RoadRunner](#сборка-кастомного-roadrunner)
    - [Настройка действия сборки](#настройка-действия-сборки)
    - [Атрибуты Velox-действия](#атрибуты-velox-действия)
    - [Процесс сборки](#процесс-сборки)
    - [Генерация конфигурационного файла](#генерация-конфигурационного-файла)
    - [Использование скачанного Velox](#использование-скачанного-velox)
    - [Конфигурация DLoad](#конфигурация-dload)
    - [Сборка RoadRunner](#сборка-roadrunner)
- [Пользовательский реестр ПО](#пользовательский-реестр-по)
    - [Определение ПО](#определение-по)
    - [Элементы ПО](#элементы-по)
- [Сценарии использования](#сценарии-использования)
    - [Настройка среды разработки](#настройка-среды-разработки)
    - [Настройка нового проекта](#настройка-нового-проекта)
    - [Интеграция с CI/CD](#интеграция-с-cicd)
    - [Кроссплатформенные команды](#кроссплатформенные-команды)
    - [Управление PHAR-инструментами](#управление-phar-инструментами)
    - [Распространение фронтенд-ресурсов](#распространение-фронтенд-ресурсов)
- [Ограничения GitHub API](#ограничения-github-api)
- [Участие в разработке](#участие-в-разработке)


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

Альтернативно можно скачать последний релиз с [GitHub releases](https://github.com/php-internal/dload/releases).

2. **Создайте конфигурационный файл интерактивно**:

    ```bash
    ./vendor/bin/dload init
    ```

    Эта команда проведёт вас через выбор пакетов ПО и создаст файл конфигурации `dload.xml`. Можно также создать его вручную:

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

3. **Скачайте настроенное ПО**:

    ```bash
    ./vendor/bin/dload get
    ```

4. **Интегрируйте с Composer** (опционально):

    ```json
    {
        "scripts": {
            "post-update-cmd": "dload get --no-interaction -v || \"echo can't dload binaries\""
        }
    }
    ```

## Использование в командной строке

### Инициализация конфигурации

```bash
# Создать конфигурационный файл интерактивно
./vendor/bin/dload init

# Создать конфигурацию в определённом месте
./vendor/bin/dload init --config=./custom-dload.xml

# Создать минимальную конфигурацию без запросов
./vendor/bin/dload init --no-interaction

# Перезаписать существующую конфигурацию без подтверждения
./vendor/bin/dload init --overwrite
```

### Загрузка ПО

```bash
# Загрузить из конфигурационного файла
./vendor/bin/dload get

# Загрузить конкретные пакеты
./vendor/bin/dload get rr temporal

# Загрузить с дополнительными опциями
./vendor/bin/dload get rr --stability=beta --force
```

#### Опции загрузки

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--path` | Папка для хранения бинарников | Текущая папка |
| `--arch` | Целевая архитектура (amd64, arm64) | Архитектура системы |
| `--os` | Целевая ОС (linux, darwin, windows) | Текущая ОС |
| `--stability` | Стабильность релиза (stable, beta) | stable |
| `--config` | Путь к конфигурационному файлу | ./dload.xml |
| `--force`, `-f` | Принудительная загрузка даже если бинарник уже есть | false |

### Просмотр ПО

```bash
# Показать доступные пакеты ПО
./vendor/bin/dload software

# Показать загруженное ПО
./vendor/bin/dload show

# Показать детали конкретного ПО
./vendor/bin/dload show rr

# Показать всё ПО (загруженное и доступное)
./vendor/bin/dload show --all
```

### Сборка кастомного ПО

```bash
# Собрать кастомное ПО используя конфигурационный файл
./vendor/bin/dload build

# Собрать с определённым конфигурационным файлом
./vendor/bin/dload build --config=./custom-dload.xml
```

#### Опции сборки

| Опция | Описание | По умолчанию |
|-------|----------|--------------|
| `--config` | Путь к конфигурационному файлу | ./dload.xml |

Команда `build` выполняет действия сборки, определённые в вашем конфигурационном файле, например создание кастомных бинарников RoadRunner с определёнными плагинами.
Подробную информацию о сборке кастомного RoadRunner смотрите в разделе [Сборка кастомного RoadRunner](#сборка-кастомного-roadrunner).

## Руководство по конфигурации

### Интерактивная конфигурация

Простейший способ создать конфигурационный файл — использовать интерактивную команду `init`:

```bash
./vendor/bin/dload init
```

Она:

- Проведёт вас через выбор пакетов ПО
- Покажет доступное ПО с описаниями и репозиториями
- Сгенерирует правильно отформатированный файл `dload.xml` с валидацией схемы
- Аккуратно обработает существующие конфигурационные файлы

### Ручная конфигурация

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

### Типы загрузки

DLoad поддерживает три типа загрузки, которые определяют способ обработки ресурсов:

#### Атрибут типа

```xml
<!-- Явное указание типа -->
<download software="psalm" type="phar" />        <!-- Скачать .phar без распаковки -->
<download software="frontend" type="archive" />  <!-- Принудительно извлечь архив -->
<download software="rr" type="binary" />         <!-- Обработка бинарников -->

<!-- Автоматическая обработка типа (рекомендуется) -->
<download software="rr" />           <!-- Использует все доступные обработчики -->
<download software="frontend" />     <!-- Умная обработка на основе конфига ПО -->
```

#### Поведение по умолчанию (тип не указан)

Когда `type` не указан, DLoad автоматически применяет все доступные обработчики:

- **Обработка бинарников**: Если у ПО есть секция `<binary>`, выполняет проверку наличия и версии бинарника
- **Обработка файлов**: Если у ПО есть секция `<file>` и ресурс загружен, обрабатывает файлы во время распаковки
- **Простая загрузка**: Если секций нет, загружает ресурс без распаковки

```xml
<!-- в реестре -->
<software name="complex-tool">
    <binary name="tool" pattern="/^tool-.*/" />
    <file pattern="/^config\..*/" extract-path="config" />
</software>

<!-- в действиях -->
<!-- Использует обработку и бинарников, и файлов -->
<download software="complex-tool" />
```

#### Поведение при явном указании типа

| Тип       | Поведение                                                      | Случаи использования           |
|-----------|----------------------------------------------------------------|--------------------------------|
| `binary`  | Проверка бинарника, валидация версии, права на выполнение     | CLI-инструменты, исполняемые файлы |
| `phar`    | Загружает `.phar` файлы как исполняемые **без распаковки**    | PHP-инструменты вроде Psalm, PHPStan |
| `archive` | **Принудительно распаковывает даже .phar файлы**              | Когда нужно содержимое архива  |

> [!NOTE]
> Используйте `type="phar"` для PHP-инструментов, которые должны остаться как `.phar` файлы.
> Использование `type="archive"` распакует даже `.phar` архивы.

### Ограничения версий

Используйте ограничения версий в стиле Composer:

```xml
<actions>
    <!-- Точная версия -->
    <download software="rr" version="2.12.3" />
    
    <!-- Диапазонные ограничения -->
    <download software="temporal" version="^1.20.0" />
    <download software="dolt" version="~0.50.0" />
    
    <!-- Ограничения стабильности -->
    <download software="tool" version="^1.0.0@beta" />
    
    <!-- Feature-релизы (автоматически устанавливает preview-стабильность) -->
    <download software="experimental" version="^1.0.0-experimental" />
</actions>
```

### Расширенные настройки

```xml
<dload temp-dir="./runtime">
    <actions>
        <!-- Разные пути извлечения -->
        <download software="frontend" extract-path="public/assets" />
        <download software="config" extract-path="config" />
        
        <!-- Таргетинг на разные окружения -->
        <download software="prod-tool" version="^2.0.0@stable" />
        <download software="dev-tool" version="^2.0.0@beta" />
    </actions>
</dload>
```

## Сборка кастомного RoadRunner

DLoad поддерживает сборку кастомных бинарников RoadRunner с помощью инструмента сборки Velox. Это полезно когда нужен RoadRunner с определёнными комбинациями плагинов, которые недоступны в готовых релизах.

### Настройка действия сборки

```xml
<actions>
    <!-- Базовая конфигурация с локальным velox.toml -->
    <velox config-file="./velox.toml" />
    
    <!-- С конкретными версиями -->
    <velox config-file="./velox.toml" 
          velox-version="^1.4.0" 
          golang-version="^1.22" 
          binary-version="2024.1.5" 
          binary-path="./bin/rr" />
</actions>
```

### Атрибуты Velox-действия

| Атрибут | Описание | По умолчанию |
|---------|----------|--------------|
| `velox-version` | Версия инструмента сборки Velox | Последняя |
| `golang-version` | Требуемая версия Go | Последняя |
| `binary-version` | Версия RoadRunner для отображения в `rr --version` | Последняя |
| `config-file` | Путь к локальному файлу velox.toml | `./velox.toml` |
| `binary-path` | Путь для сохранения собранного бинарника RoadRunner | `./rr` |

### Процесс сборки

DLoad автоматически управляет процессом сборки:

1. **Проверка Golang**: Проверяет что Go установлен глобально (обязательная зависимость)
2. **Подготовка Velox**: Использует Velox из глобальной установки, локальной загрузки или автоматически скачивает при необходимости
3. **Конфигурация**: Копирует ваш локальный velox.toml в папку сборки
4. **Сборка**: Выполняет команду `vx build` с указанной конфигурацией
5. **Установка**: Перемещает собранный бинарник в целевое расположение и устанавливает права на выполнение
6. **Очистка**: Удаляет временные файлы сборки

> [!NOTE]
> DLoad требует чтобы Go (Golang) был установлен глобально в вашей системе. Он не скачивает и не управляет установками Go.

### Генерация конфигурационного файла

Можно сгенерировать файл конфигурации `velox.toml` с помощью онлайн-билдера на https://build.roadrunner.dev/

Подробную документацию по опциям конфигурации Velox и примерам смотрите на https://docs.roadrunner.dev/docs/customization/build

Этот веб-интерфейс помогает выбрать плагины и генерирует подходящую конфигурацию для вашей кастомной сборки RoadRunner.

### Использование скачанного Velox

Можно скачать Velox как часть процесса сборки вместо использования глобально установленной версии:

```xml
<actions>
    <download software="velox" extract-path="bin" version="2025.1.1" />
    <velox config-file="velox.toml"
          golang-version="^1.22"
          binary-version="2024.1.5" />
</actions>
```

Это обеспечивает консистентные версии Velox в разных окружениях и между участниками команды.

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
# Собрать RoadRunner используя конфигурацию velox.toml
./vendor/bin/dload build

# Собрать с определённым конфигурационным файлом
./vendor/bin/dload build --config=custom-rr.xml
```

Собранный бинарник RoadRunner будет включать только плагины, указанные в вашем файле `velox.toml`, что уменьшает размер бинарника и улучшает производительность для вашего конкретного случая использования.

## Пользовательский реестр ПО

### Определение ПО

```xml
<dload>
    <registry overwrite="false">
        <!-- Исполняемый бинарник -->
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

        <!-- Смешанный тип: бинарники + файлы -->
        <software name="development-suite" description="Полный набор инструментов разработки">
            <repository type="github" uri="my-org/dev-tools" />
            <binary name="cli-tool" pattern="/^cli-tool.*/" />
            <file pattern="/^config\.yml$/" extract-path="config" />
            <file pattern="/^templates\/.*/" extract-path="templates" />
        </software>

        <!-- PHAR-инструменты -->
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
- **asset-pattern**: Regex-паттерн для соответствия ресурсам релиза

#### Элементы Binary

- **name**: Имя бинарника для ссылки
- **pattern**: Regex-паттерн для соответствия бинарнику в ресурсах
- Автоматически обрабатывает фильтрацию по ОС/архитектуре

#### Элементы File

- **pattern**: Regex-паттерн для соответствия файлам
- **extract-path**: Опциональная папка извлечения
- Работает на любой системе (без фильтрации по ОС/архитектуре)

## Сценарии использования

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

### Интеграция с CI/CD

```yaml
# GitHub Actions
- name: Download tools
  run: GITHUB_TOKEN=${{ secrets.GITHUB_TOKEN }} ./vendor/bin/dload get
```

### Кроссплатформенные команды

Каждый разработчик получает правильные бинарники для своей системы:

```xml
<actions>
    <download software="rr" />        <!-- Linux-бинарник для Linux, Windows .exe для Windows -->
    <download software="temporal" />   <!-- macOS-бинарник для macOS и т.д. -->
</actions>
```

### Управление PHAR-инструментами

```xml
<actions>
    <!-- Скачать как исполняемые .phar файлы -->
    <download software="psalm" type="phar" />
    <download software="phpstan" type="phar" />
    
    <!-- Извлечь содержимое -->
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

## Ограничения GitHub API

Используйте персональный токен доступа чтобы избежать ограничений:

```bash
GITHUB_TOKEN=your_token_here ./vendor/bin/dload get
```

Добавьте в переменные окружения CI/CD для автоматических загрузок.

## Участие в разработке

Участие приветствуется! Отправляйте Pull Request'ы для:

- Добавления нового ПО в предопределённый реестр
- Улучшения функциональности DLoad  
- Улучшения документации и перевода на [другие языки](docs/guidelines/how-to-translate-readme-docs.md)
