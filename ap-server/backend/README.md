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
- ユーザーは AP サーバー内でユニークに扱う
- 認可は「所属スコープ」と「操作権限」の両方で判定する
- ロールは「所属レイヤー」と「権限ロール」の組み合わせとして扱う
- `admin_flag` のような例外フラグは採用せず、権限は `permissions` で表現する

### 所属レイヤー

- `server`
- `service`
- `tenant`

### 権限ロール

- `admin`
- `operator`
- `viewer`

### permissions 一覧案

- `user.manage`
- `object.read`
- `object.update`
- `object.create`
- `object.delete`
- `object.execute`

### スコープと継承方針

- 各レイヤーに設定項目を持てる
- `server -> service -> tenant` のドリルダウンでオブジェクトを登録する前提とする
- 上位レイヤーの設定値は下位レイヤーへ継承できる
- データアクセス判定は、対象オブジェクトが所属スコープ配下にあることを確認したうえで、必要な `permission` を持つかで判定する

### ロールと権限の考え方

- `roles` は運用上のまとまりであり、実際の API / 業務操作の許可判定は `permissions` 単位で行う
- `admin` にユーザー管理を暗黙包含させず、`user.manage` は独立した権限として扱う
- これにより、将来的に `operator + user.manage` のような例外的な組み合わせにも対応できる

### 想定するロール構成

- `server_admin`
- `server_operator`
- `server_viewer`
- `service_admin`
- `service_operator`
- `service_viewer`
- `tenant_admin`
- `tenant_operator`
- `tenant_viewer`

### permission の割り当て例

- `admin`
  - `object.read`
  - `object.update`
  - `object.create`
  - `object.delete`
  - `object.execute`
- `operator`
  - `object.read`
  - `object.update`
  - `object.execute`
- `viewer`
  - `object.read`

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

### `GET /api/me/authorization`

現在ユーザーに対応する AP 側 RBAC 情報の最小取得 API です。未認証の場合は `authorization` も `null` を返します。

返却例:

```json
{
  "current_user": null,
  "authorization": null
}
```

Keycloak Bearer トークン認証済みの場合の返却例:

```json
{
  "current_user": {
    "id": "keycloak-user-1",
    "name": "kc-user",
    "email": "kc-user@example.com"
  },
  "authorization": {
    "keycloak_sub": "keycloak-user-1",
    "assignments": [
      {
        "scope": {
          "id": 1,
          "layer": "tenant",
          "code": "tenant-a",
          "name": "Tenant A",
          "parent_scope_id": null
        },
        "role": {
          "id": 3,
          "slug": "tenant_viewer",
          "name": "Tenant Viewer",
          "scope_layer": "tenant",
          "permission_role": "viewer"
        },
        "permissions": [
          {
            "id": 1,
            "slug": "object.read",
            "name": "Object Read"
          }
        ]
      }
    ],
    "permissions": [
      "object.read"
    ]
  }
}
```

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
- 認可テーブルは `ap_users`、`scopes`、`roles`、`permissions`、`role_permissions`、`user_role_assignments` を基本とする
- 権限 API の最小入口として `GET /api/me/authorization` を追加し、現状レスポンスは assignment 単位の `scope / role / permissions` と集約済み permission 一覧を返す
- 初期 seed は `server / service / tenant` と `admin / operator / viewer` の組み合わせロール、および基本 permissions を前提にする
- Keycloak の検証に使う設定値は `KEYCLOAK_ISSUER`、`KEYCLOAK_CLIENT_ID`、`KEYCLOAK_PUBLIC_KEY`
- Keycloak の検証に使う追加設定値は `KEYCLOAK_JWKS_URL`、`KEYCLOAK_JWKS_CACHE_TTL`、`KEYCLOAK_DISCOVERY_CACHE_TTL`
- 現時点では `kid` を使った JWKS 自動取得に対応しており、`KEYCLOAK_PUBLIC_KEY` は JWKS 未使用時のフォールバック
- `KEYCLOAK_JWKS_URL` を省略した場合は discovery を優先し、その結果が無いときだけ `issuer + /protocol/openid-connect/certs` を使う
- 既存 compose の PostgreSQL サービスは `postgres` で、共通 env では `DB_HOST=postgres` / `DB_CONNECTION=pgsql` を前提としている

## 引継ぎメモ

### AP 認可モデルの整理

- 背景: 権限まわりを今後拡張する前提で、所属レイヤー、権限ロール、permission の責務を先に分離しておく必要があった
- 決定事項: 所属レイヤーは `server / service / tenant`、権限ロールは `admin / operator / viewer` とし、ロールはその組み合わせで表現する。`user.manage` は独立 permission として扱い、`admin_flag` は採用しない
- 影響範囲: `ap-server/backend` の認可設計、テーブル設計、API ごとの認可判定方針、将来のフロントエンドの権限制御
- 次の推奨アクション: `scope` を含む認可テーブルへ DB 実装を拡張し、`roles -> permissions` の解決結果で API 権限判定を行う Service を追加する

### roles / permissions / scope を含むテーブル設計案

- 背景: 現在の最小実装はロール名の保持までで止めているが、今後は permission 単位で判定できる形に育てたい
- 決定事項: `ap_users` を起点に `user_role_assignments`、`roles`、`permissions`、`role_permissions`、`scopes` を持つ構成を基本案とし、初期ロール・権限は seed で投入する
- 影響範囲: マイグレーション、seed、認可 Service、管理 API、フロントエンドのロール編集 UI、`GET /api/me/authorization` のレスポンス形式
- 次の推奨アクション: API 単位の required permission を整理し、認可判定専用 Service と middleware 相当の入口を追加する

想定テーブル:

- `ap_users`
  - AP 側ユーザー本体
  - 主識別子は `keycloak_sub`
- `scopes`
  - 所属対象の管理テーブル
  - `id`, `layer`, `code`, `name`, `parent_scope_id`
  - `layer` は `server / service / tenant`
- `roles`
  - ロール定義テーブル
  - `id`, `scope_layer`, `permission_role`, `slug`, `name`
  - 例: `tenant_viewer`
- `permissions`
  - 権限定義テーブル
  - `id`, `slug`, `name`
  - 例: `object.read`, `user.manage`
- `role_permissions`
  - ロールと permission の対応
  - `role_id`, `permission_id`
- `user_role_assignments`
  - ユーザーへのロール付与
  - `id`, `keycloak_sub`, `role_id`, `scope_id`
  - どのスコープに対してどのロールを持つかを保持する
