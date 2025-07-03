# Módulo de Pagos - Administrador

## Descripción
Este módulo permite al administrador gestionar y visualizar todos los pagos realizados por los clientes en el sistema ToolSoft.

## Características Principales

### 1. Vista de Pagos
- **Ubicación**: `admin/payments.php`
- **Acceso**: Dashboard del administrador → Sección "Pagos"
- **Funcionalidad**: Muestra una tabla con todos los pagos realizados

### 2. Información Mostrada
- **Número de Factura**: Formato INV-YYYYMMDD-XXXX
- **Cliente**: Nombre del cliente
- **Email**: Correo electrónico del cliente
- **Fecha**: Fecha y hora del pago
- **Total**: Monto total de la transacción
- **Acciones**: Ver factura y eliminar pago

### 3. Filtros Disponibles
- **Búsqueda por cliente/email**: Permite buscar por nombre o correo electrónico
- **Filtro por fecha desde**: Fecha de inicio del rango
- **Filtro por fecha hasta**: Fecha de fin del rango
- **Botón "Limpiar"**: Resetea todos los filtros

### 4. Funcionalidades Adicionales
- **Exportar a Excel**: Descarga todos los pagos en formato .xls
- **Ver Factura**: Genera y descarga la factura en PDF
- **Eliminar Pago**: Permite eliminar un pago (con confirmación)

## Archivos Relacionados

### Archivos Principales
- `admin/payments.php` - Vista principal de pagos
- `admin_dashboard.php` - Integración en el dashboard
- `download_invoice.php` - Generación de facturas PDF
- `CSS/stylespayments.css` - Estilos específicos

### Base de Datos
- Tabla `sale` - Información de ventas/pagos
- Tabla `saledetail` - Detalles de productos por venta
- Tabla `users` - Información de clientes

## Estructura de la Base de Datos

### Tabla `sale`
```sql
CREATE TABLE `sale` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `user_id` bigint NOT NULL,
  `admin_id` bigint NOT NULL,
  `date` date DEFAULT NULL,
  `total` decimal(8,2) DEFAULT NULL,
  PRIMARY KEY (`id`)
);
```

### Tabla `sale_detail`
```sql
CREATE TABLE `sale_detail` (
  `id` bigint NOT NULL AUTO_INCREMENT,
  `sale_id` bigint NOT NULL,
  `product_id` bigint NOT NULL,
  `quantity` int NOT NULL,
  `subtotal` decimal(8,2) NOT NULL,
  PRIMARY KEY (`id`)
);
```

## Funcionalidades Técnicas

### 1. Seguridad
- Verificación de sesión de administrador
- Validación de parámetros de entrada
- Prepared statements para prevenir SQL injection

### 2. Generación de Facturas
- Uso de la librería DomPDF
- Formato profesional con logo y datos de la empresa
- Incluye detalles de productos y totales

### 3. Exportación a Excel
- Generación de archivo .xls
- Incluye todos los campos relevantes
- Formato de fecha y moneda apropiado

### 4. Eliminación de Pagos
- Eliminación en cascada (sale_detail primero, luego sale)
- Confirmación antes de eliminar
- Mensajes de éxito/error

## Uso del Sistema

### Para Administradores
1. Acceder al dashboard del administrador
2. Hacer clic en "Pagos" en el menú lateral
3. Utilizar los filtros para buscar pagos específicos
4. Ver facturas haciendo clic en "Ver Factura"
5. Exportar datos usando "Exportar a Excel"
6. Eliminar pagos si es necesario (con precaución)

### Navegación
- **Dashboard**: `admin_dashboard.php?tab=pagos`
- **Vista directa**: `admin/payments.php`
- **Exportar**: `admin/payments.php?export=excel`
- **Ver factura**: `download_invoice.php?invoice=INV-YYYYMMDD-XXXX`

## Personalización

### Estilos CSS
Los estilos están en `CSS/stylespayments.css` y pueden ser modificados para:
- Cambiar colores del tema
- Ajustar tamaños y espaciados
- Modificar responsividad móvil

### Configuración de Facturas
El template de facturas está en `download_invoice.php` en la función `generateInvoiceHTML()`:
- Logo de la empresa
- Información de contacto
- Formato de fecha y moneda
- Estilos del PDF

## Notas Importantes

1. **Eliminación de Pagos**: Esta acción es irreversible y elimina tanto la venta como sus detalles
2. **Generación de Facturas**: Requiere que DomPDF esté instalado via Composer
3. **Filtros**: Los filtros se aplican en tiempo real y pueden combinarse
4. **Responsividad**: La interfaz es responsive y funciona en dispositivos móviles

## Dependencias
- PHP 7.4+
- MySQL 5.7+
- DomPDF (via Composer)
- Librería de conexión a base de datos

## Soporte
Para problemas o mejoras, revisar:
- Logs de errores de PHP
- Conexión a base de datos
- Permisos de archivos
- Configuración de DomPDF 