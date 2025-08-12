# neixTest

Simulador FX con **PHP vanilla**, arquitectura por capas, **WebSocket** en tiempo real (Ratchet/ReactPHP) y **MySQL**.  
Incluye login, panel de instrumentos (USD, EUR, JPY, GBP), configuración por instrumento y **“Enviar todas”** validando **y persistiendo** vía WS.

---

## Credenciales de prueba
- **admin / admin123**
- **trader / trader123**

---

## Requisitos
- Docker Desktop (Windows/Mac/Linux) o Docker + Docker Compose v2
- Acceso a internet (para `composer install` durante el build)

---

## Variables de entorno
Crea un .env (o usa los defaults). Ejemplo:

env
NGINX_PORT=8088
WS_PORT=8080

DB_HOST=db
DB_NAME=neix
DB_USER=root
DB_PASS=root

APP_ENV=local
APP_DEBUG=1

---

## Primer arranque
# 1) Clonar / entrar al proyecto
cd neixTest

# 2) Levantar contenedores
docker compose up -d --build

# 3) Optimizar autoload (primera vez / tras mover clases)
docker compose exec php sh -lc "composer dump-autoload -o"

# 4) Ver logs (opcional)
docker compose logs -f

---

## DB (Adminer)

System: MariaDB

Server: db

User: root

Pass: root

Database: neix

---

## Flujo funcional
Login (HTTP) → crea sesión PHP.

UI carga instrumentos + configuración del usuario por REST.

WebSocket emite price_update periódicos (precio + market qty).

Guardar individual: POST /api/config_save.php (valida y persiste).

Enviar todas (WS): send_configs → el WS valida con ConfigService::validateOne() y persiste por ítem.
Responde ack_configs con {symbol, ok, error?}; la UI colorea ✓/✗ por fila y muestra resumen.

---

## Contratos (HTTP/WS)
HTTP (principales)
POST /api/login.php → {username, password} → {ok:true}

POST /api/logout.php → {ok:true}

GET /api/whoami.php → {username} o 401

GET /api/instruments.php → [{id, symbol}]

GET /api/config_get.php → [{symbol, target_price, quantity, side}]

POST /api/config_save.php → {symbol, target_price, quantity, side} → valida + persiste (errores descriptivos)

---

## Estructura
app/
  Application/
    ConfigService.php                     # Validación + persistencia por ítem
  Domain/
    Repository/                           # Interfaces de repositorio
  Infrastructure/
    Auth.php                              # Sesión
    DB.php                                # PDO (DB::pdo()/DB::get())
    Repository/
      PdoInstrumentRepository.php
      PdoUserInstrumentConfigRepository.php
      PdoUserRepository.php
    ws/
      PriceEngine.php                     # Simulador de precios
      server.php                          # Servidor Ratchet (WS)
public/
  index.html
  assets/
    app.js                                # UI + WS
    styles.css
  api/
    login.php, logout.php, whoami.php
    instruments.php
    config_get.php
    config_save.php
  healthz.php                             # Health para Nginx
config/
  init.sql                                # Esquema + seed
docker/
  nginx/default.conf
  php-fpm/Dockerfile
  ws/Dockerfile
docker-compose.yml
composer.json
