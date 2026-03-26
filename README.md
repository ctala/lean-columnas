# Lean Columnas

> Plugin de WordPress para gestionar columnas de opinion con roles editoriales, flujo de trabajo completo y schema SEO.

**Version:** 0.1.0
**Autor:** [Cristian Tala](https://github.com/ctala)
**Licencia:** GPL-2.0-or-later
**Requiere WordPress:** 6.0+
**Requiere PHP:** 8.1+

---

## Por que existe este plugin

Los medios digitales y sitios editoriales necesitan un sistema dedicado para gestionar columnas de opinion que sea independiente del flujo de publicacion de noticias. Lean Columnas introduce roles especificos (columnista, agencia), un flujo editorial con validaciones de calidad automaticas, y markup estructurado para SEO y E-E-A-T, sin agregar complejidad innecesaria al sitio.

## Requisitos

- **PHP** 8.1 o superior
- **WordPress** 6.0 o superior
- **Tema recomendado:** GeneratePress (el CSS del plugin es estructural y compatible con GP)
- No requiere tablas personalizadas en la base de datos. Usa user meta y post meta nativos de WordPress.

## Instalacion

### Manual

1. Descarga o clona el repositorio en `wp-content/plugins/lean-columnas/`.
2. Activa el plugin desde el panel de administracion en **Plugins > Plugins instalados**.
3. Al activar, el plugin registra automaticamente los roles personalizados y los custom post statuses.

### WP-CLI

```bash
wp plugin activate lean-columnas
```

## Funcionalidades

### Custom Post Type: columna-opinion

El plugin registra el CPT `columna-opinion` con soporte para:

- Titulo, editor, autor, imagen destacada y extracto
- Archivo publico en `/columna-opinion/`
- API REST habilitada en `/wp-json/wp/v2/columnas-opinion`
- Icono de menu: `dashicons-welcome-widgets-menus`

### Taxonomia: columna-categoria

Taxonomia jerarquica asociada al CPT. Permite clasificar columnas por tematica (economia, tecnologia, politica, etc.). Accesible via REST en `/wp-json/wp/v2/columna-categorias`.

### Roles y capabilities

El plugin crea dos roles personalizados y agrega capabilities a los roles existentes de WordPress.

**Rol: lc_columnista**

| Capability | Descripcion |
|---|---|
| `read` | Acceso basico al panel |
| `upload_files` | Subir imagenes |
| `lc_create_column` | Crear columnas nuevas |
| `lc_edit_own_columns` | Editar sus propias columnas |
| `lc_delete_own_draft` | Eliminar borradores propios |
| `lc_submit_column` | Enviar columna a revision |
| `lc_view_own_stats` | Ver estadisticas propias |
| `lc_upload_images` | Subir imagenes para columnas |

**Rol: lc_agencia**

Hereda todas las capabilities de `lc_columnista` y agrega:

| Capability | Descripcion |
|---|---|
| `lc_assign_columnists` | Asignar columnistas a la agencia |
| `lc_submit_on_behalf` | Enviar columnas en nombre de un columnista |
| `lc_view_agency_stats` | Ver estadisticas de la agencia |
| `lc_edit_agency_columns` | Editar columnas de sus columnistas |
| `lc_view_agency_dashboard` | Acceder al dashboard de agencia |

**Capabilities para Editor y Administrador**

Se agregan a los roles `editor` y `administrator`:

| Capability | Descripcion |
|---|---|
| `lc_review_columns` | Revisar columnas en la cola editorial |
| `lc_edit_all_columns` | Editar cualquier columna |
| `lc_manage_all_columnists` | Gestionar todos los columnistas |
| `lc_manage_agencies` | Gestionar agencias |
| `lc_publish_column` | Publicar columnas |
| `lc_manage_settings` | Gestionar configuracion del plugin |

El rol `administrator` recibe todas las capabilities del plugin.

### Flujo editorial

Las columnas de opinion siguen un flujo de estados personalizado. Cada transicion valida permisos del usuario y registra un log de auditoria en post meta (`_lc_status_log`).

```
draft --> lc_submitted --> lc_in_review --> lc_approved --> publish
                |               |               |
                v               v               v
              draft        lc_returned      lc_in_review
                           lc_rejected
```

**Transiciones permitidas:**

| Estado actual | Estados siguientes permitidos |
|---|---|
| `draft` | `lc_submitted` |
| `lc_submitted` | `lc_in_review`, `draft` |
| `lc_in_review` | `lc_approved`, `lc_returned`, `lc_rejected` |
| `lc_returned` | `lc_submitted`, `draft` |
| `lc_approved` | `publish`, `lc_in_review` |
| `lc_rejected` | `draft` |
| `publish` | `draft` |

**Capabilities requeridas por transicion:**

- `lc_submitted` requiere `lc_submit_column`
- `lc_in_review`, `lc_approved`, `lc_returned`, `lc_rejected` requieren `lc_review_columns`
- `publish` requiere `lc_publish_column`

**Notas editoriales:** Los editores pueden agregar notas en cada transicion. Se almacenan en `_lc_editorial_notes` como un array serializado con `user_id`, `status`, `note` y `created_at`.

### Quality gates

Antes de enviar una columna a revision (transicion a `lc_submitted`), el sistema valida automaticamente:

| Validacion | Criterio |
|---|---|
| Conteo de palabras | Minimo 600, maximo 3,000 |
| Longitud del titulo | Minimo 10 caracteres, maximo 70 |
| Extracto | Obligatorio (no puede estar vacio) |
| Imagen destacada | Obligatoria |
| Subtitulos (h2/h3) | Minimo 2 en el contenido |
| Sanitizacion | No permite tags `<script>`, `<iframe>`, `<object>`, `<embed>`, `<applet>` |

Si alguna validacion falla, la transicion se rechaza con un error `422` que detalla las fallas especificas.

### Schema JSON-LD: OpinionNewsArticle

En cada pagina singular de `columna-opinion`, el plugin inyecta un bloque `<script type="application/ld+json">` en `wp_head` con schema `OpinionNewsArticle` de schema.org. El markup incluye:

- `headline`, `description`, `datePublished`, `dateModified`
- `author` con `name`, `image`, `description`, `url` y `sameAs` (Twitter, LinkedIn, Instagram)
- `publisher` con nombre del sitio, URL y logo
- `mainEntityOfPage` con permalink
- `image` con la imagen destacada

Este schema refuerza las senales de E-E-A-T para Google al identificar explicitamente el contenido como articulo de opinion con autor verificable.

### Paginas de administracion

El plugin agrega un menu principal **Columnas** en wp-admin con tres subpaginas:

1. **Cola Editorial** (`lean-columnas`): Lista columnas filtradas por estado (Enviadas, En Revision, Aprobadas, Devueltas, Rechazadas). Muestra titulo, autor, conteo de palabras, fecha y botones de accion contextual (Tomar, Aprobar, Publicar, Editar). Requiere `lc_review_columns`.

2. **Columnistas** (`lean-columnas-columnistas`): Lista todos los usuarios con rol `lc_columnista`, mostrando nombre, email, estado (activo/inactivo/pendiente), agencia asignada, cantidad de columnas y fecha de registro. Requiere `lc_manage_all_columnists`.

3. **Agencias** (`lean-columnas-agencias`): Lista todos los usuarios con rol `lc_agencia`, mostrando nombre, email, cantidad de columnistas asignados y fecha de registro. Requiere `lc_manage_agencies`.

### Relacion agencia-columnista

La relacion entre agencias y columnistas se almacena en user meta:

- `lc_agency_user_id`: En el perfil del columnista, indica el ID del usuario agencia asignado.
- Un administrador o editor con `lc_manage_all_columnists` puede asignar una agencia desde el perfil del columnista.
- Si el valor es `0` o no existe, el columnista se considera independiente.

### Campos de perfil de usuario

El plugin agrega campos al perfil de WordPress para usuarios con roles del plugin:

- **Twitter/X URL** (`lc_social_twitter`)
- **LinkedIn URL** (`lc_social_linkedin`)
- **Instagram URL** (`lc_social_instagram`)
- **Agencia asignada** (`lc_agency_user_id`) -- solo visible para admins/editors
- **Estado del columnista** (`lc_columnist_status`) -- valores: `active`, `inactive`, `pending`

Estos campos alimentan el schema JSON-LD y las paginas de administracion.

## Uso

### Como un administrador publica en nombre de un columnista

1. Crea un usuario con rol **Columnista** y completa su perfil (bio, redes sociales, foto).
2. Desde el editor de WordPress, crea una nueva **Columna de Opinion**.
3. Asigna el columnista como autor del post usando el campo "Autor" del editor.
4. Redacta el contenido asegurandote de cumplir los quality gates (600+ palabras, titulo entre 10-70 caracteres, extracto, imagen destacada, al menos 2 subtitulos).
5. Publica directamente si tienes `lc_publish_column`, o enviatelo a traves del flujo editorial.

### Como un columnista envia una columna

1. El columnista inicia sesion en WordPress.
2. Navega a **Columnas > Agregar Nueva**.
3. Redacta su columna cumpliendo los requisitos de calidad.
4. Cambia el estado a **Submitted**. El sistema ejecuta los quality gates automaticamente.
5. Si pasa la validacion, la columna aparece en la Cola Editorial para revision.
6. Si la columna es devuelta (`lc_returned`), el columnista puede editarla y reenviarla.

### Como funcionan las agencias

1. Crea un usuario con rol **Agencia**.
2. En el perfil de cada columnista que pertenezca a la agencia, selecciona la agencia en el campo "Agencia asignada".
3. La agencia puede enviar columnas en nombre de sus columnistas (`lc_submit_on_behalf`) y editar las columnas del grupo (`lc_edit_agency_columns`).
4. La pagina de Agencias en wp-admin muestra cuantos columnistas tiene cada agencia asignados.

### Como sobreescribir templates desde el tema

El plugin busca templates en el siguiente orden:

1. `{child-theme}/lean-columnas/single-columna-opinion.php`
2. `{parent-theme}/lean-columnas/single-columna-opinion.php`
3. `{plugin}/templates/single-columna-opinion.php`

Lo mismo aplica para `archive-columna-opinion.php` (usado tanto para el archivo del CPT como para paginas de la taxonomia `columna-categoria`).

Para sobreescribir, crea la carpeta `lean-columnas/` dentro de tu tema y copia el template que deseas modificar.

**Template parts:** Puedes usar `TemplateLoader::getPart('parts/nombre-del-part', $args)` dentro de tus templates personalizados para cargar parciales con la misma jerarquia de busqueda.

## Jerarquia de templates

```
Pagina singular:
  tema/lean-columnas/single-columna-opinion.php
  plugin/templates/single-columna-opinion.php

Archivo y taxonomia:
  tema/lean-columnas/archive-columna-opinion.php
  plugin/templates/archive-columna-opinion.php
```

Si no existe ningun template personalizado, WordPress usa su jerarquia por defecto.

## CSS

El plugin incluye dos hojas de estilo:

- **`assets/css/frontend.css`**: Se carga unicamente en paginas singulares y archivos de `columna-opinion`. Contiene estilos estructurales minimos.
- **`assets/css/admin.css`**: Se carga unicamente en las paginas de administracion del plugin (Cola Editorial, Columnistas, Agencias).

Ambos archivos son estructurales y no aplican estilos decorativos. Estan disenados para ser compatibles con GeneratePress y otros temas que siguen los estandares de WordPress.

## WP-CLI

El plugin no registra comandos WP-CLI propios, pero puedes gestionar usuarios y contenido con los comandos nativos.

### Crear un columnista

```bash
wp user create juanperez juan@ejemplo.com \
  --role=lc_columnista \
  --display_name="Juan Perez" \
  --user_pass="contraseña-segura"
```

### Crear una agencia

```bash
wp user create agencia-prensa prensa@ejemplo.com \
  --role=lc_agencia \
  --display_name="Agencia Prensa" \
  --user_pass="contraseña-segura"
```

### Asignar un columnista a una agencia

```bash
# Obtener el ID de la agencia
wp user get agencia-prensa --field=ID

# Asignar (reemplaza 42 con el ID real de la agencia)
wp user meta update juanperez lc_agency_user_id 42
```

### Cambiar estado de un columnista

```bash
wp user meta update juanperez lc_columnist_status active
```

### Agregar redes sociales

```bash
wp user meta update juanperez lc_social_twitter "https://twitter.com/juanperez"
wp user meta update juanperez lc_social_linkedin "https://linkedin.com/in/juanperez"
```

### Listar todos los columnistas

```bash
wp user list --role=lc_columnista --fields=ID,display_name,user_email
```

### Listar columnas por estado

```bash
wp post list --post_type=columna-opinion --post_status=lc_submitted --fields=ID,post_title,post_author
```

## Hooks y filtros

### Actions

| Hook | Parametros | Descripcion |
|---|---|---|
| `lean_columnas_status_transition` | `$new_status`, `$old_status`, `$post` | Se dispara en cada transicion de estado de una columna. |
| `lean_columnas_status_{$status}` | `$post`, `$old_status` | Se dispara cuando una columna entra a un estado especifico. Ejemplo: `lean_columnas_status_lc_approved`. |

### Ejemplo de uso

Enviar una notificacion cuando una columna es aprobada:

```php
add_action('lean_columnas_status_lc_approved', function (\WP_Post $post, string $old_status): void {
    $author = get_userdata($post->post_author);
    if ($author === false) {
        return;
    }

    wp_mail(
        $author->user_email,
        'Tu columna fue aprobada',
        sprintf('La columna "%s" fue aprobada y sera publicada pronto.', $post->post_title)
    );
}, 10, 2);
```

Registrar transiciones en un log externo:

```php
add_action('lean_columnas_status_transition', function (string $new, string $old, \WP_Post $post): void {
    error_log(sprintf(
        'Columna #%d: %s -> %s (usuario: %d)',
        $post->ID,
        $old,
        $new,
        get_current_user_id()
    ));
}, 10, 3);
```

### Filtro de template

El plugin usa el filtro `template_include` de WordPress. Si necesitas alterar la logica de carga de templates, puedes engancharte con una prioridad mayor:

```php
add_filter('template_include', function (string $template): string {
    if (is_singular('columna-opinion')) {
        // Tu logica personalizada.
    }
    return $template;
}, 20);
```

## Comportamiento al desinstalar

Cuando el plugin se elimina desde el panel de administracion de WordPress (no al desactivar, solo al eliminar), el archivo `uninstall.php` ejecuta la limpieza completa:

1. **Roles:** Elimina `lc_columnista` y `lc_agencia`.
2. **Capabilities:** Remueve todas las capabilities del plugin de los roles `editor` y `administrator`.
3. **Opciones:** Elimina `lean_columnas_version` y `lean_columnas_settings`.
4. **User meta:** Elimina todos los registros de `lc_social_twitter`, `lc_social_linkedin`, `lc_social_instagram`, `lc_agency_user_id` y `lc_columnist_status` de todos los usuarios.
5. **Post meta:** Elimina todos los registros de post meta asociados a posts de tipo `columna-opinion`.
6. **Posts:** Elimina todos los posts de tipo `columna-opinion` de la base de datos.
7. **Taxonomia:** Elimina todos los terminos de `columna-categoria`.
8. **Rewrite rules:** Limpia las reglas de reescritura.

**Al desactivar** el plugin (sin eliminarlo), solo se limpian las rewrite rules. Los datos se preservan para una posible reactivacion.

## Estructura del proyecto

```
lean-columnas/
  lean-columnas.php          Archivo principal del plugin
  uninstall.php              Limpieza al eliminar
  src/
    Plugin.php               Orquestador principal
    Installer.php            Activacion y upgrades
    Roles.php                Roles y capabilities
    PostType.php             CPT, taxonomia y custom statuses
    UserProfile.php          Campos de perfil de usuario
    Editorial/
      WorkflowManager.php    Flujo editorial y transiciones
      QualityGates.php       Validaciones de calidad
    Schema/
      OpinionArticleSchema.php   JSON-LD para SEO
    Templates/
      TemplateLoader.php     Carga de templates con override desde tema
    Admin/
      AdminPage.php          Paginas de administracion
  templates/                 Templates por defecto del plugin
  assets/
    css/
      frontend.css           Estilos frontend (solo en paginas del CPT)
      admin.css              Estilos admin (solo en paginas del plugin)
  languages/                 Archivos de traduccion
```

## Licencia

GPL-2.0-or-later. Consulta el archivo [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) para mas detalles.
