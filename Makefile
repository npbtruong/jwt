# Makefile — lệnh tắt cho project Laravel Docker.
# Cần có `make` (WSL2/Git Bash đã có). Nếu không có make, xem lệnh docker tương ứng trong README.
#
# Truyền tham số qua biến `cmd`, ví dụ:
#   make artisan cmd="migrate --seed"
#   make composer cmd="require laravel/telescope"
#   make npm cmd="run build"

DC  = docker compose
APP = $(DC) exec app
PROD = $(DC) -f docker-compose.yml -f docker-compose.prod.yml

.PHONY: help up down restart build rebuild ps logs shell node-shell \
        composer artisan migrate fresh npm npm-dev create-laravel key install \
        perms prod-up prod-down

help: ## Danh sách lệnh
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

up: ## Khởi động toàn bộ service (dev)
	$(DC) up -d

down: ## Tắt service (GIỮ nguyên data DB)
	$(DC) down

restart: ## Khởi động lại service
	$(DC) restart

build: ## Build image app
	$(DC) build

rebuild: ## Build lại image không dùng cache rồi up
	$(DC) build --no-cache && $(DC) up -d

ps: ## Trạng thái container
	$(DC) ps

logs: ## Xem log (Ctrl+C để thoát)
	$(DC) logs -f

shell: ## Vào shell container app
	$(APP) sh

node-shell: ## Vào shell container node
	$(DC) exec node sh

composer: ## Chạy composer, vd: make composer cmd="install"
	$(APP) composer $(cmd)

artisan: ## Chạy artisan, vd: make artisan cmd="route:list"
	$(APP) php artisan $(cmd)

migrate: ## Chạy migrate
	$(APP) php artisan migrate

fresh: ## migrate:fresh --seed (XOÁ hết bảng rồi tạo lại + seed)
	$(APP) php artisan migrate:fresh --seed

npm: ## Chạy npm trong container node, vd: make npm cmd="install"
	$(DC) exec node npm $(cmd)

npm-dev: ## Chạy Vite dev server (HMR) ở cổng 5173
	$(DC) exec node npm run dev

install: ## composer install (cài lại vendor)
	$(APP) composer install

key: ## Sinh APP_KEY
	$(APP) php artisan key:generate

create-laravel: ## Tạo Laravel mới vào thư mục hiện tại (chạy 1 lần khi bắt đầu)
	$(DC) run --rm --no-deps app sh -lc 'set -e; composer create-project laravel/laravel /tmp/app --prefer-dist --no-interaction; rm -f /tmp/app/README.md /tmp/app/.env.example /tmp/app/.env; ( cd /tmp/app && tar cf - . ) | ( cd /var/www/html && tar xf - )'

perms: ## Cấp quyền ghi storage/ + bootstrap/cache cho www-data (fix lỗi 500 tempnam)
	$(APP) sh -lc "chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache"

prod-up: ## Khởi động ở chế độ production
	$(PROD) up -d --build

prod-down: ## Tắt production
	$(PROD) down
