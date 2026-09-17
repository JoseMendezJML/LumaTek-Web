# Diccionario de Datos – LumaTek

## 1. Tabla: companies

**Descripción:** Almacena la información de las empresas o clientes registrados en la plataforma LumaTek.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único de la empresa. |
| name | VARCHAR(150) | No | - | Nombre comercial de la empresa. |
| legal_name | VARCHAR(200) | Sí | - | Razón social de la empresa. |
| email | VARCHAR(150) | No | - | Correo electrónico principal de la empresa. |
| phone | VARCHAR(20) | Sí | - | Número telefónico de contacto. |
| status | ENUM('active','inactive') | No | - | Estado actual de la empresa. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

---

## 2. Tabla: roles

**Descripción:** Define los roles disponibles para los usuarios de la plataforma.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único del rol. |
| name | VARCHAR(50) | No | - | Nombre del rol. |
| description | VARCHAR(255) | Sí | - | Descripción de las funciones del rol. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

---

## 3. Tabla: users

**Descripción:** Almacena los usuarios que pueden acceder a LumaTek y relaciona cada usuario con una empresa y un rol.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único del usuario. |
| company_id | BIGINT UNSIGNED | No | FK | Empresa a la que pertenece el usuario. |
| role_id | BIGINT UNSIGNED | No | FK | Rol asignado al usuario. |
| name | VARCHAR(120) | No | - | Nombre del usuario. |
| email | VARCHAR(150) | No | UQ | Correo electrónico utilizado para iniciar sesión. |
| password | VARCHAR(255) | No | - | Contraseña almacenada de forma cifrada/hash. |
| status | ENUM('active','inactive') | No | - | Estado actual del usuario. |
| email_verified_at | TIMESTAMP | Sí | - | Fecha y hora en que se verificó el correo. |
| remember_token | VARCHAR(100) | Sí | - | Token utilizado para mantener la sesión iniciada. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

**Relaciones:**
- `company_id` → `companies.id`
- `role_id` → `roles.id`

---

## 4. Tabla: plans

**Descripción:** Almacena los planes disponibles en LumaTek y los límites asociados a cada uno.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único del plan. |
| name | VARCHAR(50) | No | - | Nombre del plan, por ejemplo Free o Pro. |
| description | VARCHAR(255) | Sí | - | Descripción general del plan. |
| max_greenhouses | INT | No | - | Cantidad máxima de invernaderos permitidos. |
| max_users | INT | No | - | Cantidad máxima de usuarios permitidos. |
| max_zones | INT | No | - | Cantidad máxima de zonas permitidas. |
| max_sensors | INT | No | - | Cantidad máxima de sensores permitidos. |
| history_days | INT | No | - | Número de días de historial disponibles. |
| reports_enabled | BOOLEAN | No | - | Indica si el plan permite generar reportes. |
| advanced_alerts | BOOLEAN | No | - | Indica si el plan permite alertas avanzadas. |
| status | ENUM('active','inactive') | No | - | Estado del plan. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

---

## 5. Tabla: subscriptions

**Descripción:** Relaciona cada empresa con el plan que tiene contratado o asignado.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único de la suscripción. |
| company_id | BIGINT UNSIGNED | No | FK | Empresa propietaria de la suscripción. |
| plan_id | BIGINT UNSIGNED | No | FK | Plan asociado a la empresa. |
| start_date | DATE | No | - | Fecha de inicio de la suscripción. |
| end_date | DATE | Sí | - | Fecha de finalización de la suscripción. |
| status | ENUM('active','expired','cancelled') | No | - | Estado actual de la suscripción. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

**Relaciones:**
- `company_id` → `companies.id`
- `plan_id` → `plans.id`

---

## 6. Tabla: greenhouses

**Descripción:** Almacena los invernaderos pertenecientes a cada empresa registrada en LumaTek.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único del invernadero. |
| company_id | BIGINT UNSIGNED | No | FK | Empresa propietaria del invernadero. |
| name | VARCHAR(120) | No | - | Nombre del invernadero. |
| crop | VARCHAR(120) | Sí | - | Cultivo principal del invernadero. |
| shape | ENUM('rectangular','other') | No | - | Forma aproximada del invernadero. |
| length_m | DECIMAL(10,2) | Sí | - | Longitud del invernadero en metros. |
| width_m | DECIMAL(10,2) | Sí | - | Ancho del invernadero en metros. |
| area_m2 | DECIMAL(12,2) | Sí | - | Área aproximada en metros cuadrados. |
| status | ENUM('active','inactive') | No | - | Estado actual del invernadero. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

