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

DLoad resuelve un problema común en proyectos PHP: cómo distribuir e instalar herramientas y recursos binarios necesarios junto con tu código PHP.
Con DLoad, puedes:

- Descargar automáticamente las herramientas requeridas durante la inicialización del proyecto
- Asegurar que todos los miembros del equipo usen las mismas versiones de las herramientas
- Simplificar la incorporación automatizando la configuración del entorno
- Gestionar la compatibilidad multiplataforma sin configuración manual
- Mantener los binarios y recursos separados del control de versiones

## Instalación

```bash
composer require internal/dload -W
```

[![PHP](https://img.shields.io/packagist/php-v/internal/dload.svg?style=flat-square&logo=php)](https://packagist.org/packages/internal/dload)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/internal/dload.svg?style=flat-square&logo=packagist)](https://packagist.org/packages/internal/dload)
[![License](https://img.shields.io/packagist/l/internal/dload.svg?style=flat-square)](LICENSE.md)
[![Total DLoads](https://img.shields.io/packagist/dt/internal/dload.svg?style=flat-square)](https://packagist.org/packages/internal/dload/stats)

## Inicio rápido

1. **Inicializa la configuración de tu proyecto**:

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

2. **Descarga el software configurado**:

    ```bash
    ./vendor/bin/dload get
    ```

3. **Integra con Composer** (opcional):

    ```json
    {
        "scripts": {
            "post-update-cmd": "dload get --no-interaction -v || echo can't dload binaries"
        }
    }
    ```

## Uso desde la línea de comandos

### Descargar software

```bash
# Descargar desde el archivo de configuración
./vendor/bin/dload get

# Descargar paquetes específicos
./vendor/bin/dload get rr temporal

# Descargar con opciones
./vendor/bin/dload get rr --stability=beta --force
```

#### Opciones de descarga

| Opción    | Descripción                                 | Predeterminado         |
|-----------|---------------------------------------------|-----------------------|
| `--path`  | Directorio para guardar los binarios        | Directorio actual     |
| `--arch`  | Arquitectura objetivo (amd64, arm64)        | Arquitectura del sistema |
| `--os`    | SO objetivo (linux, darwin, windows)        | SO actual             |
| `--stability` | Estabilidad de la versión (stable, beta) | stable                |
| `--config`| Ruta al archivo de configuración            | ./dload.xml           |
| `--force`, `-f` | Forzar descarga aunque exista binario | false                 |

### Ver software

```bash
# Listar paquetes de software disponibles
./vendor/bin/dload software

# Mostrar software descargado
./vendor/bin/dload show

# Mostrar detalles de un software específico
./vendor/bin/dload show rr

# Mostrar cada el software (descargado y disponible)
./vendor/bin/dload show --all
```

## Guía de configuración

### Configuración básica

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

### Tipos de descarga

DLoad soporta tres tipos de descarga que determinan cómo se procesan los recursos:

#### Atributo type

```xml
<!-- Especificación explícita de tipo -->
<download software="psalm" type="phar" />        <!-- Descarga .phar sin desempaquetar -->
<download software="frontend" type="archive" />  <!-- Fuerza extracción de archivo -->
<download software="rr" type="binary" />         <!-- Procesamiento específico de binarios -->

<!-- Manejo automático de tipos (recomendado) -->
<download software="rr" />           <!-- Usa todos los manejadores disponibles -->
<download software="frontend" />     <!-- Procesamiento inteligente basado en configuración del software -->
```

#### Comportamiento predeterminado (sin tipo especificado)

Cuando no se especifica `type`, DLoad usa automáticamente todos los manejadores disponibles:

- **Procesamiento binario**: Si el software tiene sección `<binary>`, verifica presencia y versión
- **Procesamiento de archivos**: Si tiene sección `<file>` y se descarga el recurso, procesa archivos al descomprimir
- **Descarga simple**: Si no existen secciones, descarga el recurso sin descomprimir

```xml
<!-- lista de registro -->
<software name="complex-tool">
    <binary name="tool" pattern="/^tool-.*/" />
    <file pattern="/^config\..*/" extract-path="config" />
</software>

<!-- lista de acciones -->
<!-- Usa tanto procesamiento binario como de archivos -->
<download software="complex-tool" />
```

#### Comportamientos explícitos de tipo

| Tipo      | Comportamiento                                                    | Caso de uso                               |
|:----------|:------------------------------------------------------------------|:------------------------------------------|
| `binary`  | Verificación binaria, validación de versión, permisos ejecutables | Herramientas CLI, ejecutables             |
| `phar`    | Descarga archivos `.phar` como ejecutables **sin desempaquetar**  | Herramientas PHP como Psalm, PHPStan      |
| `archive` | **Fuerza el desempaquetado incluso para archivos .phar**          | Cuando necesitas el contenido del archivo |

> [!NOTE]
> Usa `type="phar"` para herramientas PHP que deben permanecer como archivos `.phar`.
> Usar `type="archive"` desempaquetará incluso archivos `.phar`.

### Restricciones de versión

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

    <!-- Lanzamientos de características (asigna automáticamente estabilidad preview) -->
    <download software="experimental" version="^1.0.0-experimental" />
</actions>
```

### Opciones avanzadas de configuración

```xml
<dload temp-dir="./runtime">
    <actions>
        <!-- Diferentes rutas de extracción -->
        <download software="frontend" extract-path="public/assets" />
        <download software="config" extract-path="config" />

        <!-- Diferentes entornos objetivo -->
        <download software="prod-tool" version="^2.0.0@stable" />
        <download software="dev-tool" version="^2.0.0@beta" />
    </actions>
</dload>
```

## Registro personalizado de software

### Definiendo software

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

        <!-- Archivo con recursos -->
        <software name="frontend" description="Recursos del frontend">
            <repository type="github" uri="my-org/frontend" asset-pattern="/^artifacts.*/" />
            <file pattern="/^.*\.js$/" />
            <file pattern="/^.*\.css$/" />
        </software>

        <!-- Mixto: binarios + archivos -->
        <software name="development-suite" description="Herramientas completas de desarrollo">
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

### Elementos de software

#### Configuración de repositorio

- **type**: Actualmente soporta "github"
- **uri**: Ruta del repositorio (ejemplo: "usuario/repositorio")
- **asset-pattern**: Patrón regex para coincidir con los recursos de los releases

#### Elementos binarios

- **name**: Nombre del binario para referencia
- **pattern**: Patrón regex para coincidir con el binario en los recursos
- Maneja automáticamente el filtrado por SO/arquitectura

#### Elementos de archivo

- **pattern**: Patrón regex para coincidir con archivos
- **extract-path**: Directorio de extracción opcional
- Funciona en cualquier sistema (sin filtrado por SO/arquitectura)


## Casos de uso

### Configuración de entorno de desarrollo

```bash
# Configuración única para nuevos desarrolladores
composer install
./vendor/bin/dload get
```

### Integración CI/CD

```yaml
# GitHub Actions
- name: Descargar herramientas
  run: GITHUB_TOKEN=${{ secrets.GITHUB_TOKEN }} ./vendor/bin/dload get
```

### Equipos multiplataforma

Cada desarrollador obtiene los binarios correctos para su sistema:

```xml
<actions>
    <download software="rr" />        <!-- Binario Linux para Linux, .exe para Windows -->
    <download software="temporal" />   <!-- Binario macOS para macOS, etc. -->
</actions>
```

### Gestión de herramientas PHAR

```xml
<actions>
    <!-- Descargar como archivos ejecutables .phar -->
    <download software="psalm" type="phar" />
    <download software="phpstan" type="phar" />
    
    <!-- Extraer contenido en su lugar -->
    <download software="psalm" type="archive" />  <!-- Desempaqueta psalm.phar -->
</actions>
```

### Distribución de recursos frontend

```xml
<software name="ui-kit">
    <repository type="github" uri="company/ui-components" />
    <file pattern="/^dist\/.*/" extract-path="public/components" />
</software>

<actions>
    <download software="ui-kit" type="archive" />
</actions>
```


## Límites de peticiones a la API de GitHub

Usa un token de acceso personal para evitar límites de peticiones:

```bash
GITHUB_TOKEN=tu_token_aquí ./vendor/bin/dload get
```

Agrégalo a las variables de entorno de tu CI/CD para descargas automatizadas.

## Contribuir

¡Contribuciones bienvenidas! Envía Pull Requests para:

- Agregar nuevo software al registro predefinido
- Mejorar la funcionalidad de DLoad
- Mejorar la documentación y traducirla [a otros idiomas](docs/guidelines/how-to-translate-readme-docs.md)
