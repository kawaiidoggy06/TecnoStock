# TecnoStock – Sistema de inventario

Aplicación web para gestionar el catálogo de productos de **TecnoStock** y controlar sus entradas y salidas de existencias.
Desarrollada con **HTML5 + Bootstrap 5, JavaScript, PHP 8 (PDO) y MySQL**, sin frameworks, para ejecutarse en un servidor local con XAMPP.

> *Asignatura:* Sistemas de Información - Caso TecnoStock
> *Estudiante:* Tomas Alvarez, Martin Riega
> *Video explicativo:* _xxxxxx_

---

## Índice
1. [Instalación y ejecución](#1-instalación-y-ejecución)
2. [Análisis del caso](#2-análisis-del-caso)
3. [Requerimientos funcionales y trazabilidad](#3-requerimientos-funcionales-y-trazabilidad)
4. [Reglas de negocio](#4-reglas-de-negocio)
5. [Modelo de datos](#5-modelo-de-datos)
6. [Estructura del proyecto](#6-estructura-del-proyecto)
7. [Seguridad y calidad](#7-seguridad-y-calidad)
8. [Casos de prueba](#8-casos-de-prueba)

---

## 1. Instalación y ejecución

### Requisitos
- [XAMPP](https://www.apachefriends.org/) con PHP 8 (incluye Apache, MySQL/MariaDB y phpMyAdmin)
- Navegador web actualizado
- Conexión a internet (Bootstrap se carga desde CDN)

### Pasos
1. Copiar la carpeta del proyecto en `C:\xampp\htdocs\tecnostock`.
2. Abrir el **XAMPP Control Panel** e iniciar **Apache** y **MySQL**.
3. Entrar a `http://localhost/phpmyadmin` → pestaña **Importar** → seleccionar `database/tecnostock.sql` → **Importar**.
   El script crea la base `tecnostock`, sus 4 tablas y los datos de prueba. Se puede volver a importar para dejar la base en su estado inicial.
4. Abrir `http://localhost/tecnostock`.

### Usuario de prueba
| Correo | Contraseña |
|---|---|
| `admin@tecnostock.cl` | `admin123` |

### Configuración de la conexión
Los datos de conexión están en `conexion.php`. Por defecto usan el usuario `root` sin contraseña, como viene XAMPP. Si tu MySQL tiene otra clave, cámbiala ahí.

---

## 2. Análisis del caso

### Problema
TecnoStock, emprendimiento que vende accesorios tecnológicos, registra sus productos en una planilla. Eso ha provocado **códigos duplicados**, **errores en las cantidades disponibles** y **poca claridad sobre el stock real**.

### Objetivo
Centralizar el catálogo y los movimientos de inventario en una aplicación web sencilla, que conserve la integridad de los datos y sea fácil de usar para una persona sin conocimientos técnicos.

### Actor
| Usuario | Responsabilidad |
|---|---|
| Encargado de inventario | Inicia sesión y administra productos, categorías y movimientos de existencias. |

### Alcance
**Incluye:** inicio de sesión; registro, consulta, búsqueda, edición y desactivación de productos; administración de categorías; registro de entradas y salidas con actualización automática del stock; historial de movimientos; alerta de stock bajo.

**No incluye:** ventas o facturación, proveedores, registro de nuevos usuarios desde la interfaz, múltiples roles ni reportes exportables.

### Cómo resuelve el problema
| Problema original | Solución implementada |
|---|---|
| Códigos duplicados | Código `UNIQUE` en la base de datos + validación en PHP. |
| Cantidades erróneas | El stock solo cambia mediante movimientos registrados, dentro de transacciones, y nunca puede quedar negativo. |
| Poca claridad del stock real | Listado con stock actual y mínimo, alerta de stock bajo en el panel e historial de movimientos con su responsable. |

---

## 3. Requerimientos funcionales y trazabilidad

| ID | Requerimiento (del caso) | Archivo(s) | Pruebas |
|---|---|---|---|
| RF1 | Iniciar sesión con un usuario registrado | `index.php`, `includes/auth.php`, `logout.php` | P01–P04 |
| RF2 | Registrar productos (código, nombre, categoría, descripción, precio, stock actual y mínimo) | `producto_form.php` | P11–P13 |
| RF3 | Consultar el listado de productos | `productos.php` | P07 |
| RF4 | Buscar productos por nombre, código o categoría | `productos.php` | P08–P10 |
| RF5 | Modificar precio, descripción, categoría y cantidades | `producto_form.php` | P14 |
| RF6 | Desactivar productos que ya no se comercializan | `producto_estado.php` | P15–P16 |
| RF7 | Registrar entradas y salidas de existencias | `movimientos.php` | P17–P22 |
| RF8 | Alerta cuando el stock actual es menor que el mínimo | `panel.php`, `productos.php`, `movimientos.php` | P05–P06, P20 |
| RF9 | Administrar categorías (indicado en "Usuarios y alcance") | `categorias.php` | P23–P26 |

---

## 4. Reglas de negocio

Cada regla se valida en **más de una capa**: JavaScript avisa al instante, PHP la vuelve a revisar (porque el JS se puede saltar) y la base de datos actúa como último respaldo.

| # | Regla | JavaScript | PHP | MySQL |
|---|---|---|---|---|
| RN1 | El código de producto no puede repetirse | — | Consulta previa + captura del error 23000 | `UNIQUE (codigo)` |
| RN2 | Precio, stock actual, stock mínimo y cantidad no pueden ser negativos | `min="0"` / `min="1"` | `ctype_digit` y cantidad `> 0` | `CHECK (... >= 0)`, `CHECK (cantidad > 0)` |
| RN3 | Todo movimiento registra fecha, tipo, cantidad, producto y usuario | Campos obligatorios | El usuario se toma de la **sesión**, no del formulario | `NOT NULL`, `DEFAULT CURRENT_TIMESTAMP`, FK a producto y usuario |
| RN4 | Una salida no puede dejar el stock bajo cero | Compara la cantidad con el stock del producto elegido | `SELECT ... FOR UPDATE` y comparación con el stock real | `CHECK (stock_actual >= 0)` |
| RN5 | Un producto con movimientos se desactiva, no se elimina | Confirmación antes de desactivar | Solo existe la acción de cambiar `estado` | `ON DELETE RESTRICT` en `movimiento.id_producto` |
| RN6 | El stock se actualiza automáticamente tras cada movimiento válido | — | `UPDATE` del stock + `INSERT` del movimiento en una **transacción** | InnoDB (transacciones) |

**Reglas adicionales justificadas**
- **El código no se edita** después de creado, para no romper la trazabilidad del historial.
- **Un cambio manual de stock** (al editar un producto) o **el stock inicial** (al crearlo) generan automáticamente un movimiento de ajuste, así el historial siempre cuadra con el stock.
- **No se puede desactivar una categoría con productos activos**, y los nombres de categoría no se repiten.

---

## 5. Modelo de datos

```
 usuario (1) ───────────────< movimiento >─────────────── (1) producto >──────── (1) categoria
                 registra (N)                (N) afecta                (N) pertenece
```

| Tabla | Atributos | Claves y restricciones |
|---|---|---|
| `usuario` | id_usuario, nombre, correo, clave, estado | PK `id_usuario` · `UNIQUE (correo)` · clave guardada con `password_hash` |
| `categoria` | id_categoria, nombre, descripcion, estado | PK `id_categoria` · `UNIQUE (nombre)` |
| `producto` | id_producto, codigo, nombre, descripcion, precio, stock_actual, stock_minimo, estado, id_categoria | PK · `UNIQUE (codigo)` · `CHECK` de no negativos · FK → `categoria` |
| `movimiento` | id_movimiento, fecha_hora, tipo, cantidad, observacion, id_producto, id_usuario | PK · `tipo ENUM('ENTRADA','SALIDA')` · `CHECK (cantidad > 0)` · FK → `producto` y `usuario` con `ON DELETE RESTRICT` |

**Normalización (3FN):** las categorías van en su propia tabla y no como texto repetido en cada producto. Cada movimiento guarda solo las claves foráneas del producto y del usuario, no copias de sus datos. Ningún atributo depende de otro que no sea la clave primaria.

**Tipos de datos:** `DECIMAL(10,0)` para precios en pesos chilenos, `INT` para cantidades, `DATETIME` para la fecha y hora, y `TINYINT(1)` para el estado (1 = activo, 0 = inactivo). Todas las tablas usan el motor **InnoDB**, que soporta claves foráneas y transacciones.

El script completo y reproducible está en [`database/tecnostock.sql`](database/tecnostock.sql).

---

## 6. Estructura del proyecto

```
tecnostock/
├── index.php            Inicio de sesión
├── panel.php            Panel de inicio: resumen y alerta de stock bajo
├── productos.php        Listado, búsqueda y filtros de productos
├── producto_form.php    Registro y edición de productos
├── producto_estado.php  Desactivar / reactivar productos
├── movimientos.php      Registro de entradas/salidas e historial
├── categorias.php       Administración de categorías
├── logout.php           Cierre de sesión
├── conexion.php         Conexión PDO a MySQL
├── includes/
│   ├── auth.php         Protección de páginas privadas y funciones de ayuda
│   ├── header.php       Encabezado, menú y mensajes
│   └── footer.php       Cierre de página y scripts
├── assets/
│   ├── css/estilo.css   Estilos propios sobre Bootstrap
│   └── js/validaciones.js  Validaciones del cliente y confirmaciones
└── database/
    └── tecnostock.sql   Script de creación y datos de prueba
```

---

## 7. Seguridad y calidad

- **Consultas preparadas (PDO)** en todas las consultas con datos del usuario, para evitar la inyección SQL.
- **Contraseñas con `password_hash` / `password_verify`**: nunca se guardan en texto plano.
- **Escape de salida con `htmlspecialchars`** (función `e()`), para evitar XSS.
- **Sesiones:** `session_regenerate_id()` al iniciar sesión, y todas las páginas privadas verifican la sesión.
- **Acciones que modifican datos solo por POST**: un link no puede desactivar productos.
- **Transacciones** en las operaciones que tocan el stock y el historial a la vez.
- **Manejo de errores:** los errores técnicos se registran con `error_log` y al usuario se le muestran mensajes claros. Si MySQL está apagado, se muestra un aviso en vez del error de PHP.
- **Validación en cliente y servidor** en todos los formularios.

---

## 8. Casos de prueba

Pruebas manuales ejecutadas sobre los datos de prueba del script SQL.
Tipos: **N** = flujo normal · **E** = error esperado · **L** = caso límite.

| ID | Tipo | Caso | Datos de entrada | Resultado esperado | Resultado |
|---|---|---|---|---|---|
| P01 | N | Login correcto | admin@tecnostock.cl / admin123 | Ingresa al panel | ✅ |
| P02 | E | Login con clave incorrecta | admin@tecnostock.cl / 1234 | "Correo o contraseña incorrectos" | ✅ |
| P03 | E | Login con campos vacíos | — | Campos en rojo, no se envía | ✅ |
| P04 | E | Acceso sin sesión | URL `panel.php` | Redirige al login | ✅ |
| P05 | N | Resumen del panel | — | 6 productos activos, 3 con stock bajo | ✅ |
| P06 | N | Alerta de stock bajo | — | Lista Pendrive (0/4), Parlante (3/5) y Cable USB-C (8/10) | ✅ |
| P07 | N | Listado de productos | — | Filas con stock bajo marcadas en rojo | ✅ |
| P08 | N | Búsqueda por categoría | `audio` | Audífonos y Parlante | ✅ |
| P09 | N | Búsqueda por código | `CAR-` | Los 2 productos de Cargadores | ✅ |
| P10 | N | Filtro "Solo stock bajo" | — | Los 3 productos bajo el mínimo | ✅ |
| P11 | N | Registrar producto | TEC-001, Teclado, $15.990, stock 10, mínimo 3 | Se guarda y aparece en el listado | ✅ |
| P12 | E | Código duplicado (RN1) | AUD-001 | "Ya existe un producto con ese código" | ✅ |
| P13 | E | Precio negativo (RN2) | precio −5 | Campo en rojo, no se guarda | ✅ |
| P14 | N | Editar stock | Pendrive: 0 → 10 | Sale de la alerta y queda un ajuste en el historial | ✅ |
| P15 | N | Desactivar producto (RN5) | Teclado | Pide confirmación y deja de aparecer | ✅ |
| P16 | N | Ver y reactivar inactivos | "Ver inactivos" | Aparece en gris y se puede reactivar | ✅ |
| P17 | N | Historial de movimientos | — | Muestra fecha, tipo, producto, cantidad y usuario | ✅ |
| P18 | E | Salida mayor al stock (RN4) | Parlante (stock 3), salida 10 | "Stock insuficiente: solo hay 3" | ✅ |
| P19 | N | Entrada (RN6) | Parlante, entrada 10 | El stock queda en 13 | ✅ |
| P20 | L | Salida que deja el stock bajo el mínimo | Cargador (40), salida 31 | Stock 9, aviso amarillo y aparece en la alerta | ✅ |
| P21 | E | Cantidad cero (RN2) | cantidad 0 | No permite enviar | ✅ |
| P22 | N | Acceso rápido "± Stock" | Cualquier producto | Abre Movimientos con el producto elegido | ✅ |
| P23 | N | Registrar categoría | Gamer | Se crea activa | ✅ |
| P24 | E | Categoría duplicada | Audio | "Ya existe una categoría con ese nombre" | ✅ |
| P25 | E | Desactivar categoría con productos | Audio | Se bloquea la acción | ✅ |
| P26 | N | Desactivar categoría vacía | Gamer | Se desactiva y no aparece al crear productos | ✅ |
| P27 | E | Borrar producto con movimientos desde la BD (RN5) | DELETE en phpMyAdmin | MySQL lo rechaza por la FK | ✅ |
| P28 | E | Stock negativo directo en la BD (RN4) | `UPDATE ... stock_actual = -1` | MySQL lo rechaza por el CHECK | ✅ |
| P29 | N | Diseño responsivo | Ventana angosta / vista móvil | Menú colapsable y tablas desplazables | ✅ |

Las capturas de evidencia están en la carpeta [`docs/evidencias/`](docs/evidencias/).
