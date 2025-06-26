# 🛠️ ToolSoft - Sistema de Gestión de Ferretería

## 📋 Descripción

ToolSoft es un sistema web completo para la gestión de una ferretería, desarrollado en PHP con MySQL. Permite la administración de productos, categorías, proveedores, usuarios y ventas, así como una interfaz de cliente para realizar compras.

## ✨ Características Principales

### 🔐 Sistema de Autenticación
- Login dual (Administrador/Cliente)
- Gestión de sesiones seguras
- Protección CSRF
- Timeout automático de sesiones

### 👨‍💼 Panel de Administración
- Gestión de usuarios (clientes y administradores)
- Administración de categorías de productos
- Gestión de proveedores
- Control de inventario de productos
- Historial de ventas
- Generación de facturas

### 🛍️ Interfaz de Cliente
- Catálogo de productos
- Carrito de compras
- Proceso de checkout
- Historial de pedidos
- Gestión de perfil de usuario

### 📧 Sistema de Contacto
- Formulario de contacto
- Envío de emails con PHPMailer

## 🚀 Instalación

### Requisitos Previos
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Servidor web (Apache/Nginx)
- Extensión mysqli habilitada

### Pasos de Instalación

1. **Clonar o descargar el proyecto**
   ```bash
   git clone [URL_DEL_REPOSITORIO]
   cd Prototipo
   ```

2. **Configurar la base de datos**
   - Crear una base de datos llamada `prototipo`
   - Importar el archivo `Base de Datos/Base de Datos.sql`

3. **Configurar la conexión**
   - Editar `conexion.php` con los datos de tu base de datos:
   ```php
   $host = 'localhost';
   $dbname = 'prototipo';
   $username = 'tu_usuario';
   $password = 'tu_contraseña';
   ```

4. **Configurar permisos**
   - Asegurar que el directorio `uploads/` tenga permisos de escritura
   - Configurar permisos para `facturas/` si se usará

5. **Instalar dependencias (opcional)**
   ```bash
   php install_phpmailer.php
   ```

## 📁 Estructura del Proyecto

```
Prototipo/
├── admin/                    # Archivos del panel de administración
│   ├── categories.php       # Gestión de categorías
│   ├── products.php         # Gestión de productos
│   ├── sales_history.php    # Historial de ventas
│   ├── suppliers.php        # Gestión de proveedores
│   └── users.php           # Gestión de usuarios
├── Base de Datos/           # Scripts de base de datos
│   └── Base de Datos.sql    # Estructura y datos iniciales
├── CSS/                     # Archivos de estilos
│   ├── stylesadmin.css     # Estilos del panel admin
│   ├── stylescustomer.css  # Estilos del cliente
│   ├── stylesinterfaz.css  # Estilos de la interfaz principal
│   └── ...                 # Otros archivos CSS
├── uploads/                 # Imágenes de productos
├── facturas/               # Facturas generadas
├── vendor/                 # Dependencias (PHPMailer)
├── admin_dashboard.php     # Panel principal de administración
├── customer_dashboard.php  # Panel del cliente
├── interfaz_prototipo.php  # Página principal
├── logeo_del_prototipo.php # Sistema de login
├── conexion.php           # Configuración de base de datos
└── ...                    # Otros archivos del sistema
```

## 🔧 Configuración

### Variables de Entorno
El proyecto utiliza configuración directa en `conexion.php`. Para producción, se recomienda:

1. Crear un archivo `.env` para variables sensibles
2. Usar un archivo de configuración separado
3. Implementar variables de entorno del servidor

### Configuración de Email
Para el sistema de contacto, configurar en el archivo correspondiente:
- SMTP Server
- Puerto
- Usuario y contraseña
- Email de origen

## 🛡️ Seguridad

### Medidas Implementadas
- ✅ Headers de seguridad (XSS, CSRF, etc.)
- ✅ Validación de entrada de datos
- ✅ Sanitización de datos
- ✅ Protección contra SQL Injection
- ✅ Timeout de sesiones
- ✅ Regeneración de IDs de sesión
- ✅ Tokens CSRF

### Recomendaciones Adicionales
- Usar HTTPS en producción
- Implementar rate limiting
- Configurar firewall del servidor
- Mantener PHP y dependencias actualizadas

## 📊 Base de Datos

### Tablas Principales
- `users`: Usuarios del sistema (admin/clientes)
- `categories`: Categorías de productos
- `product`: Productos del inventario
- `suppliers`: Proveedores
- `sale`: Ventas realizadas
- `saledetail`: Detalles de ventas
- `contact`: Mensajes de contacto
- `supplier_purchases`: Compras a proveedores

## 🎨 Personalización

### Estilos CSS
Los archivos CSS están organizados por funcionalidad:
- `stylesinterfaz.css`: Página principal
- `stylesadmin.css`: Panel de administración
- `stylescustomer.css`: Interfaz de cliente
- `stylescart.css`: Carrito de compras
- Y otros archivos específicos

### Modificaciones
Para personalizar el diseño:
1. Editar los archivos CSS correspondientes
2. Modificar colores, fuentes y layout
3. Ajustar responsive design según necesidades

## 🐛 Solución de Problemas

### Errores Comunes

1. **Error de conexión a base de datos**
   - Verificar credenciales en `conexion.php`
   - Comprobar que MySQL esté ejecutándose

2. **Problemas de sesión**
   - Verificar configuración de PHP
   - Comprobar permisos de directorio temporal

3. **Imágenes no se cargan**
   - Verificar permisos del directorio `uploads/`
   - Comprobar rutas en el código

4. **Emails no se envían**
   - Configurar correctamente PHPMailer
   - Verificar credenciales SMTP

## 📝 Logs y Debugging

### Archivos de Debug
- `debug_customer_session.php`: Para diagnosticar problemas de sesión
- `check-login-session.php`: Verificar estado de login

### Configuración de Errores
En desarrollo, los errores se muestran. Para producción:
```php
ini_set('display_errors', 0);
error_reporting(0);
```

## 🤝 Contribución

Para contribuir al proyecto:
1. Fork el repositorio
2. Crear una rama para tu feature
3. Commit tus cambios
4. Push a la rama
5. Crear un Pull Request

## 📄 Licencia

Este proyecto está bajo licencia MIT. Ver archivo LICENSE para más detalles.

## 👥 Autores

- **Desarrollador Principal**: [Tu Nombre]
- **Fecha de Creación**: 2025
- **Versión**: 1.0.0

## 📞 Soporte

Para soporte técnico o consultas:
- Email: [tu-email@ejemplo.com]
- Documentación: [URL_DOCUMENTACION]

---

**ToolSoft** - Tu ferretería digital de confianza 🛠️ 