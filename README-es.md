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

2. **Crea tu archivo de configuración interactivamente**:

    ```bash
    ./vendor/bin/dload init
    ```

    Este comando te guiará a través de la selección de paquetes de software y creará un archivo de configuración `dload.xml`. También puedes crearlo manualmente:

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

3. **Descarga el software configurado**:

    ```bash
    ./vendor/bin/dload get
    ```

4. **Integración con Composer** (opcional):

    ```json
    {
        "scripts": {
            "post-update-cmd": "dload get --no-interaction -v || echo can't dload binaries"
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
