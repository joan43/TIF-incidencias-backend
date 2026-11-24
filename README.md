[![Backend CI](https://github.com/joan43/TIF-incidencias-backend/actions/workflows/ci.yml/badge.svg)](https://github.com/joan43/TIF-incidencias-backend/actions/workflows/ci.yml)

# TIF — Sistema de Gestión de Incidencias Técnicas (Backend)

API REST independiente para el Trabajo Integrador Final de Desarrollo de Software 2026. Implementa autenticación con Laravel Sanctum, roles `usuario` y `tecnico`, persistencia MySQL/MariaDB y respuestas JSON. No construye la interfaz web ni utiliza Blade para el frontend.

## Tecnologías y requisitos

- PHP 8.4 o compatible con `composer.json`.
- Laravel 13 y Laravel Sanctum 4.
- Composer 2.
- MySQL o MariaDB (por ejemplo, el servidor incluido con XAMPP).
- Postman para ejecutar la colección incluida en `postman/`.

## Instalación en Windows (CMD)

1. Descomprimí el backend en una carpeta independiente del frontend y abrí CMD en esa carpeta.
2. Comprobá que se esté utilizando PHP 8.4:

   ```bat
   C:\php84\php.exe -v
   where php
   ```

   Usá `C:\php84\php.exe` para ejecutar los comandos de Artisan y verificá que `where php` muestre esa versión primero, así Composer también utiliza PHP 8.4. Deben estar habilitadas las extensiones habituales de Laravel: `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml` y `zip`.

3. Instalá las dependencias:

   ```bat
   composer install
   ```

4. Creá la configuración local a partir del ejemplo:

   ```bat
   copy .env.example .env
   ```

5. En MySQL/MariaDB, creá una base vacía llamada `tif_incidencias`. Por ejemplo, desde phpMyAdmin o desde la consola SQL:

   ```sql
   CREATE DATABASE tif_incidencias CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

6. Revisá el archivo `.env` y ajustá `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD` según tu servidor local. No compartas el `.env` ni guardes sus credenciales en Git.
7. Generá la clave de Laravel y ejecutá las migraciones más los datos iniciales:

   ```bat
   C:\php84\php.exe artisan key:generate
   C:\php84\php.exe artisan migrate --seed
   ```

   El seeder crea las cinco categorías y las dos cuentas de prueba indicadas abajo. La migración de reparación también completa tablas creadas por la versión inicial incompleta del proyecto.


8. Iniciá el servidor de desarrollo:

   ```bat
   C:\php84\php.exe artisan serve --host=127.0.0.1 --port=8000
   ```

La API queda disponible en `http://127.0.0.1:8000/api`. Para comprobar que Laravel responde, abrí `http://127.0.0.1:8000/` (devuelve JSON) o `http://127.0.0.1:8000/up`.

> **Importante:** `C:\php84\php.exe artisan migrate:fresh --seed` elimina y vuelve a crear todas las tablas. Usalo únicamente si querés reiniciar la base y no necesitás conservar los datos. En una base nueva alcanza con `php artisan migrate --seed`.

## Usuarios de prueba

Ambos usuarios se generan con `php artisan migrate --seed`:

| Rol | Email | Contraseña |
|---|---|---|
| Usuario | `usuario@test.com` | `password123` |
| Técnico | `tecnico@test.com` | `password123` |

Son credenciales **solo para desarrollo y demostración**; no deben utilizarse en producción.

## Probar con Postman

1. Importá `postman/TIF-Incidencias-API.postman_collection.json` en Postman.
2. Verificá que `baseUrl` sea `http://127.0.0.1:8000`.
3. Ejecutá las solicitudes en orden, empezando por **01 - Login como usuario**. Los scripts de la colección guardan el token, la categoría y el identificador de la incidencia automáticamente.
4. La secuencia crea una incidencia y un comentario como usuario; luego inicia sesión como técnico, toma la incidencia, agrega otro comentario y registra su resolución.

Para probar una solicitud protegida manualmente, copiá el token de `POST /api/login` y enviá la cabecera:

```http
Authorization: Bearer TU_TOKEN
Accept: application/json
Content-Type: application/json
```

## Endpoints

Todos los endpoints requieren `Authorization: Bearer ...` excepto `POST /api/login`. Los errores se devuelven en JSON. Los endpoints están prefijados con `/api` por Laravel.

| Método | Endpoint | Función |
|---|---|---|
| `POST` | `/api/login` | Validar credenciales y emitir token de Sanctum. |
| `POST` | `/api/logout` | Revocar el token usado. |
| `GET` | `/api/user` | Obtener el usuario autenticado y su rol. |
| `GET` | `/api/categorias` | Listar categorías precargadas (`/api/categories` también funciona). |
| `GET` | `/api/incidencias` | Listar incidencias visibles para el usuario, con filtros. |
| `POST` | `/api/incidencias` | Crear una incidencia en estado `ABIERTA`. |
| `GET` | `/api/incidencias/{id}` | Obtener detalle, responsables, resolución y comentarios. |
| `PUT` | `/api/incidencias/{id}/tomar` | Un técnico toma una incidencia `ABIERTA`. |
| `PUT` | `/api/incidencias/{id}/resolver` | El técnico asignado la resuelve con una descripción obligatoria. |
| `GET` | `/api/incidencias/{id}/comentarios` | Listar comentarios cronológicamente. |
| `POST` | `/api/incidencias/{id}/comentarios` | Agregar un comentario. |

### Ejemplos de JSON

**Login** — `POST /api/login`

```json
{
  "email": "usuario@test.com",
  "password": "password123"
}
```

La respuesta contiene `token`, `token_type` (`Bearer`) y `user` (`id`, `name`, `email`, `role`). Usá `token` como Bearer en las siguientes solicitudes.

**Crear incidencia** — `POST /api/incidencias`

```json
{
  "title": "La computadora no enciende",
  "description": "La computadora del aula 2 no enciende desde esta mañana.",
  "category_id": 1
}
```

El servidor ignora cualquier `status`, `user_id`, `technician_id` o `resolution` que se intente enviar en el alta: asigna el usuario autenticado y fuerza el estado `ABIERTA`. Como comodidad, también acepta `titulo`, `descripcion` y `categoria_id`.

**Resolver incidencia** — `PUT /api/incidencias/{id}/resolver`

```json
{
  "resolution": "Se reconectó el cable de alimentación y se verificó el inicio correcto."
}
```

También se admite la clave `resolucion`.

**Comentar** — `POST /api/incidencias/{id}/comentarios`

```json
{
  "comment": "La falla empezó hoy por la mañana."
}
```

También se admite `comentario`.

### Filtros de listado

`GET /api/incidencias` acepta:

- `status` o `estado`: `ABIERTA`, `EN PROCESO` o `RESUELTA`.
- `category_id` o `categoria_id`: ID de una categoría existente.
- `category`: ID o nombre de categoría, por ejemplo `Hardware`.
- `per_page`: tamaño de página entre 1 y 100 (por defecto 15).
- `page`: número de página.

La respuesta del listado tiene `data` (arreglo de incidencias) y `meta` con la información de paginación. Los detalles y las operaciones de escritura devuelven `data` con el recurso afectado.

## Reglas implementadas

- Un usuario común ve solo las incidencias que creó; un técnico puede verlas todas.
- El backend verifica los permisos aunque se intente llamar un endpoint directamente desde Postman.
- Las categorías iniciales son Hardware, Software, Red, Impresoras y Otros.
- El estado inicial siempre es `ABIERTA`; solo un técnico puede tomar una incidencia abierta y pasa a `EN PROCESO`.
- Solo el técnico asignado puede resolverla, y la resolución debe tener una descripción.
- Los comentarios guardan incidencia, autor, texto y fecha; se muestran en orden cronológico.
- El login devuelve `401` para credenciales incorrectas, la validación devuelve `422`, falta de autenticación devuelve `401`, falta de permisos `403`, recurso inexistente `404` y transiciones incompatibles `409`.
- CORS está habilitado para desarrollo local mediante `CORS_ALLOWED_ORIGINS`. Antes de desplegar, reemplazá `*` por el origen exacto del frontend.

## Integrar el frontend separado

No copies HTML ni JavaScript dentro del proyecto Laravel. El frontend debe realizar solicitudes AJAX a `http://127.0.0.1:8000/api/...`, procesar el JSON y enviar el token en `Authorization: Bearer ...` después del login. Si usás Live Server, Vite u otro puerto/origen, ajustá `CORS_ALLOWED_ORIGINS` en `.env` (por ejemplo `http://127.0.0.1:5500`) y ejecutá `C:\php84\php.exe artisan config:clear`.

## Comprobaciones útiles

```bat
C:\php84\php.exe artisan route:list --path=api
C:\php84\php.exe artisan migrate:status
C:\php84\php.exe artisan test
```

Las pruebas automatizadas de `tests/Feature/Api` cubren autenticación, visibilidad por rol, transiciones de estado y comentarios. Ejecutalas después de instalar Composer y configurar el proyecto.
