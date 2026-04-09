# keycloak-multi-app

Ubuntu 上の Docker で、`Keycloak + Laravel + Nuxt` を `nginx + domain + https` の本番寄り構成で動かす最小サンプルです。

## 構成

- `https://global.example.com` : Nuxt ログイン画面 + Global BFF
- `https://a.example.com` : 拠点 A BFF
- `https://b.example.com` : 拠点 B BFF
- `https://keycloak.example.com` : Keycloak
- `https://pgadmin.example.com` : pgAdmin 4

コンテナは次の 9 サービスです。

- `nginx`
- `keycloak`
- `postgres`
- `pgadmin`
- `backend`
- `bff-global`
- `bff-a`
- `bff-b`
- `frontend`

Docker ネットワークは `internal` を 1 つだけ使います。

## ディレクトリ

```text
.
├── docker-compose.yml
├── docker/
│   ├── env/
│   ├── frontend/
│   └── laravel/
├── frontend/
├── keycloak/
├── laravel-overlay/
└── nginx/
    ├── certs/
    └── conf.d/
```

## 事前準備

### 1. hosts 設定

Ubuntu ホストの `/etc/hosts` に追加します。

```text
127.0.0.1 global.example.com
127.0.0.1 a.example.com
127.0.0.1 b.example.com
127.0.0.1 keycloak.example.com
127.0.0.1 pgadmin.example.com
```

### 2. mkcert で wildcard 証明書を作る

```bash
sudo apt update
sudo apt install -y libnss3-tools
curl -JLO "https://dl.filippo.io/mkcert/latest?for=linux/amd64"
chmod +x mkcert-v*-linux-amd64
sudo mv mkcert-v*-linux-amd64 /usr/local/bin/mkcert

mkcert -install
mkcert -cert-file nginx/certs/_wildcard.example.com.pem \
  -key-file nginx/certs/_wildcard.example.com-key.pem \
  "*.example.com" example.com
```

証明書は nginx コンテナ内で `/etc/nginx/certs` にマウントされます。

### 3. Docker / compose

```bash
docker compose version
docker version
```

## PostgreSQL / pgAdmin 4

アプリケーション用 DB として PostgreSQL を追加しています。

- service: `postgres`
- DB 名: `myapp`
- user: `myapp`
- password: `myapp123`

Laravel 共通 env では次を設定済みです。

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=myapp
DB_USERNAME=myapp
DB_PASSWORD=myapp123
```

pgAdmin 4 も Docker で起動し、nginx 経由で HTTPS アクセスできます。

- URL: `https://pgadmin.example.com`
- login email: `admin@example.com`
- login password: `admin123`

pgAdmin で PostgreSQL を登録する場合は次を使います。

- Host name: `postgres`
- Port: `5432`
- Username: `myapp`
- Password: `myapp123`

このリポジトリでは [servers.json](/home/wsat/projects/keycloak-multi-app/pgadmin/servers.json) を pgAdmin コンテナへマウントしているため、初回ログイン時点で `myapp-postgres` が事前登録された状態になります。

## 起動

```bash
docker compose build
docker compose up -d
```

初回 `build` 時に Laravel イメージ内で `composer create-project laravel/laravel` を実行し、`laravel-overlay/` の最小コードを上書き適用します。

## 起動後の確認

### PostgreSQL の稼働確認

まずコンテナ状態を確認します。

```bash
docker compose ps
```

PostgreSQL へ直接入る確認コマンドです。

```bash
docker compose exec postgres psql -U myapp -d myapp
```

`psql` に入れたら、次で接続確認できます。

```sql
\conninfo
\dt
SELECT current_database(), current_user;
```

終了は次です。

```sql
\q
```

1 コマンドで疎通だけ確認したい場合は次でも大丈夫です。

```bash
docker compose exec postgres psql -U myapp -d myapp -c "SELECT version();"
docker compose exec postgres psql -U myapp -d myapp -c "\dt"
```

health 状態の確認:

```bash
docker compose ps
docker inspect --format='{{json .State.Health}}' keycloak-multi-app-postgres-1
docker inspect --format='{{json .State.Health}}' keycloak-multi-app-backend-1
docker inspect --format='{{json .State.Health}}' keycloak-multi-app-keycloak-1
```

