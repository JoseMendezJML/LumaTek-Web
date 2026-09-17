# LUM-27 – Esquema de Seguridad de LumaTek

## 1. Objetivo

Definir los mecanismos de seguridad que utilizará LumaTek para proteger la confidencialidad, integridad y disponibilidad de la información almacenada y transmitida por la plataforma.

LumaTek manejará información perteneciente a diferentes empresas, por lo que se deberá garantizar que cada usuario únicamente pueda acceder a la información correspondiente a su empresa y a las funciones permitidas por su rol.

---

## 2. Autenticación

LumaTek utilizará diferentes mecanismos de autenticación dependiendo del tipo de cliente que acceda al sistema.

### Aplicación web

La aplicación web desarrollada con Laravel utilizará autenticación basada en sesiones seguras.

Al iniciar sesión correctamente, Laravel creará una sesión asociada al usuario autenticado.

Las cookies de sesión deberán configurarse con las siguientes medidas:

- `HttpOnly` para evitar acceso mediante JavaScript.
- `Secure` en ambientes con HTTPS.
- `SameSite` para reducir ataques CSRF.
- Regeneración del identificador de sesión después del inicio de sesión.

### API y futura aplicación móvil

Para la futura API REST y aplicación móvil se utilizará autenticación mediante tokens.

El esquema contemplará el uso de JWT (JSON Web Token), permitiendo autenticar las solicitudes realizadas desde clientes externos sin utilizar sesiones del navegador.

El token deberá contener únicamente la información necesaria para identificar al usuario y tendrá un tiempo de expiración definido.

---

## 3. Protección de contraseñas

Las contraseñas nunca serán almacenadas en texto plano.

Laravel utilizará el algoritmo de hash **bcrypt** para proteger las contraseñas.

Ejemplo conceptual:

Usuario escribe contraseña
→ Laravel aplica bcrypt
→ Se almacena únicamente el hash
→ La contraseña original no se guarda

Las contraseñas deberán cumplir como mínimo con:

- 8 caracteres.
- Combinación de letras y números.
- Validación antes de crear o actualizar la contraseña.
- Confirmación de contraseña durante el registro y cambio de contraseña.

---

## 4. Comunicación segura

Cuando LumaTek sea desplegado en producción deberá utilizar:

**HTTPS mediante TLS**

Esto permitirá cifrar la comunicación entre:

- Navegador y servidor.
- Aplicación móvil y API.
- Clientes externos y servicios de LumaTek.

Las credenciales y tokens nunca deberán enviarse mediante conexiones HTTP sin cifrado.

---

## 5. Roles y permisos

LumaTek manejará diferentes niveles de acceso.

### Superadministrador de LumaTek

Responsable de administrar la plataforma.

Permisos principales:

- Administrar empresas registradas.
- Administrar planes de servicio.
- Consultar métricas generales de la plataforma.
- Gestionar incidencias de servicio.
- Administrar configuraciones generales.

El acceso a información privada de una empresa deberá estar restringido y únicamente permitirse cuando exista una función administrativa o de soporte autorizada.

### Administrador de empresa

Es el responsable principal de una empresa registrada.

Permisos:

- Administrar información de su empresa.
- Crear y administrar usuarios internos.
- Registrar invernaderos.
- Crear zonas.
- Configurar dispositivos y sensores.
- Configurar límites de monitoreo.
- Consultar lecturas y alertas.
- Consultar reportes.
- Gestionar eventos de riego.

### Empleado

Usuario perteneciente a una empresa con permisos limitados.

Podrá:

- Consultar invernaderos autorizados.
- Consultar sensores y lecturas.
- Consultar alertas.
- Confirmar atención de alertas cuando tenga permiso.
- Registrar determinadas acciones operativas.

No podrá:

- Administrar empresas.
- Crear administradores.
- Modificar planes.
- Acceder a información de otras empresas.
- Cambiar configuraciones críticas sin autorización.

---

## 6. Aislamiento de información por empresa

LumaTek utilizará un modelo multiempresa.

Cada usuario estará relacionado con una empresa mediante:

`users.company_id`

Las consultas realizadas por un usuario deberán limitarse a los registros pertenecientes a su empresa.

Ejemplo:

Empresa A → Invernaderos A → Zonas A → Sensores A

Empresa B → Invernaderos B → Zonas B → Sensores B

Un usuario de Empresa A no podrá consultar, modificar o eliminar información perteneciente a Empresa B.

La validación deberá realizarse en el servidor y no solamente en la interfaz.

---

## 7. Manejo de sesiones

Las sesiones deberán cumplir las siguientes políticas:

- Crear una nueva sesión después de un inicio de sesión exitoso.
- Regenerar el identificador de sesión al autenticar al usuario.
- Cerrar completamente la sesión cuando el usuario seleccione "Cerrar sesión".
- Invalidar la sesión anterior después del cierre.
- Definir un tiempo de expiración por inactividad.
- No almacenar información sensible directamente en cookies.
- Solicitar autenticación para acceder a rutas protegidas.

Si un usuario intenta ingresar directamente mediante una URL protegida sin una sesión válida, deberá ser redirigido al inicio de sesión.

---

## 8. Recuperación de contraseña

LumaTek utilizará recuperación de contraseña mediante correo electrónico y tokens temporales.

Flujo:

1. El usuario selecciona "Olvidé mi contraseña".
2. Ingresa su correo electrónico.
3. LumaTek genera un token temporal.
4. Se envía un enlace de recuperación al correo registrado.
5. El usuario accede al enlace.
6. Define una nueva contraseña.
7. El token queda invalidado después de utilizarse o expirar.

La contraseña actual nunca será enviada por correo electrónico.

---

## 9. Validación de datos

Toda información enviada por los usuarios deberá validarse en el servidor.

Las validaciones incluirán:

- Campos obligatorios.
- Formato de correo electrónico.
- Longitudes máximas.
- Valores numéricos permitidos.
- Identificadores existentes.
- Pertenencia de recursos a la empresa autenticada.
- Valores permitidos para estados y tipos.

Laravel utilizará mecanismos de validación antes de almacenar información en la base de datos.

---

## 10. Protección de rutas

Las rutas privadas deberán requerir autenticación.

Se utilizarán mecanismos de autorización para verificar:

- Usuario autenticado.
- Rol del usuario.
- Empresa a la que pertenece.
- Permisos necesarios para realizar la acción.

No será suficiente ocultar botones en la interfaz; las restricciones deberán aplicarse también desde el backend.

---

## 11. Auditoría

LumaTek mantendrá una bitácora mediante la tabla `audit_logs`.

Se podrán registrar acciones como:

- Inicio de sesión.
- Creación o modificación de usuarios.
- Registro de invernaderos.
- Cambios de configuración.
- Modificación de sensores.
- Atención de alertas.
- Acciones administrativas.

Esto permitirá conocer qué usuario realizó una acción y cuándo ocurrió.

---

## 12. Resumen de mecanismos de seguridad

| Mecanismo | Implementación propuesta |
|---|---|
| Autenticación web | Sesiones Laravel |
| API / app móvil | JWT |
| Contraseñas | bcrypt |
| Comunicación | HTTPS/TLS |
| Autorización | Roles y permisos |
| Multiempresa | Restricción por `company_id` |
| Sesiones | Cookies seguras y expiración |
| Recuperación | Token temporal por correo |
| Validaciones | Backend Laravel |
| Auditoría | Tabla `audit_logs` |
