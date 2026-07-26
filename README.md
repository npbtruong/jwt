# Laravel + Docker (Windows / WSL2 + Docker Desktop)

Môi trường Laravel **được Docker hoá đầy đủ** — máy bạn **chỉ cần Docker**, KHÔNG cần cài PHP/Composer/Node trên Windows. Mọi lệnh đều chạy trong container.

- Web app: <http://localhost:8080>
- phpMyAdmin: <http://localhost:8081>
- Vite HMR (khi `npm run dev`): cổng `5173`

> Ports 8080/8081 được chọn để tránh đụng XAMPP/WAMP đang chạy song song (thường chiếm cổng 80). Nếu vẫn bị trùng, xem mục [Đổi port](#đổi-port-nếu-bị-trùng).

---

## Stack

| Thành phần | Image | Ghi chú |
|---|---|---|
| PHP 8.3 (php-fpm) | build từ `Dockerfile` (Alpine) | extension: pdo_mysql, bcmath, gd, zip, intl, mbstring, exif, pcntl, opcache, **redis** (pecl) + Composer |
| Nginx | `nginx:1.27-alpine` | reverse proxy → php-fpm:9000, root `public/` |
| MySQL 8 | `mysql:8.0` | dữ liệu trong **named volume** (không mất khi tắt máy) |
| Redis | `redis:7-alpine` | cache + queue |
| phpMyAdmin | `phpmyadmin:5` | UI xem DB |
| Node 20 | `node:20-alpine` | chạy npm / vite |

---

## Các file trong project

| File | Vai trò |
|---|---|
| `Dockerfile` | Image PHP-fpm: cài extension + Composer; COPY code + `composer install --no-dev` để dùng cho **build production**. |
| `.dockerignore` | Chặn `vendor/`, `node_modules/`, `.env`, `.git/`, `storage/logs` khỏi build context. |
| `docker/nginx/default.conf` | Config Nginx: trỏ `public/`, chuyển `.php` sang `app:9000`. |
| `docker/php/php.ini` | Tinh chỉnh PHP (memory, upload, opcache dev-friendly). |
| `docker-compose.yml` | **DEV**: app, nginx, mysql, redis, phpmyadmin, node. Bind mount code để sửa là thấy ngay. |
| `docker-compose.prod.yml` | Override **PROD**: không bind mount code, DB không lộ ra ngoài, không phpmyadmin/node. |
| `.env.example` | Đã cấu hình sẵn `DB_HOST=mysql`, `REDIS_HOST=redis`, `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`. |
| `Makefile` | Lệnh tắt: `up`, `down`, `shell`, `artisan`, `composer`, `npm`, `migrate`, `fresh`... |

---

## Thiết lập lần đầu (chạy theo đúng thứ tự)

> Mở terminal trong WSL2 (hoặc Git Bash / PowerShell) tại thư mục project. Không cần PHP/Composer/Node trên host.

```bash
# 1) Build image PHP (lần đầu chưa có Laravel nên composer install trong Dockerfile được bỏ qua)
docker compose build

# 2) Bật các service nền (mysql, redis...) — cần mysql chạy trước khi migrate
docker compose up -d

# 3) Tạo Laravel MỚI ngay trong container app (KHÔNG cần PHP trên Windows)
#    Tạo vào /tmp rồi copy nội dung sang bằng tar (cp của Alpine/BusyBox không copy đúng
#    cú pháp "thư mục/." nên phải dùng tar). Xoá README/.env.example/.env của bản Laravel
#    trước khi copy để GIỮ NGUYÊN README.md và .env.example đã cấu hình sẵn của repo này.
docker compose run --rm --no-deps app sh -lc 'set -e; composer create-project laravel/laravel /tmp/app --prefer-dist --no-interaction; rm -f /tmp/app/README.md /tmp/app/.env.example /tmp/app/.env; ( cd /tmp/app && tar cf - . ) | ( cd /var/www/html && tar xf - )'

# 4) Cài dependencies (thường bước 3 đã cài; chạy lại cho chắc)
docker compose exec app composer install

# 5) Cấp quyền ghi cho www-data (php-fpm) trên storage/ và bootstrap/cache/
#    Nếu bỏ qua bước này sẽ bị lỗi 500 "tempnam(): file created in the system's temporary directory".
docker compose exec app sh -lc "chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache"

# 6) Tạo file .env từ .env.example (bản đã cấu hình sẵn DB/Redis)
docker compose exec app cp .env.example .env

# 7) Sinh APP_KEY
docker compose exec app php artisan key:generate

# 8) Chạy migrate (kết nối tới service "mysql")
docker compose exec app php artisan migrate
```

Xong! Mở <http://localhost:8080>.

> **Dùng Makefile cho gọn** (nếu có `make`):
> ```bash
> make build && make up
> make create-laravel      # bước 3
> make install             # bước 4
> make perms               # bước 5 (cấp quyền storage)
> make artisan cmd="key:generate"   # hoặc: make key (bước 7)
> make migrate             # bước 8
> ```
> (Bước `cp .env.example .env`: chạy `docker compose exec app cp .env.example .env` hoặc copy tay.)

### Vì sao thứ tự này?
`build` (tạo image có PHP/Composer) → `up` (mysql/redis sẵn sàng) → **tạo Laravel** (vì máy chưa có code) → `composer install` (vendor) → **cấp quyền storage** (php-fpm chạy dưới `www-data` cần ghi được `storage/`) → `cp .env` (cấu hình) → `key:generate` (khoá app) → `migrate` (tạo bảng, cần mysql đã chạy).

---

## Lệnh dùng hằng ngày

| Việc | Lệnh docker | Makefile |
|---|---|---|
| Bật / tắt | `docker compose up -d` / `down` | `make up` / `make down` |
| Vào shell app | `docker compose exec app sh` | `make shell` |
| Artisan | `docker compose exec app php artisan <cmd>` | `make artisan cmd="<cmd>"` |
| Composer | `docker compose exec app composer <cmd>` | `make composer cmd="<cmd>"` |
| Migrate | `docker compose exec app php artisan migrate` | `make migrate` |
| Làm mới DB + seed | `docker compose exec app php artisan migrate:fresh --seed` | `make fresh` |
| npm | `docker compose exec node npm <cmd>` | `make npm cmd="<cmd>"` |
| Vite dev (HMR) | `docker compose exec node npm run dev` | `make npm-dev` |
| Xem log | `docker compose logs -f` | `make logs` |

### Sửa code — F5 thấy ngay
Nhờ bind mount `./:/var/www/html`, mọi thay đổi **PHP/Blade** có hiệu lực ngay (opcache đã bật `validate_timestamps`). **Chỉ cần build lại image** (`make rebuild`) khi bạn đổi `Dockerfile` (extension/PHP version).

### Frontend (Vite)
```bash
docker compose exec node npm install
docker compose exec node npm run dev   # HMR ở cổng 5173
# hoặc build tĩnh:
docker compose exec node npm run build
```
Để HMR chạy được từ trình duyệt host, đảm bảo `vite.config.js` có `server: { host: '0.0.0.0', hmr: { host: 'localhost' } }`.

---

## Dữ liệu MySQL (quan trọng)

- DB nằm trong **named volume** `laraveldocker_mysqldata` → `docker compose down` / tắt máy **KHÔNG mất data**.
- ⚠️ **`docker compose down -v` sẽ XOÁ volume → mất toàn bộ dữ liệu DB.** Chỉ dùng khi thực sự muốn reset sạch.
- Cổng DB bind vào `127.0.0.1:3306` → chỉ kết nối được từ máy local (DBeaver/TablePlus...), không lộ ra mạng ngoài.

---

## Chạy chế độ Production (tuỳ chọn)

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
```

Ở prod: code lấy từ image (không bind mount), DB không mở ra mạng ngoài, không chạy phpmyadmin/node.

> ⚠️ **Deploy lại khi đổi code**: named volume `app_code` chỉ nạp code từ image **lần đầu**. Sau khi build image mới, làm mới volume:
> ```bash
> docker compose -f docker-compose.yml -f docker-compose.prod.yml down
> docker volume rm laraveldocker_app_code
> docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
> ```
> Nhớ đặt `APP_ENV=production`, `APP_DEBUG=false` trong `.env`.

---

## Đổi port (nếu bị trùng)

Sửa trong `docker-compose.yml`:
- Web: đổi `"8080:80"` → ví dụ `"8090:80"` (service `nginx`), rồi cập nhật `APP_URL` trong `.env`.
- phpMyAdmin: đổi `"8081:80"` → ví dụ `"8091:80"` (service `phpmyadmin`).

Kiểm tra cổng đang bị chiếm trên Windows (PowerShell): `netstat -ano | findstr :8080`.

---

## Xử lý sự cố nhanh

- **`php artisan migrate` báo không kết nối được DB**: đợi mysql khởi động xong (`docker compose ps` thấy mysql `healthy`), rồi chạy lại. Đảm bảo `.env` có `DB_HOST=mysql`.
- **Lỗi quyền ghi `storage/` hoặc `bootstrap/cache/`**: `docker compose exec app sh -lc "chmod -R ug+rw storage bootstrap/cache"`.
- **Đổi extension/PHP version không ăn**: `make rebuild` (build lại không cache).
- **Trang trắng / 502**: xem log `docker compose logs -f app nginx`.