**Relaciones:**
- `company_id` → `companies.id`

---

## 7. Tabla: zones

**Descripción:** Define las zonas de monitoreo dentro de cada invernadero.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único de la zona. |
| greenhouse_id | BIGINT UNSIGNED | No | FK | Invernadero al que pertenece la zona. |
| name | VARCHAR(100) | No | - | Nombre asignado a la zona. |
| description | VARCHAR(255) | Sí | - | Descripción adicional de la zona. |
| position_x | DECIMAL(5,2) | Sí | - | Posición horizontal relativa dentro del invernadero. |
| position_y | DECIMAL(5,2) | Sí | - | Posición vertical relativa dentro del invernadero. |
| width_percent | DECIMAL(5,2) | Sí | - | Ancho relativo de la zona en porcentaje. |
| height_percent | DECIMAL(5,2) | Sí | - | Alto relativo de la zona en porcentaje. |
| status | ENUM('active','inactive') | No | - | Estado actual de la zona. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

**Relaciones:**
- `greenhouse_id` → `greenhouses.id`

---

## 8. Tabla: devices

**Descripción:** Almacena los dispositivos de comunicación o control ubicados en las zonas, como ESP32 o gateways.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único del dispositivo. |
| zone_id | BIGINT UNSIGNED | No | FK | Zona donde se encuentra el dispositivo. |
| name | VARCHAR(120) | No | - | Nombre identificativo del dispositivo. |
| device_code | VARCHAR(100) | No | UQ | Código único del dispositivo. |
| device_type | VARCHAR(80) | Sí | - | Tipo o modelo del dispositivo. |
| connection_type | ENUM('wifi','lora','ethernet','other') | Sí | - | Medio de comunicación utilizado. |
| status | ENUM('online','offline','inactive') | No | - | Estado operativo del dispositivo. |
| last_connection_at | TIMESTAMP | Sí | - | Fecha y hora de la última conexión registrada. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

**Relaciones:**
- `zone_id` → `zones.id`

---

## 9. Tabla: sensors

**Descripción:** Almacena los sensores físicos conectados a cada dispositivo y los límites utilizados para el monitoreo.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único del sensor. |
| device_id | BIGINT UNSIGNED | No | FK | Dispositivo al que está conectado el sensor. |
| name | VARCHAR(120) | No | - | Nombre identificativo del sensor. |
| sensor_type | ENUM('temperature','air_humidity','soil_moisture','light','co2','other') | No | - | Tipo de variable medida. |
| unit | VARCHAR(20) | No | - | Unidad de medida utilizada. |
| min_threshold | DECIMAL(10,2) | Sí | - | Valor mínimo permitido antes de generar una alerta. |
| max_threshold | DECIMAL(10,2) | Sí | - | Valor máximo permitido antes de generar una alerta. |
| position_x | DECIMAL(5,2) | Sí | - | Posición horizontal relativa del sensor. |
| position_y | DECIMAL(5,2) | Sí | - | Posición vertical relativa del sensor. |
| status | ENUM('active','inactive','maintenance') | No | - | Estado operativo del sensor. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

**Relaciones:**
- `device_id` → `devices.id`

---

## 10. Tabla: readings

**Descripción:** Registra las mediciones obtenidas por los sensores de LumaTek.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único de la lectura. |
| sensor_id | BIGINT UNSIGNED | No | FK | Sensor que produjo la lectura. |
| value | DECIMAL(12,4) | No | - | Valor registrado por el sensor. |
| recorded_at | DATETIME | No | - | Fecha y hora en que se obtuvo la medición. |
| source | ENUM('simulation','iot') | No | - | Origen de la lectura: simulación o dispositivo físico. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

**Relaciones:**
- `sensor_id` → `sensors.id`

---

## 11. Tabla: alerts

