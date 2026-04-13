# AP Backend

`ap-server/backend` は、新しい AP サーバー向けの `Laravel 13` バックエンドです。

## 現在の API 方針

- 最小 API は `GET /api/health` と `GET /api/me`
- `greeting` エンドポイントは採用せず、今後も残る用途に寄せて整理済み
- Controller にロジックは置かず、`Service` と `CurrentUserResolver` に責務を分離
- 将来の認証方式が Laravel 標準から Keycloak に変わっても、CurrentUser の解決は `app/Services/Auth/CurrentUserResolver.php` に寄せる方針
- API の内部表現として `app/Services/Auth/CurrentUser.php` を使い、Eloquent `User` を直接サービス境界の外へ漏らさない
- Keycloak トークン対応の入口として `app/Services/Auth/KeycloakTokenCurrentUserResolver.php` を追加し、Bearer トークンの claims から `CurrentUser` を組み立てられるようにした
- Keycloak Bearer トークンは RS256 署名、`iss`、`aud` / `azp`、`exp`、`nbf` を検証してから `CurrentUser` に変換する
- Keycloak の公開鍵は `kid` を使って JWKS から選択する。`KEYCLOAK_PUBLIC_KEY` はフォールバック用途として残す
- `KEYCLOAK_JWKS_URL` が未設定なら OpenID Connect discovery から `jwks_uri` を解決し、取得失敗時は Keycloak の標準 certs パスへフォールバックする

## 認可方針

- 認可は各 AP サーバーで実施する
- 認可モデルは RBAC を基本とする
- 権限は AP サーバーごとに独立して持つ
- 同一ユーザーでも AP ごとに異なる権限を持てる前提で設計する
- Keycloak は認証の正とし、AP サーバー内の認可判定は AP サーバーの業務データを正として行う

## ユーザー管理方針

- ユーザー更新経路は `Application -> AuthGateway -> Keycloak API`
- 認証情報の正は Keycloak
- 業務ユーザー情報の正は各 AP サーバー
- ユーザー識別子は Keycloak の `sub` を使用する
- `sub` を AP サーバー側のユーザー主識別子として扱い、メールアドレスや表示名は変更可能な属性として扱う

## DB 方針

- 現時点のおすすめは、既存の `postgres` コンテナを再利用し、その中に AP サーバー用の別 DB を追加して使う形
- コンテナは共有しても、DB は `laravel-overlay` 用と `ap-server` 用で分離する
- AP サーバーの業務データと認可データは AP サーバー用 DB に保持する
- 別コンテナ化は、バージョン差分、運用責任分離、性能分離、バックアップ分離が必要になった時点で検討する

## 現在のエンドポイント

### `GET /api/health`

疎通確認用です。

返却例:

```json
{
  "status": "ok"
}
```

### `GET /api/me`

現在ユーザー取得用です。未認証でも `200 OK` を返し、`current_user` が `null` になります。

返却例:

```json
{
  "current_user": null
}
```

認証済みの場合の返却例:

```json
{
  "current_user": {
    "id": 1,
    "name": "AP User",
    "email": "ap-user@example.com"
  }
}
```

Keycloak Bearer トークンが送られた場合も、claims から同じ `current_user` 形式を返す。

## コンテナでの作業

起動:

```bash
docker compose up -d ap-backend
```

コンテナへ入る:

```bash
docker compose exec ap-backend bash
```

## アプリ操作

依存関係のインストール:

```bash
composer install
```

開発サーバー起動:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

テスト実行:

```bash
php artisan test
```

## 補足

- 初期 scaffold は `Laravel 13` で作成済み
- ルーティングは `bootstrap/app.php` で `routes/api.php` を読み込む設定に変更済み
- `CurrentUserResolver` は現時点では `request()->user()` を元に `CurrentUser` DTO を組み立てる実装
- `CurrentUserResolver` は Bearer トークンがある場合は Keycloak claims から `CurrentUser` を解決し、無い場合は `request()->user()` を使う
- Keycloak 導入時は `CurrentUserResolver` のみ差し替える想定
- API テストは `tests/Feature/Api` 配下に追加して管理
- `MeService` は `CurrentUser` DTO を受け取ってレスポンスを整形する
- Keycloak の検証に使う設定値は `KEYCLOAK_ISSUER`、`KEYCLOAK_CLIENT_ID`、`KEYCLOAK_PUBLIC_KEY`
- Keycloak の検証に使う追加設定値は `KEYCLOAK_JWKS_URL`、`KEYCLOAK_JWKS_CACHE_TTL`、`KEYCLOAK_DISCOVERY_CACHE_TTL`
- 現時点では `kid` を使った JWKS 自動取得に対応しており、`KEYCLOAK_PUBLIC_KEY` は JWKS 未使用時のフォールバック
- `KEYCLOAK_JWKS_URL` を省略した場合は discovery を優先し、その結果が無いときだけ `issuer + /protocol/openid-connect/certs` を使う
- 既存 compose の PostgreSQL サービスは `postgres` で、共通 env では `DB_HOST=postgres` / `DB_CONNECTION=pgsql` を前提としている
