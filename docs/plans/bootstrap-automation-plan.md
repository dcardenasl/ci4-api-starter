# Plan de Automatización: "CI4 Project Bootstrapper"

Este plan detalla la creación de un script de inicialización para nuevos proyectos basados en el `ci4-api-starter`. El objetivo es reducir el tiempo de configuración de 15 minutos a menos de 1 minuto, eliminando errores humanos.

## 1. El Mecanismo de Entrada
Se creará un script llamado `install.sh` en la raíz del repositorio. Al ser un repo público, el comando de ejecución para un nuevo proyecto será:
```bash
/bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/dcardenasl/ci4-api-starter/main/install.sh)"
```

## 2. Flujo Lógico del Script (`install.sh`)

### Fase A: Recolección de Datos (Interactiva)
1. **Nombre del Proyecto:** Se usará para crear la carpeta y como `APP_NAME`.
2. **Configuración de DB:**
   - Host (default: `localhost`)
   - Usuario (default: `root`)
   - Password (input oculto)
   - Nombre de la DB (sugerido basado en el nombre del proyecto)

### Fase B: Despliegue de Archivos
1. **Clonación:** `git clone --depth=1` del template en la carpeta del nuevo proyecto.
2. **Dependencias:** Ejecución automática de `composer install`.
3. **Configuración de Entorno:**
   - Copiar `.env.example` a `.env`.
   - Reemplazar placeholders con los datos recolectados.
   - Generar una `JWT_SECRET_KEY` aleatoria y segura usando PHP.

### Fase C: Infraestructura y Base de Datos
1. **Creación de DB:** Intentar ejecutar `CREATE DATABASE IF NOT EXISTS ...` vía CLI de MySQL.
2. **Migraciones:** Ejecutar `php spark migrate` para preparar las tablas.
3. **Bootstrap de Superadmin:** Ejecutar `php spark users:bootstrap-superadmin --email ... --password ... --first-name ... --last-name ...`.

### Fase D: Limpieza e Identidad
1. **Reset de Git (opt-in con confirmación):**
   - `rm -rf .git`
   - `git init`
   - `git add .`
   - `git commit -m "Initial commit from ci4-api-starter template"`
2. **Finalización:** Mostrar mensaje de éxito con instrucciones para iniciar el servidor (`php spark serve`).

## 3. Consideraciones Técnicas (macOS)
- El script verificará la existencia de `php`, `composer` y `mysql` antes de iniciar.
- Se usará `sed` (versión BSD de macOS) para el reemplazo de strings en el `.env`.
- El script se auto-eliminará después de la ejecución si es necesario, o quedará como herramienta interna.

## 4. Próximos Pasos
1. Crear el script `install.sh` con la lógica de Bash.
2. Crear un pequeño script PHP de apoyo para la generación de claves y validaciones complejas.
3. Probar el flujo en una carpeta temporal.