**Descripción:** Registra las alertas generadas cuando una lectura supera los límites establecidos o cuando ocurre una condición anormal.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único de la alerta. |
| sensor_id | BIGINT UNSIGNED | No | FK | Sensor relacionado con la alerta. |
| reading_id | BIGINT UNSIGNED | Sí | FK | Lectura que originó la alerta. |
| alert_type | ENUM('below_minimum','above_maximum','sensor_offline','other') | No | - | Tipo de condición detectada. |
| severity | ENUM('low','medium','high','critical') | No | - | Nivel de severidad de la alerta. |
| message | VARCHAR(255) | No | - | Mensaje descriptivo de la alerta. |
| status | ENUM('active','acknowledged','resolved') | No | - | Estado actual de la alerta. |
| acknowledged_by | BIGINT UNSIGNED | Sí | FK | Usuario que confirmó la atención de la alerta. |
| acknowledged_at | DATETIME | Sí | - | Fecha y hora en que fue reconocida. |
| resolved_at | DATETIME | Sí | - | Fecha y hora en que fue resuelta. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

**Relaciones:**
- `sensor_id` → `sensors.id`
- `reading_id` → `readings.id`
- `acknowledged_by` → `users.id`

---

## 12. Tabla: irrigation_events

**Descripción:** Registra los eventos de riego realizados en los invernaderos o zonas.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único del evento de riego. |
| greenhouse_id | BIGINT UNSIGNED | No | FK | Invernadero donde se realizó el riego. |
| zone_id | BIGINT UNSIGNED | Sí | FK | Zona específica donde se realizó el riego. |
| user_id | BIGINT UNSIGNED | Sí | FK | Usuario responsable del evento. |
| event_type | ENUM('manual','automatic') | No | - | Tipo de activación del riego. |
| started_at | DATETIME | No | - | Fecha y hora de inicio. |
| ended_at | DATETIME | Sí | - | Fecha y hora de finalización. |
| notes | VARCHAR(255) | Sí | - | Observaciones adicionales. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

**Relaciones:**
- `greenhouse_id` → `greenhouses.id`
- `zone_id` → `zones.id`
- `user_id` → `users.id`

---

## 13. Tabla: crop_cycles

**Descripción:** Almacena los ciclos de cultivo administrados dentro de cada invernadero.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único del ciclo de cultivo. |
| greenhouse_id | BIGINT UNSIGNED | No | FK | Invernadero relacionado con el ciclo. |
| crop_name | VARCHAR(120) | No | - | Nombre del cultivo. |
| variety | VARCHAR(120) | Sí | - | Variedad del cultivo. |
| sowing_date | DATE | Sí | - | Fecha de siembra. |
| expected_end_date | DATE | Sí | - | Fecha estimada de finalización. |
| actual_end_date | DATE | Sí | - | Fecha real de finalización. |
| status | ENUM('planned','active','completed','cancelled') | No | - | Estado actual del ciclo. |
| notes | VARCHAR(255) | Sí | - | Observaciones del ciclo de cultivo. |
| created_at | TIMESTAMP | Sí | - | Fecha de creación del registro. |
| updated_at | TIMESTAMP | Sí | - | Fecha de última actualización. |

**Relaciones:**
- `greenhouse_id` → `greenhouses.id`

---

## 14. Tabla: audit_logs

**Descripción:** Registra acciones relevantes realizadas por los usuarios para mantener trazabilidad y auditoría dentro de LumaTek.

| Campo | Tipo de dato | Nulo | Llave | Descripción |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | PK | Identificador único del registro de auditoría. |
| company_id | BIGINT UNSIGNED | No | FK | Empresa relacionada con la acción. |
| user_id | BIGINT UNSIGNED | Sí | FK | Usuario que realizó la acción. |
| action | VARCHAR(120) | No | - | Acción realizada. |
| entity_type | VARCHAR(100) | Sí | - | Tipo de entidad afectada. |
| entity_id | BIGINT UNSIGNED | Sí | - | Identificador de la entidad afectada. |
| description | VARCHAR(255) | Sí | - | Descripción de la acción realizada. |
| ip_address | VARCHAR(45) | Sí | - | Dirección IP desde la que se realizó la acción. |
| created_at | TIMESTAMP | Sí | - | Fecha y hora del registro de auditoría. |

**Relaciones:**
- `company_id` → `companies.id`
- `user_id` → `users.id`