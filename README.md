# ImpreFast — MVP

Aplicación Laravel 12 / PHP 8.3 para pedidos de impresiones, copias, escaneos y trabajos personalizados con domicilio en San Miguel, El Salvador. La interfaz es mobile-first, no obliga a crear cuenta y guarda archivos fuera de `public`.

## Inicio con Docker

```bash
cp .env.example .env
docker compose build
docker compose run --rm app php artisan key:generate
docker compose up -d db
docker compose run --rm app php artisan migrate --seed
docker compose up -d
```

Abra `http://localhost:8080`. Desarrollo únicamente: `admin@imprefast.test` / `Demo2026!`. El seeder no crea este usuario en producción.

## Operación

```bash
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan test
docker compose exec app php artisan files:purge-expired
docker compose logs --tail=100 app web worker scheduler db
```

Worker y scheduler son servicios. MariaDB no publica puertos. `db_data` y `private_files` son persistentes.

## Telegram administrativo

1. Crea el bot con `@BotFather` y configura en `.env` `TELEGRAM_NOTIFICATIONS_ENABLED=true`, `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME` y un `TELEGRAM_WEBHOOK_SECRET` aleatorio.
2. Define `APP_URL` con la URL HTTPS pública y ejecuta `docker compose exec app php artisan telegram:set-webhook`.
3. En Administración → Configuración pulsa **Conectar Telegram**, abre el bot y presiona Iniciar.
4. El servicio `worker` procesa `notifications,default`; el servicio `scheduler` ejecuta cada minuto los recordatorios. En una instalación sin Docker configura: `* * * * * cd /ruta/proyecto && php artisan schedule:run >> /dev/null 2>&1` y mantén `php artisan queue:work --queue=notifications,default` activo.

Telegram es solo para avisos administrativos. El WhatsApp de clientes no se modifica.

El proveedor inicial es OSRM; en pruebas se usa el fake sin red. El backend recalcula la ruta. Si falla o supera 10 km, el pedido pasa a cotización. Coordenadas, tarifas, WhatsApp, horarios y retención están en `.env.example` y en `settings`, editable en `/admin/configuracion`.

Para producción configure secretos, `APP_ENV=production`, `APP_DEBUG=false`, HTTPS/cookies seguras, WhatsApp real y un proveedor de rutas con SLA. Reemplace la cuenta bancaria ficticia.

## Arquitectura y seguridad

- `RouteCalculatorInterface`, OSRM y fake; `PricingService` centraliza precios.
- Creación transaccional, snapshots históricos de tarifas, cotizaciones, pagos e historial.
- Descargas privadas autenticadas y tokens públicos aleatorios.
- PWA mínima solo cachea portada/offline, nunca pedidos ni datos personales.
- Limpieza diaria mediante `files:purge-expired`.

## Problemas frecuentes

- Composer lento: `docker compose build --no-cache app`.
- Espere que `docker compose ps` indique DB healthy antes de migrar.
- Permisos: `docker compose exec app chown -R www-data:www-data storage bootstrap/cache`.

## Pendiente para v2

Búsqueda/geocodificación visual avanzada, cupos por intervalo, iconos PWA finales, WhatsApp oficial, tarjeta, rastreo y apps nativas están fuera del MVP.
