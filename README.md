# Cargo — Grup Tous

Logística de viajes de materiales para consorcios: registro de viajes,
confirmación por vale y liquidación de cuentas.

Reemplaza los Excel de relación de viajes.

## Puesta en marcha

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Crear la base en Postgres y ajustar credenciales en .env
php artisan migrate
php artisan db:seed --class=DatosRealesSeeder

# En dos terminales, o con: composer dev
php artisan serve
npm run dev
```

Entra en http://localhost:8000 con `admin@gruptous.co` o
`digitacion@gruptous.co` (la clave está en el seeder: **cámbiala**).

## Cómo está armado

```
app/
  Enums/          Estados y tipos como enums de PHP
  Models/         Eloquent. El bloqueo por liquidación vive en Viaje::booted()
  Actions/        La lógica de negocio, una clase por operación
  Exceptions/     Errores de dominio con su código HTTP
  Http/           Controladores delgados, form requests y resources
  Policies/       Quién puede anular, cerrar cuentas y cambiar tarifas

resources/js/
  lib/            Cliente de API y formato de cifras y fechas
  stores/         Sesión (Pinia) y avisos
  router/         Rutas del front y guardia de sesión
  layouts/        La barra lateral
  components/     Piezas compartidas
  views/          Una carpeta por módulo

routes/api.php    Toda la API
routes/web.php    Una sola vista: el resto lo maneja Vue Router
```

El front es una SPA de Vue 3 servida por el mismo Laravel y autenticada con
tokens de Sanctum. No hay CORS que resolver porque es el mismo dominio.

## Los módulos

| Módulo | Qué hace |
|---|---|
| Inicio | La cola de trabajo: qué viajes esperan vale y desde cuándo |
| Viajes | Listado con filtros y registro **en lote** |
| Vales | Registrar el vale y confirmar de una vez los viajes que ampara |
| Tarifas | Precios por ruta, con vigencia |
| Liquidaciones | Cuentas de cobro y de pago, con anticipos |
| Catálogos | Terceros, volquetas, canteras, botaderos, materiales, consorcios, obras |

## Las reglas que no se pueden romper

1. **Los valores se congelan en el viaje.** Al registrarlo se copian los de la
   tarifa vigente. Los reportes leen del viaje, nunca hacen join contra
   tarifas: si no, subir un precio reescribiría cuentas ya cobradas.
2. **La confirmación es atómica.** Vale + viajes + m³ + estado, en una
   transacción.
3. **Un viaje liquidado no se toca.** Lo impide el modelo, no el controlador.
4. **La plata en enteros de pesos.** Nunca float.
5. **Las reglas condicionales también viven en Postgres**, con `CHECK`.

Detalle en `ARQUITECTURA.md`.

## Pruebas

```bash
php artisan test
```

Corren contra Postgres (`sistema_viajes_test`), no sqlite: las reglas críticas
dependen de `CHECK` constraints y de un índice único parcial.

## Pendiente

- Reportes: margen por obra, viajes por conductor, desviación de m³.
- Exportar a PDF las cuentas de pago y de material (la de cobro ya sale).
- El logo (hoy hay un marcador). Los iconos viven en `resources/js/components/Icono.vue`.