### pgAdmin 4 の確認

1. `https://pgadmin.example.com` を開く
2. `admin@example.com / admin123` でログインする
3. 左ペインに `myapp-postgres` が見えることを確認する
4. 初回接続時にパスワードを聞かれたら `myapp123` を入力する

もし手動登録したい場合は次を入力します。

- Name: `myapp-postgres`
- Host name/address: `postgres`
- Port: `5432`
- Maintenance database: `myapp`
- Username: `myapp`
- Password: `myapp123`

### Laravel migration の初期設定

Laravel 共通 env で PostgreSQL 接続は設定済みです。

```env
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=myapp
DB_USERNAME=myapp
DB_PASSWORD=myapp123
```

初期 migration サンプルとして [2026_04_09_000000_create_route_assignments_table.php](/home/wsat/projects/keycloak-multi-app/laravel-overlay/database/migrations/2026_04_09_000000_create_route_assignments_table.php) を追加しています。管理用カラム追加は [2026_04_09_000100_add_management_columns_to_route_assignments_table.php](/home/wsat/projects/keycloak-multi-app/laravel-overlay/database/migrations/2026_04_09_000100_add_management_columns_to_route_assignments_table.php) です。初期 seed は [DatabaseSeeder.php](/home/wsat/projects/keycloak-multi-app/laravel-overlay/database/seeders/DatabaseSeeder.php) です。

migration 実行はどの Laravel コンテナからでもできますが、共通 backend から実行するのが分かりやすいです。

```bash
docker compose exec backend php artisan migrate --seed --force
```

すでに旧版の `route_assignments` テーブルを作成済みでも、このまま `migrate --seed --force` で追加カラム migration が順に適用されます。

状態確認:

```bash
docker compose exec backend php artisan migrate:status
docker compose exec postgres psql -U myapp -d myapp -c "\dt"
docker compose exec postgres psql -U myapp -d myapp -c "SELECT sub, site_code, server_url FROM route_assignments ORDER BY sub;"
```

ロールバック確認:

```bash
docker compose exec backend php artisan migrate:rollback --step=1
docker compose exec backend php artisan migrate --seed --force
```

新しい migration を追加したいときは次を使います。

```bash
docker compose exec backend php artisan make:migration create_example_table
```

生成先は Laravel コンテナ内の `/var/www/app/database/migrations` です。今の構成ではイメージ内に展開されるため、継続的に編集したい場合は次のどちらかにすると運用しやすいです。

- `laravel-overlay/database/migrations` に migration を追加して再 build する
- Laravel アプリ全体を bind mount する構成へ切り替える

## Backend の振り分けロジック

backend の `/internal/users/{sub}/server` は PostgreSQL の `route_assignments` テーブルを参照するようにしています。

- 既存レコードがあればそのまま返却
- 未登録の `sub` は暫定ルールで A/B を決めて DB に保存
- 次回以降は保存済みの所属先を返却

`route_assignments` には pgAdmin で直接編集しやすい管理カラムも追加しています。

- `display_name`: 管理画面で見やすい表示名
- `is_active`: 一時停止フラグ
- `priority`: 優先度
- `notes`: 運用メモ
- `last_resolved_at`: backend が最後に参照した時刻

backend API と保存データの確認:

```bash
curl -k https://global.example.com/health
curl -k https://keycloak.example.com/health/ready
docker compose exec postgres psql -U myapp -d myapp -c "SELECT sub, site_code, server_url FROM route_assignments ORDER BY sub;"
```

### 管理 API

backend には最小の管理 API を追加しています。

- `GET /internal/route-assignments`
- `POST /internal/route-assignments`
- `PUT /internal/route-assignments/{sub}`
- `DELETE /internal/route-assignments/{sub}`

これらの管理 API には Basic 認証を付けています。初期値は [backend.env](/home/wsat/projects/keycloak-multi-app/docker/env/backend.env) に入れています。

- user: `ops`
- password: `ops12345`

実運用では必ず変更してください。

一覧取得:

```bash
docker compose exec backend curl -s -u ops:ops12345 http://127.0.0.1:8000/internal/route-assignments
```

新規追加:

```bash
docker compose exec backend curl -s -u ops:ops12345 -X POST http://127.0.0.1:8000/internal/route-assignments \
  -H "Content-Type: application/json" \
  -d '{
    "sub": "tenant-user-c",
    "display_name": "Site C candidate",
    "site_code": "A",
    "server_url": "https://a.example.com",
    "is_active": true,
    "priority": 120,
    "notes": "Created via backend management API."
  }'
```

更新:

```bash
docker compose exec backend curl -s -u ops:ops12345 -X PUT http://127.0.0.1:8000/internal/route-assignments/tenant-user-a \
  -H "Content-Type: application/json" \
  -d '{
    "display_name": "Alice updated assignment",
    "site_code": "B",
    "server_url": "https://b.example.com",
    "priority": 90,
    "notes": "Temporarily routed to site B."
  }'
```

無効化:

```bash
docker compose exec backend curl -s -u ops:ops12345 -X PUT http://127.0.0.1:8000/internal/route-assignments/tenant-user-b \
  -H "Content-Type: application/json" \
  -d '{
    "is_active": false,
    "notes": "Disabled for maintenance."
  }'
```

削除:

```bash
docker compose exec backend curl -i -u ops:ops12345 -X DELETE http://127.0.0.1:8000/internal/route-assignments/tenant-user-c
```

## Keycloak 設定

このリポジトリには `keycloak/realm-myapp.json` を同梱してあり、起動時に自動 import します。

### realm

- `myapp`

### clients

- `global-login`
- `app-a`
- `app-b`

### redirect URIs

- `https://global.example.com/auth/callback`
- `https://a.example.com/auth/callback`
- `https://b.example.com/auth/callback`

### issuer

- `https://keycloak.example.com/realms/myapp`

### 初期ログイン情報

- Keycloak 管理画面: `https://keycloak.example.com`
- admin user: `admin`
- admin password: `admin123`

### テストユーザー

- `alice / password`
- `bob / password`

このサンプルでは Keycloak user id を固定しており、subject は次になります。

- `alice` の `sub`: `tenant-user-a`
- `bob` の `sub`: `tenant-user-b`

そのため、末尾が `b` のユーザーは `b.example.com`、それ以外は `a.example.com` に振り分けられます。

## Laravel の役割

### 共通 .env

全 Laravel サービスで次を共通設定しています。

```env
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
```

### Global BFF

- `/login` で Keycloak OIDC ログイン開始
- `/auth/callback` で `sub` を取得
- `backend` の `/internal/users/{sub}/server` を呼ぶ
- `https://a.example.com/auth/silent-login` または `https://b.example.com/auth/silent-login` へ 302

### 共通 Backend

- `GET /internal/users/{sub}/server`
- 仮実装として、`sub` の末尾が `b` なら B、それ以外は A

### 拠点 BFF

- `/auth/silent-login` で `prompt=none` を付けた Keycloak 再認証
- `/auth/callback` で Laravel Auth セッションを作成
- `/` でログイン済みユーザー情報を返却

## Nuxt

`frontend/` はログインボタンのみの最小 UI です。`https://global.example.com/login` に遷移します。

## nginx

`nginx/conf.d/default.conf` で 5 ドメイン分の server block をまとめて定義しています。

必須ヘッダは全 proxy location に設定済みです。

```nginx
proxy_set_header Host $host;
proxy_set_header X-Forwarded-Proto https;
proxy_set_header X-Forwarded-For $remote_addr;
```

## 動作確認

1. `https://global.example.com` を開く
2. `Login` を押す
3. Keycloak でログインする
4. backend が所属先 URL を返す
5. `https://a.example.com` または `https://b.example.com` にリダイレクトされる
6. Keycloak セッションを使って silent login が成功し、SSO 状態になる

## VS Code Remote-SSH 前提

- Ubuntu サーバーに Remote-SSH 接続
- このリポジトリをそのまま開く
- 編集はローカルファイルに対して行い、起動はサーバー上で `docker compose up -d`

## 補足

- Keycloak は簡略化のため `start-dev` を使っています
- 本番運用では Keycloak 用 DB、secret 管理、監査設定、healthcheck を追加してください
- Nuxt と Laravel は最小構成です。実案件では CSRF、logout、エラーハンドリング、監視を補ってください
