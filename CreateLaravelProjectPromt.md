Tôi muốn tạo một project Laravel MỚI hoàn toàn, được Docker hoá đầy đủ để phát triển local trên Windows (WSL2 + Docker Desktop). Máy tôi chỉ có Docker, KHÔNG cài sẵn PHP/Composer/Node trên Windows, nên mọi thứ phải chạy trong container.

## Stack yêu cầu
- PHP 8.3 (php-fpm, Alpine cho nhẹ)
- Nginx làm web server (reverse proxy tới php-fpm)
- MariaDB 10.11
- Redis (cache + queue)
- phpMyAdmin (UI xem DB)
- Node 20 (build Vite/npm assets)
- Laravel bản mới nhất

## Cấu trúc cần tạo
Tạo các file sau ở thư mục gốc:
- Dockerfile (cho service PHP: cài các extension Laravel cần như pdo_mysql, bcmath, gd, zip, redis qua pecl; cài Composer; có COPY code + chạy composer install --no-dev --optimize-autoloader để dùng được cho production build)
- .dockerignore (chặn vendor/, node_modules/, .env, .git/, storage/logs)
- docker/nginx/default.conf (config nginx trỏ tới public/, fastcgi sang php-fpm cổng 9000)
- docker-compose.yml — dùng cho DEV, gồm các service:
    - app (build từ Dockerfile): VOLUME MOUNT ./:/var/www/html để sửa code thấy ngay không cần build lại
    - nginx: cổng 8080:80, mount code + config nginx
    - db (mariadb:10.11): có NAMED VOLUME để giữ data qua các lần tắt máy, expose 3306
    - redis
    - phpmyadmin: cổng 8081, trỏ tới db
    - node (node:20-alpine): để chạy npm/vite, mount code
- docker-compose.prod.yml — override cho production: KHÔNG volume mount code (dùng code đã COPY trong image), không expose DB ra ngoài, không có phpmyadmin/node
- .env.example đã cấu hình sẵn: DB_HOST=db, DB_DATABASE, DB_USERNAME, DB_PASSWORD, REDIS_HOST=redis, CACHE_STORE=redis, QUEUE_CONNECTION=redis
- Makefile với các lệnh tắt tiện dùng: up, down, build, shell (vào container app), composer, artisan, migrate, npm, fresh (migrate:fresh --seed)
- README.md ghi rõ các bước setup lần đầu và lệnh dùng hằng ngày

## Yêu cầu quan trọng
1. Vì máy chưa có Laravel code, hãy tạo Laravel mới BÊN TRONG container (composer create-project hoặc laravel installer), KHÔNG yêu cầu tôi cài PHP trên Windows.
2. Giải thích rõ trong README thứ tự chạy lần đầu: build → up → tạo Laravel → composer install → cp .env → key:generate → migrate.
3. Tất cả lệnh trong README phải chạy được qua Docker (docker compose exec ...), không giả định có PHP/Composer/Node trên host.
4. MariaDB phải dùng named volume để KHÔNG mất data khi tắt máy hoặc docker compose down (nhưng cảnh báo tôi về down -v).
5. Cấu hình để sửa code PHP/Blade là F5 thấy ngay (nhờ volume mount), chỉ build lại image khi đổi extension/PHP version.
6. Ports: web 8080, phpMyAdmin 8081 — tránh đụng XAMPP/WAMP mà tôi đang chạy song song (thường chiếm 80, 8080 đôi khi cũng bị, nếu nghi ngờ đề xuất port khác).

Hãy tạo toàn bộ file, giải thích ngắn gọn từng file làm gì, và cuối cùng cho tôi đúng danh sách lệnh chạy theo thứ tự để có project chạy được tại http://localhost:8080.
