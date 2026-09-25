# Revista Digital – DDP Noticias

Sistema web desarrollado para administrar y publicar contenido informativo de **Diálogo y Desarrollo Perú (DDP Noticias)**. El proyecto incluye un sitio público para los lectores, un panel administrativo para gestionar el contenido y un proceso de despliegue automático desde GitHub hacia alwaysdata.

## Enlaces del proyecto

- **Repositorio:** [github.com/LuisMiguelFt20/revista-digital-admin](https://github.com/LuisMiguelFt20/revista-digital-admin)
- **Sitio público:** [luismiguel.alwaysdata.net/dist/sitio-publico/](https://luismiguel.alwaysdata.net/dist/sitio-publico/)
- **Panel administrativo:** [luismiguel.alwaysdata.net/dist/dashboard/auth/sign-in.php](https://luismiguel.alwaysdata.net/dist/dashboard/auth/sign-in.php)
- **Ejecuciones de despliegue:** [GitHub Actions](https://github.com/LuisMiguelFt20/revista-digital-admin/actions)

> Las credenciales del panel, de la base de datos y del servidor no se publican en este repositorio.

## Funcionalidad general

El sistema está dividido en dos partes principales:

### Sitio público

Permite que cualquier visitante consulte el contenido publicado. Incluye las siguientes secciones:

- Inicio.
- Actualidad.
- Reportajes.
- Podcast.
- Boletín NTEP.
- Alianzas.
- Sobre D&D.
- Contacto.

Las publicaciones que se muestran en esta parte se obtienen desde la base de datos. El sitio presenta imágenes, fechas, títulos, resúmenes y páginas de detalle.

### Panel administrativo

Es un área privada destinada a los usuarios autorizados. Sus funciones principales son:

- Inicio de sesión seguro.
- Administración de contenidos.
- Creación y edición de reportajes.
- Publicación y actualización de información.
- Gestión de imágenes relacionadas con las publicaciones.
- Control de módulos y permisos del sistema.

Los cambios realizados desde el panel se almacenan en la base de datos y se reflejan en el sitio público.

## Tecnologías utilizadas

- PHP.
- MySQL/MariaDB.
- HTML5.
- CSS3 y SCSS.
- JavaScript.
- Bootstrap 5.
- Gulp y npm para recursos del frontend.
- Git y GitHub para control de versiones.
- GitHub Actions para despliegue automático.
- alwaysdata como servicio de alojamiento.
- FileZilla/SFTP para la carga y verificación inicial de archivos.

## Estructura principal

```text
revista-digital-admin/
├── .github/
│   └── workflows/
│       └── deploy.yml          # Despliegue automático
├── dist/
│   ├── assets/                 # CSS, JavaScript, imágenes y librerías
│   ├── dashboard/              # Panel administrativo y configuración PHP
│   ├── landing-pages/          # Recursos adicionales de la plantilla
│   └── sitio-publico/          # Portal visible para los lectores
├── gulp/                       # Tareas auxiliares de compilación
├── src/                        # Archivos fuente del frontend
├── gulpfile.js                 # Configuración de tareas Gulp
├── package.json                # Dependencias y comandos npm
└── README.md                   # Documentación general del proyecto
```

## Requisitos para ejecución local

- PHP 8 o una versión compatible.
- MySQL o MariaDB.
- Servidor local como XAMPP, WAMP o Laragon.
- Node.js y npm, solamente si se modificarán o compilarán los recursos del frontend.
- Git, si se desea clonar y versionar el proyecto.

## Instalación local

1. Clonar el repositorio:

   ```bash
   git clone https://github.com/LuisMiguelFt20/revista-digital-admin.git
   ```

2. Ingresar en el proyecto:

   ```bash
   cd revista-digital-admin
   ```

3. Colocar la carpeta dentro del directorio público del servidor local. En XAMPP normalmente se utiliza `htdocs`.

4. Crear una base de datos local e importar la copia SQL proporcionada de manera privada por el responsable del proyecto.

5. Crear o completar el archivo local de conexión dentro de la configuración del panel. Se debe usar una estructura similar a la siguiente, sin publicar datos reales:

   ```php
   <?php
   $conexion = new PDO(
       'mysql:host=SERVIDOR;dbname=NOMBRE_BASE;charset=utf8mb4',
       'USUARIO',
       'CONTRASENA',
       [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
   );
   ```

6. Abrir el proyecto desde el navegador. Por ejemplo:

   ```text
   http://localhost/revista-digital-admin/dist/sitio-publico/
   ```

## Recursos del frontend

Si se modifican archivos fuente de estilos o scripts, primero se instalan las dependencias:

```bash
npm install
```

Para trabajar en modo de desarrollo:

```bash
npm run watch
```

Para generar los archivos de producción:

```bash
npm run build
```

Los comandos disponibles dependen de los scripts definidos en `package.json`.

## Base de datos

La base de datos almacena usuarios administrativos, publicaciones, reportajes y demás información dinámica del portal.

En el servidor de producción, la base de datos ya está configurada y poblada. El despliegue desde GitHub está diseñado para actualizar el código sin reemplazar automáticamente:

- Las credenciales de conexión.
- La información almacenada en las tablas.
- Los archivos privados del servidor.
- Las imágenes cargadas por los usuarios, cuando están excluidas del despliegue.

Por seguridad, los datos de conexión y las copias de la base de datos no deben subirse a GitHub.

## Despliegue automático desde GitHub

El repositorio contiene el flujo `.github/workflows/deploy.yml`. Cada vez que se confirma un cambio en la rama `main`, GitHub Actions realiza el proceso de publicación.

Flujo general:

1. Un desarrollador modifica el código.
2. Confirma el cambio mediante un *commit* en la rama `main`.
3. GitHub Actions descarga la versión actual del repositorio.
4. El flujo establece una conexión segura con alwaysdata.
5. Los archivos autorizados se sincronizan con el servidor.
6. La aplicación pública muestra la nueva versión del código.

La contraseña SFTP se guarda como un secreto cifrado llamado `ALWAYSDATA_PASSWORD`. Su valor no aparece en el código ni en los registros normales del repositorio.

## Cómo realizar una modificación

### Desde la página de GitHub

1. Abrir el archivo que se desea cambiar.
2. Presionar el botón de edición.
3. Realizar la modificación.
4. Seleccionar **Commit changes**.
5. Confirmar directamente en `main` solamente si el cambio fue revisado.
6. Abrir la pestaña **Actions** y comprobar que el despliegue termine con una marca verde.
7. Recargar la página pública para verificar el resultado.

### Desde una computadora

```bash
git pull origin main
git add .
git commit -m "Descripción breve del cambio"
git push origin main
```

Después del `push`, GitHub Actions inicia el despliegue automáticamente.

## Recomendaciones para colaboradores

- Crear una rama para cambios grandes y usar un *pull request* antes de unirlos a `main`.
- Probar las modificaciones localmente.
- No publicar contraseñas, archivos de conexión ni exportaciones SQL.
- No modificar la base de datos de producción desde GitHub.
- No eliminar carpetas del servidor que contengan archivos subidos por los usuarios.
- Revisar el resultado de GitHub Actions después de cada actualización.
- Mantener mensajes de *commit* claros y descriptivos.

## Seguridad aplicada

- Credenciales del servidor almacenadas mediante GitHub Secrets.
- Acceso al panel administrativo mediante autenticación.
- Separación entre el código versionado y la configuración privada.
- Conexión PDO con manejo de errores mediante excepciones.
- Uso de funciones de escape al mostrar contenido dinámico.
- Despliegue por SSH/SFTP.
- Base de datos y archivos sensibles excluidos del repositorio público.

## Prueba rápida del sistema

Después de cada despliegue se recomienda comprobar lo siguiente:

1. El sitio público abre correctamente.
2. El menú dirige a las secciones esperadas.
3. Los reportajes cargan desde la base de datos.
4. Las imágenes se visualizan sin errores.
5. El inicio de sesión del administrador funciona.
6. El contenido puede crearse o editarse desde el panel.
7. El cambio realizado desde el panel aparece en el sitio público.
8. La ejecución más reciente de GitHub Actions aparece en color verde.

## Solución de problemas

### La página muestra “Forbidden”

Verificar que la URL apunte al directorio público correcto y que los archivos y carpetas tengan permisos de lectura adecuados.

### La página no conecta con la base de datos

Comprobar el archivo privado de conexión del servidor, el nombre de la base, el usuario autorizado y la disponibilidad del servicio MySQL. No publicar estas credenciales en incidencias o capturas.

### GitHub Actions aparece en rojo

Abrir la ejecución fallida en la pestaña **Actions**, identificar el paso con error y revisar que el secreto del servidor y las rutas del despliegue sigan configurados correctamente.

### Se modificó GitHub, pero la página no cambió

Confirmar que el cambio fue guardado en `main`, que la acción finalizó correctamente y que el navegador no está mostrando una copia en caché. Se puede realizar una recarga forzada con `Ctrl + F5`.

## Autor

**Uscamayta Guzmán Luis Miguel Herber**  
Proyecto académico de desarrollo y despliegue web.

## Créditos

El panel administrativo utiliza componentes visuales basados en **Hope UI**, un sistema de diseño construido sobre Bootstrap 5. El contenido, la integración PHP/MySQL, el sitio público y la configuración de despliegue corresponden a la implementación de este proyecto.

## Licencia

Este repositorio conserva las licencias correspondientes de las librerías y plantillas de terceros utilizadas. El código propio se emplea con fines académicos.
