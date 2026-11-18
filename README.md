# PLANTILLA SALTEÑAS

Esta es la **plantilla base** para trabajar en el sistema de ventas de Salteñas.
Solo contiene la parte visual (navegación y estilos). **NO incluye base de datos ni lógica.**

## 📁 Archivos de la plantilla

| Archivo | Descripción |
|---------|-------------|
| `index.php` | Estructura HTML + barra de navegación |
| `styles.css` | Hoja de estilos con colores y estilos |
| `conexion.php` | Archivo de conexión a la base de datos (para los desarrolladores) |

## 👥 Cómo usar la plantilla

### Para cada compañero:

1. **Descargar o copiar la carpeta `template`** a tu servidor
2. **Abrir `index.php`** en tu editor
3. **Ir al final del archivo** (justo después de `</nav>`)
4. **Escribir tu código** para la sección que le toca
5. **Guardar los cambios**

### La barra de navegación

La barra de arriba ya está lista:

```html
<!-- Botones de navegación -->
<a href="?p=ventas">📄 Ventas</a>
<a href="?p=reportes">📊 Reportes</a>
<a href="?p=clientes">👥 Clientes</a>
<a href="?p=usuarios">👤 Usuarios</a>
```

Cada botón usa `?p=` para llamar a cada sección.

### El CSS

Los estilos están en `styles.css`. No tocarlos a menos que sepas lo que haces.
Los colores principales son:

| Color | Código | Uso |
|-------|--------|-----|
| Naranja | `#E87722` | Botones, texto principal, acento |
| Negro | `#1A1A1A` | Fondo de la barra de navegación |
| Crema | `#FDF0D5` | Fondo del cuerpo |
| Gris | `#2D2D2D` | Texto y detalles |

## 📋 Tareas de cada compañero

### 1. El que hace VENTAS
- Página: `?p=ventas`
- Funciones: registrar ventas, ver detalle de productos, calcular totales

### 2. El que hace REPORTES
- Página: `?p=reportes`
- Funciones: listado de ventas, totales por día, ventas por vendedor

### 3. El que hace CLIENTES
- Página: `?p=clientes`
- Funciones: listado de clientes, agregar nuevo cliente, buscar clientes

### 4. El que hace USUARIOS
- Página: `?p=usuarios`
- Funciones: listado de usuarios, roles, contraseñas, permisos

## 💡 Ejemplo de cómo agregar código

Para **ventas**, el compañero debería poner esto justo después de `</nav>`:

```php
<!-- ====== PÁGINA DE VENTAS ====== -->
<?php if(isset($_GET["p"]) && $_GET["p"] == "ventas"): ?>

    <h1>📄 Registrar Ventas</h1>
    <form>
        <!-- Aquí va el formulario de ventas -->
    </form>

<?php else: ?>

    <!-- Aquí va la página por defecto (inicio) -->
    <h1>Bienvenido</h1>

<?php endif; ?>
```

## ✅ Reglas

- ❌ No modificar `styles.css` sin necesidad
- ✅ Usar los colores definidos
- ✅ Todos los archivos en la misma carpeta
- ✅ El nombre de la base de datos va en `conexion.php`
- ✅ Cada compañero trabaja en su propia carpeta de pruebas

## 🚀 Para probar

1. Subir la carpeta a tu servidor (XAMPP, Laragon, etc.)
2. Abrir el navegador
3. Ir a: `http://localhost/saltenas/template/`

---

Que empiece el trabajo! 🚀

