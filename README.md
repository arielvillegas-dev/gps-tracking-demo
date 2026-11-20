# GPS Tracking & Route Visualization Demo

Aplicación web y API en **PHP 8.3 + Laravel 13** enfocada en procesamiento geoespacial de telemetría GPS y **visualización interactiva en mapa** de rutas planificadas vs. recorridos reales.

> **Nota:** Demostración técnica independiente con coordenadas y recorridos 100 % sintéticos.

## 1. Problema que resuelve
Permite visualizar de manera clara el comportamiento de una unidad GPS sobre un corredor: compara la ruta planificada frente a la traza real, filtra lecturas con ruido o saltos imposibles, detecta paradas y excesos de velocidad, y ofrece controles interactivos de reproducción paso a paso.

## 2. Tecnologías utilizadas
- **Backend / API:** PHP `^8.3`, Laravel `^13`, Eloquent ORM, SQLite.
- **Visualización:** Leaflet (`OpenStreetMap`) + visor vectorial SVG sincronizado (disponible incluso sin conexión a tiles externos).
- **Pruebas:** PHPUnit `^12.5` y Playwright (`@playwright/test`).

## 3. Arquitectura general
- `app/Services/GpsRouteProcessorService.php`: Cálculo Haversine, filtrado por precisión (`<= 45 m`) y velocidad implícita (`<= 180 km/h`), detección de paradas (`<= 2 km/h`), alertas de velocidad y desviación respecto a la ruta planificada.
- `app/Http/Controllers/GpsTrackingController.php`: Endpoints REST `v1` y controlador web del visor.
- `resources/views/map.blade.php`: Mapa Leaflet + visor vectorial SVG + controles de reproducción (`Play`, `Next Point`, `Reset`, `Send Live GPS Ping`).

## 4. Instrucciones para ejecutarlo

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Abrir `http://127.0.0.1:8000` en el navegador.

## 5. Pruebas disponibles
```bash
php artisan test
npm install
npm run test:e2e
```

## 6. Contribución real al desarrollo
Arquitectura de telemetría, algoritmo de filtrado/detección y diseño del visor creados por Carlos Ariel Villegas Flores como demostración técnica independiente, con apoyo de herramientas de IA para generar scaffolding y pruebas verificadas con PHPUnit y Playwright.
