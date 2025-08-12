
# neixTest

Proyecto de prueba en **PHP vanilla** con **WebSocket** (Ratchet), **MariaDB** y **Docker**.  
Arquitectura modular por capas (simple) y front en HTML/JS puro.

## Requisitos
- Docker Desktop (Windows/Mac) o Docker + Docker Compose v2
- (Opcional) Make
- Acceso a internet para `composer install` durante el build

## Levantar el proyecto

```bash
# 1) Clonar el repo (o descomprimir el zip)
cd neixTest

# 2) Levantar contenedores (primera vez tarda por composer)
docker compose up -d --build

# 3) Ver logs (opcional)
docker compose logs -f

# 4) Abrir la app
http://localhost:8088
# WebSocket: ws://localhost:8080

# 5) Adminer (DB)
http://localhost:8081
# System: MariaDB
# Server: db
# User: root
# Pass: root
# Database: neix
```

### Usuarios de prueba
- **admin / admin123**
- **trader / trader123**

## Estructura rápida
```
app/
  Domain/               # Validaciones y modelos simples
  Infrastructure/
    db.php              # Conexión PDO
    auth.php            # Helpers de sesión
    ws/                 # Servidor Ratchet y simulador de precios
public/
  index.html            # Login + Panel (SPA simple)
  assets/app.js         # Lógica de UI + WebSocket
  assets/styles.css
  api/*.php             # Endpoints HTTP
config/
  init.sql              # Esquema + datos seed
docker/
  nginx/default.conf    # Nginx -> PHP-FPM
  php-fpm/Dockerfile
  ws/Dockerfile
docker-compose.yml
composer.json
```

## Scripts útiles

```bash
# reiniciar sólo WS
docker compose restart ws

# ver logs del WS
docker compose logs -f ws

# resetear DB (borra volúmenes)
docker compose down -v && docker compose up -d --build
```

## Flujo
1. Login HTTP → sesión PHP.
2. UI carga instrumentos y config del usuario por REST.
3. WebSocket (Ratchet) envía precios simulados cada 200ms.
4. Botón “Enviar todas las configuraciones” manda `send_configs` por WS y muestra ACK/Error en notificaciones.
