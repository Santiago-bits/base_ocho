# CRM BaseOcho

Sistema interno de gestión de clientes: importación de Excels, fichas e historial,
seguimientos, clientes y pagos, plantillas y estadísticas.

Laravel 13 · PHP 8.3 o superior · MySQL/MariaDB. No necesita npm ni compilar nada.

## Correrlo en la compu

Usa SQLite, así que no hace falta instalar una base.

```bash
cd crm
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan crm:usuario tu@email.com "Tu nombre"
php artisan serve
```

Abrir http://localhost:8000. Para correr los tests: `php artisan test`.

## Subirlo a Hostinger (baseocho.com/crm)

Hace falta acceso SSH (se activa en hPanel > Avanzado > Acceso SSH).

**Importante:** el proyecto va **afuera** de `public_html`. Solo la carpeta `public` queda visible en la web.

1. En hPanel, elegir **PHP 8.3 o superior** para el dominio.
2. En hPanel > Bases de datos MySQL, crear una base y un usuario.
3. Poner el contenido de `crm/` en `~/crm_app`, **sin** `vendor/`, `.env` ni `database/database.sqlite`.
   No usar `domains/baseocho.com`: Hostinger marca esa carpeta con `DO_NOT_UPLOAD_HERE`.
4. Por SSH (`--no-scripts` hace falta porque Hostinger desactiva `proc_open`):

```bash
cd ~/crm_app
composer install --no-dev --optimize-autoloader --no-scripts
php artisan package:discover
cp .env.hostinger.example .env
chmod 600 .env
nano .env                      # completar DB_DATABASE, DB_USERNAME y DB_PASSWORD (entre comillas simples)
php artisan key:generate
php artisan migrate --force    # o importar database/crm_baseocho.sql en phpMyAdmin
php artisan config:cache && php artisan route:cache && php artisan view:cache
ln -s ~/crm_app/public ~/domains/baseocho.com/public_html/crm
php artisan crm:usuario leo@ejemplo.com "Leo"
php artisan crm:usuario santiago@ejemplo.com "Santiago"
```

Las credenciales van solo en el `.env` del servidor. Nunca en el código ni en un commit.

5. Abrir https://baseocho.com/crm y entrar.

Controles después de subirlo:

- https://baseocho.com/crm/login carga con estilos y deja entrar.
- https://baseocho.com/ y las páginas públicas siguen funcionando.
- https://baseocho.com/crm/.env **no** muestra nada.

**Si el enlace simbólico no funciona:** borrar `public_html/crm`, copiar ahí el contenido de `crm_app/public`
y en `public_html/crm/index.php` cambiar las rutas `__DIR__.'/../...'` para que apunten a `~/crm_app`.

### Actualizar una versión

Subir los archivos cambiados y, por SSH:

```bash
cd ~/crm_app
composer install --no-dev --optimize-autoloader --no-scripts
php artisan package:discover
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Backups

Toda la información está en la base MySQL. Revisar que los backups de Hostinger la incluyan,
o exportarla seguido desde phpMyAdmin.

## Importar los Excels

1. En **Ajustes**, crear los rubros (también se pueden crear al importar).
2. En **Importar**, elegir el rubro y el archivo (.xlsx, .xls o .csv).
3. Revisar qué es cada columna. El sistema propone un mapeo según el nombre.
4. Si hay columna «Estado envío», indicar a qué estado corresponde cada valor.
5. Revisar los duplicados y elegir **Actualizar** o **Saltear**. Por defecto se saltean.

Se detectan duplicados por teléfono, WhatsApp o email normalizados, aunque estén en distinto formato.
«Actualizar» pisa los datos con lo que trae el Excel, pero lo vacío no borra nada.
«Estado envío» y «Notas» quedan como registro en el historial.

## Reglas de negocio

- **Abonos:** el monto inicial cubre el primer mes. El primer abono vence un mes después del alta,
  siempre el mismo día del mes (o el último día si ese mes es más corto).
- **Tasa de respuesta:** envíos marcados «Respondió» sobre el total de envíos. Lo importado no cuenta.
- **Franjas horarias:** mañana 6–13, tarde 13–20, noche 20–6.
- **Límite de WhatsApp:** cuenta los WhatsApp registrados en el CRM por los dos usuarios. Avisa, pero no bloquea.
- **Estados automáticos:** el primer contacto pasa la empresa a «Contactado», y marcar una respuesta la pasa a «Respondió».
  Pasar a mano a «Respondió» o más adelante marca como respondido el último envío pendiente.

## Usuarios

`php artisan crm:usuario email "Nombre"` crea un usuario, o cambia la contraseña si ya existe.
