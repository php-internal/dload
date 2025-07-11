<div align="center">

![DLoad](./resources/logo.svg)

</div>

<p align="center">Descarga artefactos fácilmente</p>

<div align="center">

[![Support](https://img.shields.io/static/v1?style=flat-square&label=Support&message=%E2%9D%A4&logo=GitHub&color=%23fe0086)](https://patreon.com/roxblnfk)

</div>

<br />

DLoad simplifica la descarga y gestión de artefactos binarios para tus proyectos. Perfecto para entornos de desarrollo que requieren herramientas específicas como RoadRunner, Temporal o binarios personalizados.

[![English readme](https://img.shields.io/badge/README-English%20%F0%9F%87%BA%F0%9F%87%B8-moccasin?style=flat-square)](README.md)
[![Chinese readme](https://img.shields.io/badge/README-%E4%B8%AD%E6%96%87%20%F0%9F%87%A8%F0%9F%87%B3-moccasin?style=flat-square)](README-zh.md)
[![Russian readme](https://img.shields.io/badge/README-Русский%20%F0%9F%87%B7%F0%9F%87%BA-moccasin?style=flat-square)](README-ru.md)
[![Spanish readme](https://img.shields.io/badge/README-Español%20%F0%9F%87%AA%F0%9F%87%B8-moccasin?style=flat-square)](README-es.md)

## ¿Por qué DLoad?

DLoad resuelve un problema común en proyectos PHP: cómo distribuir e instalar herramientas binarias y recursos necesarios junto con tu código PHP.
Con DLoad, puedes:

- Descargar automáticamente herramientas requeridas durante la inicialización del proyecto
- Asegurar que todos los miembros del equipo usen las mismas versiones de herramientas
- Simplificar la incorporación automatizando la configuración del entorno
- Gestionar compatibilidad multiplataforma sin configuración manual
- Mantener binarios y recursos separados de tu control de versiones

### Tabla de Contenidos

- [Instalación](#instalación)
- [Inicio Rápido](#inicio-rápido)
- [Uso de Línea de Comandos](#uso-de-línea-de-comandos)
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
    - [Usando Velox Descargado](#usando-velox-descargado)
    - [Configuración DLoad](#configuración-dload)
    - [Construyendo RoadRunner](#construyendo-roadrunner)
- [Registro de Software Personalizado](#registro-de-software-personalizado)
    - [Definiendo Software](#definiendo-software)
    - [Elementos de Software](#elementos-de-software)
- [Casos de Uso](#casos-de-uso)
    - [Configuración de Entorno de Desarrollo](#configuración-de-entorno-de-desarrollo)
    - [Configuración de Nuevo Proyecto](#configuración-de-nuevo-proyecto)
    - [Integración CI/CD](#integración-cicd)
    - [Equipos Multiplataforma](#equipos-multiplataforma)
    - [Gestión de Herramientas PHAR](#gestión-de-herramientas-phar)
    - [Distribución de Recursos Frontend](#distribución-de-recursos-frontend)
- [Límites de Rate de API de GitHub](#límites-de-rate-de-api-de-github)
- [Contribuciones](#contribuciones)


## Instalación

```bash
composer require internal/dload -W
```

[![PHP](https://img.shields.io/packagist/php-v/internal/dload.svg?style=flat-square&logo=php)](https://packagist.org/packages/internal/dload)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/internal/dload.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/internal/dload)
[![License](https://img.shields.io/packagist/l/internal/dload.svg?style=flat-square)](LICENSE.md)
[![Total DLoads](https://img.shields.io/packagist/dt/internal/dload.svg?style=flat-square)](https://packagist.org/packages/internal/dload/stats)

## Inicio Rápido

1. **Instala DLoad vía Composer**:

    ```bash
    composer require internal/dload -W
    ```

Alternativamente, puedes descargar la última versión desde [GitHub releases](https://github.com/php-internal/dload/releases).

2. **Crea tu archivo de configuración interactivamente**:

    ```bash
    ./vendor/bin/dload init
    ```

    Este comando te guiará a través de la selección de paquetes de software y creará un archivo de configuración `dload.xml`. También puedes crearlo manualmente:

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

4. **Integración con Composer** (opcional):

    ```json
    {
        "scripts": {
            "post-update-cmd": "dload get --no-interaction -v || \"echo can't dload binaries\""
        }
    }
    ```

## Uso de Línea de Comandos

### Inicializar Configuración

```bash
# Crear archivo de configuración interactivamente
./vendor/bin/dload init

# Crear configuración en ubicación específica
./vendor/bin/dload init --config=./custom-dload.xml

# Crear configuración mínima sin prompts
./vendor/bin/dload init --no-interaction

# Sobrescribir configuración existente sin confirmación
./vendor/bin/dload init --overwrite
```

### Descargar Software

```bash
# Descargar desde archivo de configuración
./vendor/bin/dload get

# Descargar paquetes específicos
./vendor/bin/dload get rr temporal

# Descargar con opciones
./vendor/bin/dload get rr --stability=beta --force
```

#### Opciones de Descarga

| Opción | Descripción | Por defecto |
|--------|-------------|---------|
| `--path` | Directorio para almacenar binarios | Directorio actual |
| `--arch` | Arquitectura objetivo (amd64, arm64) | Arquitectura del sistema |
| `--os` | SO objetivo (linux, darwin, windows) | SO actual |
| `--stability` | Estabilidad de lanzamiento (stable, beta) | stable |
| `--config` | Ruta al archivo de configuración | ./dload.xml |
| `--force`, `-f` | Forzar descarga aunque el binario exista | false |

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
# Construir software personalizado usando archivo de configuración
./vendor/bin/dload build

# Construir con archivo de configuración específico
./vendor/bin/dload build --config=./custom-dload.xml
```

#### Opciones de Construcción

| Opción | Descripción | Por defecto |
|--------|-------------|---------|
| `--config` | Ruta al archivo de configuración | ./dload.xml |

El comando `build` ejecuta acciones de construcción definidas en tu archivo de configuración, como crear binarios RoadRunner personalizados con plugins específicos.
Para información detallada sobre construir RoadRunner personalizado, consulta la sección [Construir RoadRunner Personalizado](#construir-roadrunner-personalizado).

## Guía de Configuración

### Configuración Interactiva

La forma más fácil de crear un archivo de configuración es usando el comando interactivo `init`:

```bash
./vendor/bin/dload init
```

Esto:

- Te guiará a través de la selección de paquetes de software
- Mostrará software disponible con descripciones y repositorios
- Generará un archivo `dload.xml` correctamente formateado con validación de esquema
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

DLoad soporta tres tipos de descarga que determinan cómo se procesan los recursos:

#### Atributo de Tipo

```xml
<!-- Especificación explícita de tipo -->
<download software="psalm" type="phar" />        <!-- Descargar .phar sin desempaquetar -->
<download software="frontend" type="archive" />  <!-- Forzar extracción de archivo -->
<download software="rr" type="binary" />         <!-- Procesamiento específico de binarios -->

<!-- Manejo automático de tipo (recomendado) -->
<download software="rr" />           <!-- Usa todos los manejadores disponibles -->
<download software="frontend" />     <!-- Procesamiento inteligente basado en configuración de software -->
```

#### Comportamiento Por Defecto (Tipo No Especificado)

Cuando `type` no se especifica, DLoad automáticamente usa todos los manejadores disponibles:

- **Procesamiento de binarios**: Si el software tiene sección `<binary>`, realiza verificación de presencia y versión de binarios
- **Procesamiento de archivos**: Si el software tiene sección `<file>` y el recurso se descarga, procesa archivos durante el desempaquetado
- **Descarga simple**: Si no existen secciones, descarga el recurso sin desempaquetar

```xml
<!-- lista de registro -->
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
|-----------|--------------------------------------------------------------|--------------------------------|
| `binary`  | Verificación de binarios, validación de versión, permisos de ejecución  | Herramientas CLI, ejecutables         |
| `phar`    | Descarga archivos `.phar` como ejecutables **sin desempaquetar** | Herramientas PHP como Psalm, PHPStan  |
| `archive` | **Fuerza desempaquetado incluso para archivos .phar**                    | Cuando necesitas contenido de archivo |

> [!NOTE]
> Usa `type="phar"` para herramientas PHP que deben permanecer como archivos `.phar`.
> Usar `type="archive"` desempaquetará incluso archivos `.phar`.

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
    
    <!-- Lanzamientos de características (establece automáticamente estabilidad de vista previa) -->
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
        
        <!-- Dirigir diferentes entornos -->
        <download software="prod-tool" version="^2.0.0@stable" />
        <download software="dev-tool" version="^2.0.0@beta" />
    </actions>
</dload>
```

## Construir RoadRunner Personalizado

DLoad soporta construir binarios RoadRunner personalizados usando la herramienta de construcción Velox. Esto es útil cuando necesitas RoadRunner con combinaciones de plugins personalizados que no están disponibles en versiones pre-construidas.

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

| Atributo | Descripción | Por defecto |
|-----------|-------------|---------|
| `velox-version` | Versión de la herramienta de construcción Velox | Última |
| `golang-version` | Versión de Go requerida | Última |
| `binary-version` | Versión de RoadRunner para mostrar en `rr --version` | Última |
| `config-file` | Ruta al archivo velox.toml local | `./velox.toml` |
| `binary-path` | Ruta para guardar el binario RoadRunner construido | `./rr` |

### Proceso de Construcción

DLoad maneja automáticamente el proceso de construcción:

1. **Verificación de Golang**: Verifica que Go esté instalado globalmente (dependencia requerida)
2. **Preparación de Velox**: Usa Velox desde instalación global, descarga local, o descarga automáticamente si es necesario
3. **Configuración**: Copia tu velox.toml local al directorio de construcción
4. **Construcción**: Ejecuta el comando `vx build` con la configuración especificada
5. **Instalación**: Mueve el binario construido a la ubicación objetivo y establece permisos de ejecución
6. **Limpieza**: Elimina archivos temporales de construcción

> [!NOTE]
> DLoad requiere que Go (Golang) esté instalado globalmente en tu sistema. No descarga ni gestiona instalaciones de Go.

### Generación de Archivo de Configuración

Puedes generar un archivo de configuración `velox.toml` usando el constructor en línea en https://build.roadrunner.dev/

Para documentación detallada sobre opciones de configuración de Velox y ejemplos, visita https://docs.roadrunner.dev/docs/customization/build

Esta interfaz web te ayuda a seleccionar plugins y genera la configuración apropiada para tu construcción RoadRunner personalizada.

### Usando Velox Descargado

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

### Construyendo RoadRunner

```bash
# Construir RoadRunner usando configuración velox.toml
./vendor/bin/dload build

# Construir con archivo de configuración específico
./vendor/bin/dload build --config=custom-rr.xml
```

El binario RoadRunner construido incluirá solo los plugins especificados en tu archivo `velox.toml`, reduciendo el tamaño del binario y mejorando el rendimiento para tu caso de uso específico.

## Registro de Software Personalizado

### Definiendo Software

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

        <!-- Archivo con archivos -->
        <software name="frontend" description="Recursos de frontend">
            <repository type="github" uri="my-org/frontend" asset-pattern="/^artifacts.*/" />
            <file pattern="/^.*\.js$/" />
            <file pattern="/^.*\.css$/" />
        </software>

        <!-- Mixto: binarios + archivos -->
        <software name="development-suite" description="Herramientas de desarrollo completas">
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

#### Configuración de Repositorio

- **type**: Actualmente soporta "github"
- **uri**: Ruta del repositorio (ej., "username/repo")
- **asset-pattern**: Patrón de expresión regular para coincidir con recursos de lanzamiento

#### Elementos Binarios

- **name**: Nombre del binario para referencia
- **pattern**: Patrón de expresión regular para coincidir con binario en recursos
- Maneja automáticamente filtrado por SO/arquitectura

#### Elementos de Archivo

- **pattern**: Patrón de expresión regular para coincidir con archivos
- **extract-path**: Directorio de extracción opcional
- Funciona en cualquier sistema (sin filtrado por SO/arquitectura)

## Casos de Uso

### Configuración de Entorno de Desarrollo

```bash
# Configuración única para nuevos desarrolladores
composer install
./vendor/bin/dload init  # Solo la primera vez
./vendor/bin/dload get
```

### Configuración de Nuevo Proyecto

```bash
# Iniciar un nuevo proyecto con DLoad
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
    <download software="rr" />        <!-- Binario Linux para Linux, .exe Windows para Windows -->
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
    <download software="psalm" type="archive" />  <!-- Desempaqueta psalm.phar -->
</actions>
```

### Distribución de Recursos Frontend

```xml
<software name="ui-kit">
    <repository type="github" uri="company/ui-components" />
    <file pattern="/^dist\/.*/" extract-path="public/components" />
</software>

<actions>
    <download software="ui-kit" type="archive" />
</actions>
```

## Límites de Rate de API de GitHub

Usa un token de acceso personal para evitar límites de rate:

```bash
GITHUB_TOKEN=your_token_here ./vendor/bin/dload get
```

Añádelo a variables de entorno CI/CD para descargas automatizadas.

## Contribuciones

¡Las contribuciones son bienvenidas! Envía Pull Requests para:

- Añadir nuevo software al registro predefinido
- Mejorar la funcionalidad de DLoad  
- Mejorar la documentación y traducirla a [otros idiomas](docs/guidelines/how-to-translate-readme-docs.md)
