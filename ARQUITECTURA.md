# Cómo está armado el código

## El recorrido de un viaje

```
1. Se registra              RegistrarViajes      estado: por_confirmar, sin vale
      ↓                     (congela las tarifas del día)
2. Llega el vale            ConfirmarViajesPorVale   estado: confirmado, m3_confirmado
      ↓                     (una sola transacción)
3. Entra en una cuenta      GenerarLiquidacion       liquidacion_pago_id / _cobro_id
      ↓
4. Se cierra la cuenta      CerrarLiquidacion        el viaje queda bloqueado
```

## Dónde vive cada cosa

```
app/Enums/          Los estados y tipos, como enums de PHP en vez de strings sueltos
app/Models/         Eloquent. El bloqueo por liquidación vive en Viaje::booted()
app/Actions/        La lógica de negocio, una clase por operación
app/Exceptions/     Tres errores de dominio, cada uno con su código HTTP
app/Http/           Controladores delgados, form requests y resources
app/Policies/       Quién puede anular, cerrar cuentas y cambiar tarifas
```

Un controlador no calcula plata. Recibe, valida, llama a la Action y responde.

## Las cuatro decisiones que sostienen todo

**1. Los valores se congelan en el viaje.**
`RegistrarViajes` resuelve las tarifas y copia los valores a las columnas del
viaje. Los reportes leen esas columnas; nunca hacen join contra `tarifas`. Si
se calculara al vuelo, subir un precio reescribiría los totales de cuentas ya
cobradas.

**2. La tarifa se resuelve por ruta.**
`ResolverTarifa` busca (obra + cantera + material) y, si no hay, (obra +
cantera). Lo que manda es la distancia del recorrido, no el material — por eso
basta una tarifa por ruta en vez de una por cada material. Un retiro se
resuelve por (obra + botadero).

Cuando no encuentra nada **lanza**, no devuelve cero: un viaje guardado en
cero se paga en cero y nadie lo nota hasta la liquidación.

**3. La confirmación es atómica.**
`ConfirmarViajesPorVale` crea el vale y confirma sus viajes dentro de una
transacción. Si se partiera quedarían viajes confirmados sin respaldo, y
confirmado es lo que se paga.

**4. El bloqueo está en el modelo y en la base.**
`Viaje::booted()` impide editar o borrar un viaje liquidado, venga la orden de
donde venga: un controlador, un comando de consola, una corrección masiva. Y
por debajo, Postgres tiene `CHECK` constraints que rechazan un confirmado sin
vale y un suministro con botadero, aunque la aplicación se salte.

## Los dos precios

Cada viaje lleva dos valores con su unidad, porque son negocios distintos:

| | Al consorcio | Al dueño | Al conductor propio |
|---|---|---|---|
| Unidad | por m³ | por viaje | porcentaje |
| Ejemplo | 25.000 × 14 = 350.000 | 200.000 | 15% de 200.000 = 30.000 |

La diferencia es el margen, que es de donde vive la empresa. Un solo campo
`valor` no permitiría calcularlo.

Del conductor se guardan los tres componentes (base, porcentaje y resultado),
no solo el total: así un pago viejo siempre se puede explicar.

## La plata

Enteros de pesos, sin decimales, en todo el sistema. El único `decimal` es el
porcentaje del conductor. Nunca float: las cuentas tienen que cuadrar exactas
contra los Excel.
