<div align="center">

![DLoad](./resources/logo.svg)

</div>

<p align="center">Descarga artefactos fácilmente</p>

<div align="center">

[![Support](https://img.shields.io/static/v1?style=flat-square&label=Support&message=%E2%9D%A4&logo=GitHub&color=%23fe0086)](https://patreon.com/roxblnfk)

</div>

<br />

DLoad simplifica la descarga y gestión de artefactos binarios para tus proyectos. Es perfecto para entornos de desarrollo que necesitan herramientas específicas como RoadRunner, Temporal o binarios personalizados.

[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%BA%F0%9F%87%B8-moccasin?style=flat-square)](README.md)
[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![Russian readme](https://img.shields.io/badge/README-Русский%20%F0%9F%87%B7%F0%9F%87%BA-moccasin?style=flat-square)](README-ru.md)
[![Spanish readme](https://img.shields.io/badge/README-Español%20%F0%9F%87%AA%F0%9F%87%B8-moccasin?style=flat-square)](README-es.md)

## ¿Por qué DLoad?

DLoad resuelve un problema común en proyectos PHP: cómo distribuir e instalar herramientas binarias y recursos necesarios junto con tu código PHP.
Con DLoad puedes:

- Descargar automáticamente las herramientas que necesitas durante la configuración inicial del proyecto
- Asegurar que todo el equipo use exactamente las mismas versiones de las herramientas
- Simplificar la incorporación de nuevos desarrolladores automatizando la configuración del entorno
- Manejar compatibilidad multiplataforma sin configuración manual
- Mantener binarios y recursos fuera de tu control de versiones

### Tabla de Contenidos

- [Instalación](#instalación)
- [Inicio Rápido](#inicio-rápido)
- [Uso desde Línea de Comandos](#uso-desde-línea-de-comandos)
    - [Inicializar Configuración](#inicializar-configuración)
    - [Descargar Software](#descargar-software)
    - [Ver Software](#ver-software)
    - [Construir Software Personalizado](#construir-software-personalizado)
- [Guía de Configuración](#guía-de-configuración)
    - [Configuración Interactiva](#configuración-interactiva)
    - [Configuración Manual](#configuración-manual)
    - [Tipos de Descarga](#tipos-de-descarga)
    - [Restricciones de Versión](#restricciones-de-versión)
    - [Opciones de Configuración Avanzadas](#opciones-de-configuración-avanzadas)
- [Construir RoadRunner Personalizado](#construir-roadrunner-personalizado)
    - [Configuración de Acción de Construcción](#configuración-de-acción-de-construcción)
    - [Atributos de Acción Velox](#atributos-de-acción-velox)
    - [Proceso de Construcción](#proceso-de-construcción)
    - [Generación de Archivo de Configuración](#generación-de-archivo-de-configuración)
    - [Usar Velox Descargado](#usar-velox-descargado)
    - [Configuración DLoad](#configuración-dload)
    - [Construir RoadRunner](#construir-roadrunner)
- [Registro de Software Personalizado](#registro-de-software-personalizado)
    - [Definir Software](#definir-software)
    - [Elementos de Software](#elementos-de-software)
- [Casos de Uso](#casos-de-uso)
    - [Configurar Entorno de Desarrollo](#configurar-entorno-de-desarrollo)
    - [Configurar Nuevo Proyecto](#configurar-nuevo-proyecto)
    - [Integración CI/CD](#integración-cicd)
    - [Equipos Multiplataforma](#equipos-multiplataforma)
    - [Gestión de Herramientas PHAR](#gestión-de-herramientas-phar)
    - [Distribución de Assets Frontend](#distribución-de-assets-frontend)
- [Límites de Rate de la API de GitHub](#límites-de-rate-de-la-api-de-github)
- [Contribuir](#contribuir)


## Instalación

```bash
composer require internal/dload -W
```

[![PHP](https://img.shields.io/packagist/php-v/internal/dload.svg?style=flat-square&logo=php)](https://packagist.org/packages/internal/dload)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/internal/dload.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/internal/dload)
[![License](https://img.shields.io/packagist/l/internal/dload.svg?style=flat-square)](LICENSE.md)
[![Total DLoads](https://img.shields.io/packagist/dt/internal/dload.svg?style=flat-square)](https://packagist.org/packages/internal/dload/stats)

## Inicio Rápido

1. **Instala DLoad usando Composer**:

    ```bash
    composer require internal/dload -W
    ```

También puedes descargar la versión más reciente desde [GitHub releases](https://github.com/php-internal/dload/releases).

2. **Crea tu archivo de configuración de forma interactiva**:

    ```bash
    ./vendor/bin/dload init
    ```

    Este comando te ayudará a seleccionar paquetes de software y creará un archivo de configuración `dload.xml`. También puedes crearlo manualmente:

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

3. **Descarga el software configurado**:

    ```bash
    ./vendor/bin/dload get
    ```

4. **Integra con Composer** (opcional):

    ```json
    {
        "scripts": {
            "post-update-cmd": "dload get --no-interaction -v || \"echo can't dload binaries\""
        }
    }
    ```

## Uso desde Línea de Comandos

### Inicializar Configuración

```bash
# Crear archivo de configuración de forma interactiva
./vendor/bin/dload init

# Crear configuración en una ubicación específica
./vendor/bin/dload init --config=./custom-dload.xml

# Crear configuración mínima sin preguntas
./vendor/bin/dload init --no-interaction

# Sobrescribir configuración existente sin confirmación
./vendor/bin/dload init --overwrite
```

### Descargar Software

```bash
# Descargar usando el archivo de configuración
./vendor/bin/dload get

# Descargar paquetes específicos
./vendor/bin/dload get rr temporal

# Descargar con opciones adicionales
./vendor/bin/dload get rr --stability=beta --force
```

#### Opciones de Descarga

| Opción | Descripción | Valor por defecto |
|--------|-------------|-------------------|
| `--path` | Directorio donde guardar los binarios | Directorio actual |
| `--arch` | Arquitectura de destino (amd64, arm64) | Arquitectura del sistema |
| `--os` | Sistema operativo de destino (linux, darwin, windows) | SO actual |
| `--stability` | Estabilidad del release (stable, beta) | stable |
| `--config` | Ruta al archivo de configuración | ./dload.xml |
| `--force`, `-f` | Forzar descarga aunque el binario ya exista | false |

### Ver Software

```bash
# Listar paquetes de software disponibles
./vendor/bin/dload software

# Mostrar software descargado
./vendor/bin/dload show

# Mostrar detalles de software específico
./vendor/bin/dload show rr

# Mostrar todo el software (descargado y disponible)
./vendor/bin/dload show --all
```

### Construir Software Personalizado

```bash
# Construir software personalizado usando el archivo de configuración
./vendor/bin/dload build

# Construir con un archivo de configuración específico
./vendor/bin/dload build --config=./custom-dload.xml
```

#### Opciones de Construcción

| Opción | Descripción | Valor por defecto |
|--------|-------------|-------------------|
| `--config` | Ruta al archivo de configuración | ./dload.xml |

El comando `build` ejecuta las acciones de construcción definidas en tu archivo de configuración, como crear binarios personalizados de RoadRunner con plugins específicos.
Para información detallada sobre cómo construir RoadRunner personalizado, consulta la sección [Construir RoadRunner Personalizado](#construir-roadrunner-personalizado).

## Guía de Configuración

### Configuración Interactiva

La forma más sencilla de crear un archivo de configuración es usando el comando interactivo `init`:

```bash
./vendor/bin/dload init
```

Esto hará lo siguiente:

- Te guiará en la selección de paquetes de software
- Mostrará software disponible con descripciones y repositorios
- Generará un archivo `dload.xml` bien formateado con validación de esquema
- Manejará archivos de configuración existentes de manera elegante

### Configuración Manual

Crea `dload.xml` en la raíz de tu proyecto:

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

### Tipos de Descarga

DLoad soporta tres tipos de descarga que determinan cómo se procesan los assets:

#### Atributo Type

```xml
<!-- Especificación explícita de tipo -->
<download software="psalm" type="phar" />        <!-- Descargar .phar sin extraer -->
<download software="frontend" type="archive" />  <!-- Forzar extracción de archivo -->
<download software="rr" type="binary" />         <!-- Procesamiento específico para binarios -->

<!-- Manejo automático de tipo (recomendado) -->
<download software="rr" />           <!-- Usa todos los manejadores disponibles -->
<download software="frontend" />     <!-- Procesamiento inteligente basado en la config del software -->
```

#### Comportamiento por Defecto (Sin Especificar Type)

Cuando no se especifica `type`, DLoad automáticamente usa todos los manejadores disponibles:

- **Procesamiento de binarios**: Si el software tiene una sección `<binary>`, verifica la presencia y versión del binario
- **Procesamiento de archivos**: Si el software tiene una sección `<file>` y el asset se descarga, procesa los archivos durante la extracción
- **Descarga simple**: Si no hay secciones, descarga el asset sin extraer

```xml
<!-- lista del registro -->
<software name="complex-tool">
    <binary name="tool" pattern="/^tool-.*/" />
    <file pattern="/^config\..*/" extract-path="config" />
</software>

<!-- lista de acciones -->
<!-- Usa tanto procesamiento de binarios como de archivos -->
<download software="complex-tool" />
```

#### Comportamientos de Tipos Explícitos

| Tipo      | Comportamiento                                                     | Caso de Uso                       |
|-----------|-------------------------------------------------------------------|----------------------------------|
| `binary`  | Verificación de binarios, validación de versión, permisos de ejecución  | Herramientas CLI, ejecutables         |
| `phar`    | Descarga archivos `.phar` como ejecutables **sin extraer** | Herramientas PHP como Psalm, PHPStan  |
| `archive` | **Fuerza la extracción incluso para archivos .phar**                    | Cuando necesitas el contenido del archivo |

> [!NOTE]
> Usa `type="phar"` para herramientas PHP que deben mantenerse como archivos `.phar`.
> Usar `type="archive"` extraerá incluso archivos `.phar`.

### Restricciones de Versión

Usa restricciones de versión estilo Composer:

```xml
<actions>
    <!-- Versión exacta -->
    <download software="rr" version="2.12.3" />
    
    <!-- Restricciones de rango -->
    <download software="temporal" version="^1.20.0" />
    <download software="dolt" version="~0.50.0" />
    
    <!-- Restricciones de estabilidad -->
    <download software="tool" version="^1.0.0@beta" />
    
    <!-- Releases experimentales (automáticamente establece estabilidad preview) -->
    <download software="experimental" version="^1.0.0-experimental" />
</actions>
```

### Opciones de Configuración Avanzadas

```xml
<dload temp-dir="./runtime">
    <actions>
        <!-- Diferentes rutas de extracción -->
        <download software="frontend" extract-path="public/assets" />
        <download software="config" extract-path="config" />
        
        <!-- Apuntar a diferentes entornos -->
        <download software="prod-tool" version="^2.0.0@stable" />
        <download software="dev-tool" version="^2.0.0@beta" />
    </actions>
</dload>
```

## Construir RoadRunner Personalizado

DLoad soporta la construcción de binarios personalizados de RoadRunner usando la herramienta Velox. Esto es útil cuando necesitas RoadRunner con combinaciones específicas de plugins que no están disponibles en las versiones pre-construidas.

### Configuración de Acción de Construcción

```xml
<actions>
    <!-- Configuración básica usando velox.toml local -->
    <velox config-file="./velox.toml" />
    
    <!-- Con versiones específicas -->
    <velox config-file="./velox.toml" 
          velox-version="^1.4.0" 
          golang-version="^1.22" 
          binary-version="2024.1.5" 
          binary-path="./bin/rr" />
</actions>
```

### Atributos de Acción Velox

| Atributo | Descripción | Valor por defecto |
|-----------|-------------|-------------------|
| `velox-version` | Versión de la herramienta Velox | Última |
| `golang-version` | Versión requerida de Go | Última |
| `binary-version` | Versión de RoadRunner para mostrar en `rr --version` | Última |
| `config-file` | Ruta al archivo velox.toml local | `./velox.toml` |
| `binary-path` | Ruta donde guardar el binario construido de RoadRunner | `./rr` |

### Proceso de Construcción

DLoad maneja automáticamente todo el proceso de construcción:

1. **Verificación de Golang**: Verifica que Go esté instalado globalmente (dependencia requerida)
2. **Preparación de Velox**: Usa Velox desde instalación global, descarga local, o lo descarga automáticamente si es necesario
3. **Configuración**: Copia tu archivo velox.toml local al directorio de construcción
4. **Construcción**: Ejecuta el comando `vx build` con la configuración especificada
5. **Instalación**: Mueve el binario construido a la ubicación de destino y establece permisos de ejecución
6. **Limpieza**: Elimina archivos temporales de construcción

> [!NOTE]
> DLoad requiere que Go (Golang) esté instalado globalmente en tu sistema. No descarga ni gestiona instalaciones de Go.

### Generación de Archivo de Configuración

Puedes generar un archivo de configuración `velox.toml` usando el constructor online en https://build.roadrunner.dev/

Para documentación detallada sobre opciones de configuración de Velox y ejemplos, visita https://docs.roadrunner.dev/docs/customization/build

Esta interfaz web te ayuda a seleccionar plugins y genera la configuración apropiada para tu build personalizado de RoadRunner.

### Usar Velox Descargado

Puedes descargar Velox como parte de tu proceso de construcción en lugar de depender de una versión instalada globalmente:

```xml
<actions>
    <download software="velox" extract-path="bin" version="2025.1.1" />
    <velox config-file="velox.toml"
          golang-version="^1.22"
          binary-version="2024.1.5" />
</actions>
```

Esto asegura versiones consistentes de Velox entre diferentes entornos y miembros del equipo.

### Configuración DLoad

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

### Construir RoadRunner

```bash
# Construir RoadRunner usando la configuración velox.toml
./vendor/bin/dload build

# Construir con un archivo de configuración específico
./vendor/bin/dload build --config=custom-rr.xml
```

El binario de RoadRunner construido incluirá solo los plugins especificados en tu archivo `velox.toml`, reduciendo el tamaño del binario y mejorando el rendimiento para tu caso de uso específico.

## Registro de Software Personalizado

### Definir Software

```xml
<dload>
    <registry overwrite="false">
        <!-- Ejecutable binario -->
        <software name="RoadRunner" alias="rr" 
                  homepage="https://roadrunner.dev"
                  description="Servidor de aplicaciones de alto rendimiento">
            <repository type="github" uri="roadrunner-server/roadrunner" asset-pattern="/^roadrunner-.*/" />
            <binary name="rr" pattern="/^roadrunner-.*/" />
        </software>

        <!-- Archive con archivos -->
        <software name="frontend" description="Assets de frontend">
            <repository type="github" uri="my-org/frontend" asset-pattern="/^artifacts.*/" />
            <file pattern="/^.*\.js$/" />
            <file pattern="/^.*\.css$/" />
        </software>

        <!-- Mixto: binarios + archivos -->
        <software name="development-suite" description="Suite completa de herramientas de desarrollo">
            <repository type="github" uri="my-org/dev-tools" />
            <binary name="cli-tool" pattern="/^cli-tool.*/" />
            <file pattern="/^config\.yml$/" extract-path="config" />
            <file pattern="/^templates\/.*/" extract-path="templates" />
        </software>

        <!-- Herramientas PHAR -->
        <software name="psalm" description="Herramienta de análisis estático">
            <repository type="github" uri="vimeo/psalm" />
            <binary name="psalm.phar" pattern="/^psalm\.phar$/" />
        </software>
    </registry>
</dload>
```

### Elementos de Software

#### Configuración de Repository

- **type**: Actualmente soporta "github"
- **uri**: Ruta del repositorio (ej., "username/repo")
- **asset-pattern**: Patrón regex para hacer match con assets de release

#### Elementos Binary

- **name**: Nombre del binario para referencia
- **pattern**: Patrón regex para hacer match con el binario en los assets
- Maneja automáticamente el filtrado por SO/arquitectura

#### Elementos File

- **pattern**: Patrón regex para hacer match con archivos
- **extract-path**: Directorio de extracción opcional
- Funciona en cualquier sistema (sin filtrado por SO/arquitectura)

## Casos de Uso

### Configurar Entorno de Desarrollo

```bash
# Configuración única para nuevos desarrolladores
composer install
./vendor/bin/dload init  # Solo la primera vez
./vendor/bin/dload get
```

### Configurar Nuevo Proyecto

```bash
# Empezar un nuevo proyecto con DLoad
composer init
composer require internal/dload -W
./vendor/bin/dload init
./vendor/bin/dload get
```

### Integración CI/CD

```yaml
# GitHub Actions
- name: Download tools
  run: GITHUB_TOKEN=${{ secrets.GITHUB_TOKEN }} ./vendor/bin/dload get
```

### Equipos Multiplataforma

Cada desarrollador obtiene los binarios correctos para su sistema:

```xml
<actions>
    <download software="rr" />        <!-- Binario Linux para Linux, .exe de Windows para Windows -->
    <download software="temporal" />   <!-- Binario macOS para macOS, etc. -->
</actions>
```

### Gestión de Herramientas PHAR

```xml
<actions>
    <!-- Descargar como archivos .phar ejecutables -->
    <download software="psalm" type="phar" />
    <download software="phpstan" type="phar" />
    
    <!-- Extraer contenido en su lugar -->
    <download software="psalm" type="archive" />  <!-- Extrae psalm.phar -->
</actions>
```

### Distribución de Assets Frontend

```xml
<software name="ui-kit">
    <repository type="github" uri="company/ui-components" />
    <file pattern="/^dist\/.*/" extract-path="public/components" />
</software>

<actions>
    <download software="ui-kit" type="archive" />
</actions>
```

## Límites de Rate de la API de GitHub

Usa un token de acceso personal para evitar límites de rate:

```bash
GITHUB_TOKEN=your_token_here ./vendor/bin/dload get
```

Agrégalo a las variables de entorno CI/CD para descargas automatizadas.

## Contribuir

¡Las contribuciones son bienvenidas! Envía Pull Requests para:

- Agregar nuevo software al registro predefinido
- Mejorar la funcionalidad de DLoad  
- Mejorar la documentación y traducirla a [otros idiomas](docs/guidelines/how-to-translate-readme-docs.md)
