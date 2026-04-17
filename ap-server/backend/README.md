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
- 業務データは各レコードに `scope_id` を持たせ、認可では「必要 permission を持つ assignment が対象 scope の祖先または同一 scope に存在するか」で判定する

### 所属レイヤー

- `server`
- `service`
- `tenant`

### 権限ロール

- `admin`
- `operator`
- `viewer`
- `user_manager`

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
- `server_user_manager`
- `service_admin`
- `service_operator`
- `service_viewer`
- `service_user_manager`
- `tenant_admin`
- `tenant_operator`
- `tenant_viewer`
- `tenant_user_manager`

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
- `user_manager`
  - `user.manage`

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
- 最初の業務テーブルとして `objects` を追加し、`scope_id`, `code`, `name` を持つ最小構成で扱う
- `objects.code` は保存前に trim、lowercase、空白 / `_` の `-` 正規化を行う

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

### 開発用 token 取得前提

- live mode の手動確認では `global-login` client の Bearer token を `Auth Entry` へ直接貼る運用を採る
- ローカル開発では `keycloak/realm-myapp.json` の `global-login` に対して `directAccessGrantsEnabled: true` を有効にし、`alice / password` から password grant で `access_token` を取得できるようにする
- 取得例:

```bash
curl -k https://keycloak.example.com/realms/myapp/protocol/openid-connect/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=password' \
  --data-urlencode 'client_id=global-login' \
  --data-urlencode 'client_secret=global-secret' \
  --data-urlencode 'username=alice' \
  --data-urlencode 'password=password'
```

- `ap-backend` は `KEYCLOAK_CLIENT_ID=global-login` を既定とするので、`access_token` の `aud` / `azp` もこの client に合わせる

### AP Frontend host を使う live mode では backend CORS に `ap.example.com` が必要

- `ap-server/frontend` を `https://ap.example.com` で公開した後、browser から `https://ap-backend-fpm.example.com/api/*` を直接叩く live mode では `CORS_ALLOWED_ORIGINS` に `https://ap.example.com` を含める必要がある
- backend 側の CORS 設定は `config/cors.php` を repo に置き、`docker/env/laravel.common.env` の `CORS_ALLOWED_ORIGINS` をそのまま読む形に固定する
- `ap.example.com` を追加した後は `docker compose up -d --force-recreate ap-backend ap-backend-fpm` で env を反映する

### `GET /api/users`

AP 側ユーザー管理の一覧 endpoint です。route では `required_permissions:user.manage` を必須にし、service では `AuthorizationService::accessibleScopeIds(..., ['user.manage'])` で確定した管理可能 scope 配下に assignment を持つユーザーだけを返します。つまり、システムユーザーは配下 service / tenant を含む管理対象、サービスユーザーは配下 tenant を含む管理対象、テナントユーザーは所属 tenant のユーザー一覧を表示する前提です。`scope_id`, `keycloak_sub`, `keyword`, `sort`, `page`, `per_page` を受け付け、`keyword` は `display_name` / `email` を横断して曖昧検索します。初期 sort は `email` の昇順です。assignment と集約 `permissions` も同じ scope 範囲に限定して返します。

返却例:

```json
{
  "data": [
    {
      "keycloak_sub": "keycloak-user-1",
      "display_name": "AP User",
      "email": "ap-user@example.com",
      "assignments": [
        {
          "id": 1,
          "scope": {
            "id": 3,
            "layer": "tenant",
            "code": "tenant-a",
            "name": "Tenant A",
            "parent_scope_id": 1
          },
          "role": {
            "id": 10,
            "slug": "tenant_user_manager",
            "name": "Tenant User Manager",
            "scope_layer": "tenant",
            "permission_role": "user_manager"
          },
          "permissions": [
            {
              "id": 1,
              "slug": "user.manage",
              "name": "User Manage"
            }
          ]
        }
      ],
      "permissions": [
        "user.manage"
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1,
    "filters": {
      "scope_id": 3,
      "keycloak_sub": null,
      "keyword": "ap",
      "sort": "keycloak_sub"
    }
  }
}
```

### `GET /api/roles`

AP 側ユーザー管理 UI 向けの role 一覧 endpoint です。route では `required_permissions:user.manage` を必須にし、role 定義自体は global resource として扱います。`scope_layer`, `permission_role`, `slug`, `name`, `sort` を受け付け、assignment 付与 UI では選択中 scope に合わせて `scope_layer` を指定して候補 role を取得する前提とします。

返却例:

```json
{
  "data": [
    {
      "id": 11,
      "slug": "tenant_viewer",
      "name": "Tenant Viewer",
      "scope_layer": "tenant",
      "permission_role": "viewer",
      "permissions": [
        {
          "id": 2,
          "slug": "object.read",
          "name": "Object Read"
        }
      ]
    }
  ]
}
```

### `GET /api/scopes`

AP 側ユーザー管理 UI 向けの visible scope 一覧 endpoint です。route では `required_permissions:user.manage` を必須にし、service では `AuthorizationService::accessibleScopeIds(..., ['user.manage'])` で確定した管理可能 scope だけを返します。`layer`, `parent_scope_id`, `code`, `name`, `sort` を受け付け、scope 選択 UI では必要に応じて `layer` や `parent_scope_id` で候補を絞る前提とします。

返却例:

```json
{
  "data": [
    {
      "id": 3,
      "layer": "tenant",
      "code": "tenant-a",
      "name": "Tenant A",
      "parent_scope_id": 2
    }
  ]
}
```

### `GET /api/users/{keycloak_sub}`

AP 側ユーザー管理の詳細取得 endpoint です。route では `required_permissions:user.manage` を必須にし、service では対象ユーザーの assignment を管理可能 scope 配下に絞って読み込みます。visible な assignment が 1 件以上ある場合だけ `200 OK` を返し、対象ユーザーが存在しない場合と、存在しても管理可能 scope に assignment が無い場合はどちらも `404 Not Found` を返します。

返却例:

```json
{
  "data": {
    "keycloak_sub": "keycloak-user-1",
    "display_name": "AP User",
    "email": "ap-user@example.com",
    "assignments": [
      {
        "id": 1,
        "scope": {
          "id": 3,
          "layer": "tenant",
          "code": "tenant-a",
          "name": "Tenant A",
          "parent_scope_id": 1
        },
        "role": {
          "id": 11,
          "slug": "tenant_viewer",
          "name": "Tenant Viewer",
          "scope_layer": "tenant",
          "permission_role": "viewer"
        },
        "permissions": [
          {
            "id": 2,
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

### `POST /api/users/{keycloak_sub}/assignments`

AP 側ユーザーへの role 付与 endpoint です。route では `required_permissions:user.manage` を必須にし、入力された `scope_id` は管理可能 scope 配下であることを要求します。`role_id` は target scope と同じ `scope_layer` を持つ role だけを許可し、同一 `(keycloak_sub, role_id, scope_id)` が既に存在する場合は `422 Unprocessable Entity` を返します。成功時は visible assignment だけを含む user payload を `201 Created` で返します。

リクエスト例:

```json
{
  "scope_id": 3,
  "role_id": 11
}
```

### `DELETE /api/users/{keycloak_sub}/assignments`

AP 側ユーザーから role を剥奪する endpoint です。route では `required_permissions:user.manage` を必須にし、payload の `scope_id` と `role_id` で visible assignment を特定して削除します。対象 assignment が存在しない場合と、存在しても管理可能 scope の外にある場合は `404 Not Found` を返します。成功時は `204 No Content` を返します。

### `DELETE /api/users/{keycloak_sub}/assignments/{assignmentId}`

AP 側ユーザーから role を剥奪する個別 endpoint です。route では `required_permissions:user.manage` を必須にし、path の `assignmentId` で visible assignment を特定して削除します。対象 assignment が存在しない場合、別ユーザーの assignment を指した場合、存在しても管理可能 scope の外にある場合はどれも `404 Not Found` を返します。成功時は `204 No Content` を返します。今後の users 系 assignment 個別操作はこちらを基準 route とします。

users 系 assignment の変更は、現段階では `PATCH` を追加せず、この個別削除 endpoint と `POST /api/users/{keycloak_sub}/assignments` の再付与を組み合わせて表現します。
また、一括更新 endpoint は現段階では追加せず、複数 assignment の調整も single-assignment API の繰り返しで表現します。

## API 契約の運用方針

- 現段階では OpenAPI は未採用とする
- ただし将来の OpenAPI 移行を前提に、README 上の API 契約は endpoint ごとに request / success response / error case を分けて維持する
- 設計判断や採用理由は引き続き README の引継ぎメモへ残し、外向き API 契約へ寄る内容は endpoint セクションで明示する
- response shape、error shape、parameter 命名は新規 API 追加時も既存の表現に寄せ、後から `openapi.yaml` へ写しやすい粒度を保つ

### `GET /api/objects`

業務 API の一覧 endpoint です。`required_permissions:object.read` を必須にし、認可通過後も `AuthorizationService` で「閲覧可能 scope 配下の object のみ」に絞って返します。`scope_id`, `code`, `name`, `sort`, `page`, `per_page` を受け付けるが、filter と sort は認可済み scope の範囲内でのみ適用されます。

返却例:

```json
{
  "data": [
    {
      "id": 1,
      "scope_id": 3,
      "code": "tenant-object",
      "name": "Tenant Object"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1,
    "filters": {
      "scope_id": 3,
      "code": "tenant",
      "name": "Tenant",
      "sort": "code"
    }
  }
}
```

### `GET /api/playbooks`

`objects` とは別 resource の一覧 endpoint です。`required_permissions:object.read` を必須にし、`AuthorizationService` で確定した閲覧可能 scope 配下だけを対象に `scope_id`, `code`, `name`, `sort`, `page`, `per_page` を適用します。query の組み立ては `ListQueryService` を再利用します。

返却例:

```json
{
  "data": [
    {
      "id": 1,
      "scope_id": 3,
      "code": "tenant-runbook",
      "name": "Tenant Runbook"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1,
    "filters": {
      "scope_id": 3,
      "code": null,
      "name": "Tenant",
      "sort": "code"
    }
  }
}
```

### `GET /api/playbooks/{id}`

`playbooks` の詳細取得 endpoint です。route では `required_permissions:object.read` を必須にし、service では対象 playbook の `scope_id` に対して `canAccessScope(..., 'object.read', $scopeId)` を使って判定します。

### `POST /api/playbooks`

`playbooks` の新規作成 endpoint です。route では `required_permissions:object.create` を必須にし、入力された `scope_id` に対して `canAccessScope(..., 'object.create', $scopeId)` を使って作成可否を判定します。`code` は objects と同じ正規化ルールを使い、正規化後の `scope_id + code` が重複する場合は `422 Unprocessable Entity` を返します。

### `PATCH /api/playbooks/{id}`

`playbooks` の更新 endpoint です。route では `required_permissions:object.update` を必須にし、service では対象 playbook の `scope_id` に対して `canAccessScope(..., 'object.update', $scopeId)` を使って更新可否を判定します。`scope_id` を変更する場合は、現在の scope に対する `object.update` に加えて、移動先 `scope_id` に対する `object.create` も必要です。

### `DELETE /api/playbooks/{id}`

`playbooks` の削除 endpoint です。route では `required_permissions:object.delete` を必須にし、service では対象 playbook の `scope_id` に対して `canAccessScope(..., 'object.delete', $scopeId)` を使って削除可否を判定します。成功時は `204 No Content` を返します。

### `GET /api/policies`

`policies` の一覧 endpoint です。`required_permissions:object.read` を必須にし、`objects` / `playbooks` と同じく `AuthorizationService` で確定した閲覧可能 scope 配下だけを対象に filter / sort / pagination を適用します。

### `GET /api/checklists`

`checklists` の一覧 endpoint です。`required_permissions:object.read` を必須にし、`objects` / `playbooks` / `policies` と同じく `AuthorizationService` で確定した閲覧可能 scope 配下だけを対象に filter / sort / pagination を適用します。

### `GET /api/policies/{id}`

`policies` の詳細取得 endpoint です。route では `required_permissions:object.read` を必須にし、service では対象 policy の `scope_id` に対して `canAccessScope(..., 'object.read', $scopeId)` を使って判定します。

### `POST /api/policies`

`policies` の新規作成 endpoint です。route では `required_permissions:object.create` を必須にし、入力された `scope_id` に対して `canAccessScope(..., 'object.create', $scopeId)` を使って作成可否を判定します。`code` は既存 resource と同じ正規化ルールを使い、正規化後の `scope_id + code` が重複する場合は `422 Unprocessable Entity` を返します。

### `PATCH /api/policies/{id}`

`policies` の更新 endpoint です。route では `required_permissions:object.update` を必須にし、service では対象 policy の `scope_id` に対して `canAccessScope(..., 'object.update', $scopeId)` を使って更新可否を判定します。`scope_id` の変更は許可せず、作成後の policy は同一 scope に固定します。

### `DELETE /api/policies/{id}`

`policies` の削除 endpoint です。route では `required_permissions:object.delete` を必須にし、service では対象 policy の `scope_id` に対して `canAccessScope(..., 'object.delete', $scopeId)` を使って削除可否を判定します。成功時は `204 No Content` を返します。

### `GET /api/checklists/{id}`

`checklists` の詳細取得 endpoint です。route では `required_permissions:object.read` を必須にし、service では対象 checklist の `scope_id` に対して `canAccessScope(..., 'object.read', $scopeId)` を使って判定します。

### `POST /api/checklists`

`checklists` の新規作成 endpoint です。route では `required_permissions:object.create` を必須にし、入力された `scope_id` に対して `canAccessScope(..., 'object.create', $scopeId)` を使って作成可否を判定します。`code` は既存 resource と同じ正規化ルールを使い、正規化後の `scope_id + code` が重複する場合は `422 Unprocessable Entity` を返します。

### `PATCH /api/checklists/{id}`

`checklists` の更新 endpoint です。route では `required_permissions:object.update` を必須にし、service では対象 checklist の `scope_id` に対して `canAccessScope(..., 'object.update', $scopeId)` を使って更新可否を判定します。`scope_id` を変更する場合は、現在の scope に対する `object.update` に加えて、移動先 `scope_id` に対する `object.create` も必要です。

### `DELETE /api/checklists/{id}`

`checklists` の削除 endpoint です。route では `required_permissions:object.delete` を必須にし、service では対象 checklist の `scope_id` に対して `canAccessScope(..., 'object.delete', $scopeId)` を使って削除可否を判定します。成功時は `204 No Content` を返します。

### `GET /api/objects/{id}`

業務 object の詳細取得 endpoint です。route では `required_permissions:object.read` を必須にし、service では対象 object の `scope_id` に対して `canAccessScope(..., 'object.read', $scopeId)` を使って絞り込みます。

存在しない object の場合:

```json
{
  "message": "Not Found"
}
```

対象 scope にアクセスできない場合:

```json
{
  "message": "Forbidden",
  "required_permissions": ["object.read"],
  "scope_id": 3
}
```

### `PATCH /api/objects/{id}`

業務 object の更新 endpoint です。route では `required_permissions:object.update` を必須にし、service では対象 object の `scope_id` に対して `canAccessScope(..., 'object.update', $scopeId)` を使って更新可否を判定します。`scope_id` を変更する場合は、現在の scope に対する `object.update` に加えて、移動先 `scope_id` に対する `object.create` も必要です。`code` は保存前に正規化され、正規化後の `scope_id + code` が既存 object と重複する更新は `422 Unprocessable Entity` を返します。

リクエスト例:

```json
{
  "scope_id": 5,
  "code": "updated-object",
  "name": "Updated Object"
}
```

### `POST /api/objects`

業務 object の新規作成 endpoint です。route では `required_permissions:object.create` を必須にし、service では入力された `scope_id` に対して `canAccessScope(..., 'object.create', $scopeId)` を使って作成可否を判定します。`code` は保存前に正規化され、正規化後の `scope_id + code` が既存 object と重複する場合は `422 Unprocessable Entity` を返します。

リクエスト例:

```json
{
  "scope_id": 3,
  "code": "tenant-object",
  "name": "Tenant Object"
}
```

作成成功時の返却例:

```json
{
  "data": {
    "id": 1,
    "scope_id": 3,
    "code": "tenant-object",
    "name": "Tenant Object"
  }
}
```

### `DELETE /api/objects/{id}`

業務 object の削除 endpoint です。route では `required_permissions:object.delete` を必須にし、service では対象 object の `scope_id` に対して `canAccessScope(..., 'object.delete', $scopeId)` を使って削除可否を判定します。成功時は `204 No Content` を返します。

## 現在の API required permissions 一覧

- `GET /api/health`
  - required permissions: なし
  - 理由: 疎通確認用の public endpoint
- `GET /api/me`
  - required permissions: なし
  - 理由: 現在ユーザーの自己参照 endpoint で、未認証時も `current_user: null` を返す契約
- `GET /api/me/authorization`
  - required permissions: なし
  - 理由: 現在ユーザーの AP 側 RBAC 可視化 endpoint で、未認証時も `authorization: null` を返す契約
- `GET /api/users`
  - required permissions: `user.manage`
  - 理由: AP 側ユーザー管理一覧では route で `user.manage` を明示し、返却対象と assignment 表示範囲を管理可能 scope 配下へ限定するため
- `GET /api/roles`
  - required permissions: `user.manage`
  - 理由: AP 側ユーザー管理 UI 用の role 一覧でも route で `user.manage` を明示し、assignment 付与候補を同じ権限境界の内側で取得するため
- `GET /api/scopes`
  - required permissions: `user.manage`
  - 理由: AP 側ユーザー管理 UI 用の scope 一覧でも route で `user.manage` を明示し、visible scope だけを選択候補として返すため
- `GET /api/users/{keycloak_sub}`
  - required permissions: `user.manage`
  - 理由: AP 側ユーザー管理詳細でも route で `user.manage` を明示し、visible な assignment を持つ対象ユーザーだけを返すため
- `POST /api/users/{keycloak_sub}/assignments`
  - required permissions: `user.manage`
  - 理由: AP 側ユーザーへの role 付与でも route で `user.manage` を明示し、visible scope の assignment だけを作成対象にするため
- `DELETE /api/users/{keycloak_sub}/assignments`
  - required permissions: `user.manage`
  - 理由: AP 側ユーザーからの role 剥奪でも route で `user.manage` を明示し、visible assignment だけを削除対象にするため
- `DELETE /api/users/{keycloak_sub}/assignments/{assignmentId}`
  - required permissions: `user.manage`
  - 理由: assignment id ベースの個別削除でも route で `user.manage` を明示し、visible assignment を安定して 1 件指定できるようにするため
- `GET /api/objects`
  - required permissions: `object.read`
  - 理由: 一覧 endpoint でも public / introspection endpoint 以外は route 定義時に required permissions を明示し、返却対象と filter の適用範囲を閲覧可能 scope 配下へ限定するため
- `GET /api/playbooks`
  - required permissions: `object.read`
  - 理由: 別 resource の一覧でも route で read permission を明示し、返却対象と filter / sort の適用範囲を閲覧可能 scope 配下へ限定するため
- `GET /api/playbooks/{id}`
  - required permissions: `object.read`
  - 理由: playbooks の詳細取得でも route で read permission を明示し、対象 record の `scope_id` に対する追加認可を service 側で行うため
- `POST /api/playbooks`
  - required permissions: `object.create`
  - 理由: playbooks の作成でも route で create permission を明示し、入力された `scope_id` に対する追加認可を service 側で行うため
- `PATCH /api/playbooks/{id}`
  - required permissions: `object.update`
  - 理由: playbooks の更新でも route で update permission を明示し、対象 record の `scope_id` と必要に応じて移動先 `scope_id` に対する追加認可を service 側で行うため
- `DELETE /api/playbooks/{id}`
  - required permissions: `object.delete`
  - 理由: playbooks の削除でも route で delete permission を明示し、対象 record の `scope_id` に対する追加認可を service 側で行うため
- `GET /api/policies`
  - required permissions: `object.read`
  - 理由: policies の一覧でも route で read permission を明示し、返却対象と filter / sort の適用範囲を閲覧可能 scope 配下へ限定するため
- `GET /api/checklists`
  - required permissions: `object.read`
  - 理由: checklists の一覧でも route で read permission を明示し、返却対象と filter / sort の適用範囲を閲覧可能 scope 配下へ限定するため
- `GET /api/policies/{id}`
  - required permissions: `object.read`
  - 理由: policies の詳細取得でも route で read permission を明示し、対象 record の `scope_id` に対する追加認可を service 側で行うため
- `GET /api/checklists/{id}`
  - required permissions: `object.read`
  - 理由: checklists の詳細取得でも route で read permission を明示し、対象 record の `scope_id` に対する追加認可を service 側で行うため
- `POST /api/policies`
  - required permissions: `object.create`
  - 理由: policies の作成でも route で create permission を明示し、入力された `scope_id` に対する追加認可を service 側で行うため
- `POST /api/checklists`
  - required permissions: `object.create`
  - 理由: checklists の作成でも route で create permission を明示し、入力された `scope_id` に対する追加認可を service 側で行うため
- `PATCH /api/policies/{id}`
  - required permissions: `object.update`
  - 理由: policies の更新でも route で update permission を明示し、対象 record の `scope_id` に対する追加認可を service 側で行うため
- `PATCH /api/checklists/{id}`
  - required permissions: `object.update`
  - 理由: checklists の更新でも route で update permission を明示し、対象 record の `scope_id` と必要に応じて移動先 `scope_id` に対する追加認可を service 側で行うため
- `DELETE /api/policies/{id}`
  - required permissions: `object.delete`
  - 理由: policies の削除でも route で delete permission を明示し、対象 record の `scope_id` に対する追加認可を service 側で行うため
- `DELETE /api/checklists/{id}`
  - required permissions: `object.delete`
  - 理由: checklists の削除でも route で delete permission を明示し、対象 record の `scope_id` に対する追加認可を service 側で行うため
- `GET /api/objects/{id}`
  - required permissions: `object.read`
  - 理由: 詳細取得でも route で read permission を明示したうえで、対象 object の `scope_id` に対する追加認可を service 側で行うため
- `PATCH /api/objects/{id}`
  - required permissions: `object.update`
  - 理由: 更新操作は route で update permission を明示し、対象 object の `scope_id` に対する追加認可を service 側で行うため。`scope_id` 変更時は移動先に対する `object.create` も必要
- `POST /api/objects`
  - required permissions: `object.create`
  - 理由: 作成操作は route で create permission を明示し、入力された `scope_id` に対する追加認可を service 側で行うため
- `DELETE /api/objects/{id}`
  - required permissions: `object.delete`
  - 理由: 削除操作は route で delete permission を明示し、対象 object の `scope_id` に対する追加認可を service 側で行うため

今後の業務 API は route 定義で `required_permissions` middleware を必ず宣言する。

例:

```php
Route::get('/objects', ObjectIndexController::class)
    ->middleware('required_permissions:object.read');
```

詳細 API では route middleware に加えて、service で対象 record の `scope_id` に対する認可判定を行う。

## 引継ぎメモ

### 業務 API の最初の入口を `GET /api/objects` として追加

- 背景: `required_permissions` middleware と `AuthorizationService` は入っていたが、実際の業務 route にまだ適用されておらず、次の実装で使い始める入口が必要だった
- 決定事項: `GET /api/objects` を最小業務 API として追加し、route には `required_permissions:object.read` を必須で付与する
- 影響範囲: `routes/api.php`、`app/Http/Controllers/Api/ObjectIndexController.php`、`app/Services/Object/ObjectIndexService.php`、関連 feature test
- 次の推奨アクション: `objects` の業務テーブルと scope 所属情報を追加し、`object.read` に加えて対象 scope 配下かどうかの認可判定まで拡張する

### `objects.scope_id` と scope 配下判定を認可の基本形として採用

- 背景: 業務 API で permission だけを見ても、どの server / service / tenant に属するデータを読めるかが表現できず、AP サーバー側での認可が閉じなかった
- 決定事項: 業務レコードは `scope_id` を持ち、`AuthorizationService` に「指定 permission でアクセス可能な scope 一覧を返す」処理を追加する。`GET /api/objects` はその結果で `objects.scope_id` を絞り込む
- 影響範囲: `database/migrations/2026_04_14_000000_create_objects_table.php`、`app/Models/ManagedObject.php`、`app/Services/Authorization/AuthorizationService.php`、`app/Services/Object/ObjectIndexService.php`、`tests/Feature/Api/ObjectIndexControllerTest.php`
- 次の推奨アクション: `AuthorizationService` の accessible scope 判定を `object.update` / `object.delete` など他操作にも使い回し、詳細 API では対象 object の `scope_id` に対する単体判定を導入する

### 詳細 API は route permission と object 単位の scope 判定を併用する

- 背景: 一覧 API の scope 絞り込みだけでは、詳細取得や更新で「対象 record を操作してよいか」を統一ルールで表現できなかった
- 決定事項: `GET /api/objects/{id}` と `PATCH /api/objects/{id}` を追加し、route では `required_permissions` middleware、service では `FindAuthorizedObject` から `AuthorizationService::canAccessScope()` を使って対象 object の `scope_id` を判定する
- 影響範囲: `routes/api.php`、`app/Http/Controllers/Api/ObjectShowController.php`、`app/Http/Controllers/Api/ObjectUpdateController.php`、`app/Services/Object/FindAuthorizedObject.php`、`app/Services/Object/ObjectShowService.php`、`app/Services/Object/ObjectUpdateService.php`、関連 feature test
- 次の推奨アクション: `DELETE /api/objects/{id}` や create API でも同じ構成を使い、`scope_id` の入力や移動を伴う更新時の認可ルールを明確化する

### 作成先 `scope_id` の認可も `canAccessScope()` で統一する

- 背景: create では対象 record がまだ存在しないため、詳細 API と同じ object 単位判定だけでは認可ルールを表現できなかった
- 決定事項: `POST /api/objects` を追加し、入力された `scope_id` に対して `AuthorizationService::canAccessScope(..., 'object.create', $scopeId)` を使って作成可否を判定する。`DELETE /api/objects/{id}` も `FindAuthorizedObject` を通して同じ構成で扱う
- 影響範囲: `routes/api.php`、`app/Http/Controllers/Api/ObjectStoreController.php`、`app/Http/Controllers/Api/ObjectDeleteController.php`、`app/Services/Object/EnsureAuthorizedScope.php`、`app/Services/Object/ObjectStoreService.php`、`app/Services/Object/ObjectDeleteService.php`、関連 feature test
- 次の推奨アクション: `scope_id` 自体を変更する更新 API を入れる場合は、「変更前 scope の update 権限」と「変更後 scope の create 権限」のどちらを見るかを明文化し、service 契約として固定する

### `scope_id` 移動は current scope の update と target scope の create を両方要求する

- 背景: object の移動は単なる属性更新ではなく、元の所属からの変更と新しい所属先への登録を同時に含むため、`object.update` だけでは認可境界が弱かった
- 決定事項: `PATCH /api/objects/{id}` で `scope_id` を変更する場合は、対象 object に対する `object.update` に加えて、移動先 `scope_id` に対する `object.create` を要求する
- 影響範囲: `app/Http/Controllers/Api/ObjectUpdateController.php`、`app/Services/Object/ObjectUpdateService.php`、`app/Services/Object/EnsureAuthorizedScope.php`、`tests/Feature/Api/ObjectUpdateControllerTest.php`
- 次の推奨アクション: 今後 `code` の一意性を scope ごとに厳密に扱うなら、移動時も `scope_id + code` の重複チェックを validation / service に追加する

### 一覧 API の pagination / filter も認可済み scope の内側で処理する

- 背景: 一覧 API に filter や pagination を加えると、実装次第では認可されていない scope の存在や件数を間接的に漏らすリスクがあった
- 決定事項: `GET /api/objects` は先に `accessibleScopeIds('object.read')` で認可済み scope を確定し、その内側だけで `scope_id` / `code` filter と pagination を適用する。返却には `meta.current_page`, `meta.per_page`, `meta.total`, `meta.last_page`, `meta.filters` を含める
- 影響範囲: `app/Http/Controllers/Api/ObjectIndexController.php`、`app/Services/Object/ObjectIndexService.php`、`tests/Feature/Api/ObjectIndexControllerTest.php`
- 次の推奨アクション: 一覧 API に `name` や `sort` を追加する場合も、同じ順序で「認可 scope 確定 -> filter -> pagination」を崩さずに拡張する

### `scope_id + code` の重複は API で先に 422 として返す

- 背景: `objects` テーブルには `scope_id + code` の unique 制約があるため、create / update で DB 例外に任せると API 契約が不安定になりやすかった
- 決定事項: `POST /api/objects` と `PATCH /api/objects/{id}` は保存前に `scope_id + code` の重複を確認し、重複時は `errors.code` を含む `422 Unprocessable Entity` を返す。`code` は trim、lowercase、空白 / `_` の `-` 正規化後の値で比較する
- 影響範囲: `app/Services/Object/ObjectCodeNormalizer.php`、`app/Services/Object/EnsureUniqueObjectCode.php`、`app/Services/Object/ObjectStoreService.php`、`app/Services/Object/ObjectUpdateService.php`、`app/Models/ManagedObject.php`、関連 feature test
- 次の推奨アクション: 今後 `code` の許可文字種をさらに絞るなら、validation と normalizer の責務分担を README で明文化する

### 一覧 API に `name` filter と `sort` を追加しても認可順序は変えない

- 背景: 一覧 API の使い勝手を上げるには filter / sort を増やしたいが、認可より先に評価すると見えないデータの存在を間接的に漏らす余地がある
- 決定事項: `GET /api/objects` に `name` filter と `sort` を追加しつつ、実行順序は引き続き「認可 scope 確定 -> filter -> sort -> pagination」とする。`sort` は `id`, `-id`, `code`, `-code`, `name`, `-name` のみ許可する
- 影響範囲: `app/Http/Controllers/Api/ObjectIndexController.php`、`app/Services/Object/ObjectIndexService.php`、`tests/Feature/Api/ObjectIndexControllerTest.php`
- 次の推奨アクション: 将来 `sort=scope_id` のような項目を増やすなら、公開してよい並び替えキーかどうかを README で明示してから追加する

### 一覧 query の共通処理は `ListQueryService` に寄せて横展開しやすくする

- 背景: resource が増えるたびに filter / sort / pagination を各 service へ都度実装すると、認可順序は同じでも query 仕様の差分が散らばりやすかった
- 決定事項: 一覧 query の contains filter、sort、pagination は `app/Services/Query/ListQueryService.php` に寄せ、resource 固有 service は「認可済み scope の確定」と「公開する filter / sort キーの選択」に集中する
- 影響範囲: `app/Services/Query/ListQueryService.php`、`app/Services/Object/ObjectIndexService.php`
- 次の推奨アクション: 次に別 resource の一覧 API を追加する場合も、同じ `ListQueryService` を使って認可 scope 確定後の query 組み立てをそろえる

### `playbooks` 一覧で `ListQueryService` の横展開を確認

- 背景: `ListQueryService` が `objects` 専用の抽象化に留まると、次の resource 追加時に本当に再利用できるか判断しづらかった
- 決定事項: `GET /api/playbooks` を追加し、`objects` と同じく route では `required_permissions:object.read`、service では `AuthorizationService::accessibleScopeIds()` の結果に対して `ListQueryService` で filter / sort / pagination を適用する
- 影響範囲: `database/migrations/2026_04_14_000001_create_playbooks_table.php`、`app/Models/Playbook.php`、`app/Http/Controllers/Api/PlaybookIndexController.php`、`app/Services/Playbook/PlaybookIndexService.php`、`routes/api.php`、`tests/Feature/Api/PlaybookIndexControllerTest.php`
- 次の推奨アクション: 次に playbooks の詳細 API や create/update/delete を追加する場合も、objects と同じ「route middleware + scope 判定 + 必要なら unique/code ルール」の順で広げる

### `playbooks` でも objects と同じ CRUD 責務分割を採用

- 背景: 一覧だけ再利用できても、詳細取得や更新・削除で責務分割が崩れると、resource ごとに設計がばらつくリスクがあった
- 決定事項: `playbooks` に対しても show/store/update/delete を追加し、objects と同じく「route middleware」「scope 判定 service」「unique code 判定」「payload 変換」の責務分割を採用する
- 影響範囲: `app/Http/Controllers/Api/Playbook*Controller.php`、`app/Services/Playbook/*`、`routes/api.php`、`tests/Feature/Api/Playbook*Test.php`
- 次の推奨アクション: 次に別 resource を追加する場合は、この `playbooks` 構成をテンプレートとして使い、共通化しすぎる前に 2-3 resource で安定する責務境界を見極める

### resource 共通化は「scope 一覧」「scope 単体取得」「scope 内 code 一意性」までに留める

- 背景: objects / playbooks の CRUD を並べると、controller や service の流れは似ていても payload 形状や正規化の有無まで完全共通化すると逆に読みづらくなる懸念があった
- 決定事項: 共通化は `app/Services/Resource/ScopedIndexQueryService.php`、`AuthorizedScopedResourceService.php`、`ScopedCodeUniquenessService.php` の 3 点に留め、resource 固有 service は payload 変換や入力解釈を担当する
- 影響範囲: `app/Services/Resource/*`、`app/Services/Object/*`、`app/Services/Playbook/*`
- 次の推奨アクション: controller/request validation を共通化する場合も、まずは `scope_id` / `code` / `name` の型や必須性のような入力スキーマだけに留め、resource 固有ルールは service に残す

### `policy` は作成後に scope を変更しない

- 背景: service 層の共通化が終わった段階で request validation まで一気に共通化すると、resource ごとの業務ルールが後から入りづらくなる懸念があった
- 決定事項: 差分確認のため `policy` には「作成後に `scope_id` を変更できない」業務ルールを追加し、`PATCH /api/policies/{id}` で別 scope への移動要求が来た場合は `422 Unprocessable Entity` を返す。`scope_id` の型や存在確認は controller validation に残し、resource 固有ルールは `PolicyUpdateService` で判定する
- 影響範囲: `app/Services/Policy/PolicyUpdateService.php`、`tests/Feature/Api/PolicyUpdateControllerTest.php`
- 次の推奨アクション: 次に controller 側を共通化する場合は「入力フォーマット共通化」に限定し、移動可否や状態遷移のような resource 固有ルールは service へ残す方針で他 resource にも当てはまるかを確認する
- 次の推奨アクション: 3 つ目の resource を追加した時点で、payload や create/update の共通化まで本当に必要かをあらためて見直す

### controller/request validation の共通化は入力スキーマまでに留める

- 背景: `objects` / `playbooks` / `policies` の store/update controller では `scope_id` / `code` / `name` の validation が重複していたが、前段で `policy` だけ移動不可の業務ルールを追加したことで、validation と業務判定を混ぜない境界を保つ必要がはっきりした
- 決定事項: 共通 request として `StoreScopedResourceRequest` と `UpdateScopedResourceRequest` を追加し、controller は共通の入力スキーマだけを受け持つ。resource 固有ルールや状態遷移制約は引き続き各 service で判定する
- 影響範囲: `app/Http/Requests/Api/StoreScopedResourceRequest.php`、`app/Http/Requests/Api/UpdateScopedResourceRequest.php`、`app/Http/Controllers/Api/*StoreController.php`、`app/Http/Controllers/Api/*UpdateController.php`
- 次の推奨アクション: 次に request 共通化を広げる場合は、resource 固有の追加項目が出たときに「共通 request の継承で足せるか」を見る。共通 request 自体へ業務ルールを押し込まない

### index controller の query validation も共通 request に寄せる

- 背景: 4 resource まで増えた時点で index controller には `scope_id` / `code` / `name` / `sort` / `page` / `per_page` の validation と数値キャストが同じ形で並び、HTTP 層の補助ロジックだけが重複していた
- 決定事項: `IndexScopedResourceRequest` を追加し、index controller は query validation と `scope_id` / `page` / `per_page` の数値正規化をこの request に委譲する。resource ごとの差分は引き続き index service 側の model 指定に留める
- 影響範囲: `app/Http/Requests/Api/IndexScopedResourceRequest.php`、`app/Http/Controllers/Api/*IndexController.php`
- 次の推奨アクション: 次に横断共通化を進めるなら、feature test に重複している Keycloak JWT セットアップを trait や helper に寄せ、resource ごとのテスト本体は認可と payload の差分だけに集中させる

### Keycloak JWT の test セットアップは trait と base test に寄せる

- 背景: API feature test が増えるにつれて、RSA 鍵生成、JWKS 偽装、Bearer トークン生成、AuthorizationSeeder の投入が各 test class に繰り返し現れ、resource 本体の差分が見えにくくなっていた
- 決定事項: Keycloak 用の共通 helper は `tests/Concerns/InteractsWithKeycloakTokens.php` に寄せ、API test 用の base class として `tests/Feature/Api/KeycloakApiTestCase.php`、`tests/Feature/Api/AuthorizationApiTestCase.php` を追加する。resource 系や認可系の feature test はこれらを継承し、各 class には業務シナリオ固有の setup だけを残す
- 影響範囲: `tests/Concerns/InteractsWithKeycloakTokens.php`、`tests/Feature/Api/KeycloakApiTestCase.php`、`tests/Feature/Api/AuthorizationApiTestCase.php`、`tests/Feature/Api/*Test.php`
- 次の推奨アクション: 次に test 共通化を進めるなら、`assignRole()` や fixture 作成の重複を resource 横断でまとめるのではなく、まずは JWT 以外でも完全一致している補助処理だけを trait 化する

### `assignRole()` の共通化は assignment の芯だけを trait に寄せる

- 背景: JWT セットアップを base test に寄せた後も、resource 系 feature test には `assignRole()` の重複が残っていた。一方で `ApUser::create()` と `updateOrCreate()` の選択や、一部 test の既定 scope code には差分があり、丸ごと 1 本化すると各 test の意図を潰す懸念があった
- 決定事項: 完全一致している「既定 scope 作成」と「`Role` 解決 + `UserRoleAssignment` 作成」だけを `tests/Concerns/InteractsWithAuthorizationAssignments.php` に切り出す。各 test class の `assignRole()` は薄いラッパーとして残し、`ApUser` の作り方や scope 生成の差分は各 test 側で保持する
- 影響範囲: `tests/Concerns/InteractsWithAuthorizationAssignments.php`、`tests/Feature/Api/AuthorizationApiTestCase.php`、`tests/Feature/Authorization/AuthorizationServiceTest.php`、`tests/Feature/Api/*Test.php`
- 次の推奨アクション: 次に test 共通化を進めるなら、`ApUser` の `create/updateOrCreate` 差分が本当に意味のある違いかを確認し、完全一致にそろえられる test だけを段階的に base helper へ寄せる

### `ApUser::create()` と `updateOrCreate()` の差分は維持しつつ、登録処理だけ trait 化する

- 背景: 前回の次アクションに沿って `ApUser` 登録差分を見直したところ、`ObjectUpdateControllerTest`、`PlaybookUpdateControllerTest`、`PolicyUpdateControllerTest`、`ChecklistUpdateControllerTest` では同じ `keycloak_sub` に対して 2 回 `assignRole()` を呼び、既存ユーザーへ複数 assignment を積むケースがあった。このため `updateOrCreate()` を `create()` にそろえると補助処理の意図が崩れる一方、`ApUser` の属性セット自体は完全一致していた
- 決定事項: `tests/Concerns/InteractsWithAuthorizationAssignments.php` に `createAuthorizationUser()` と `updateOrCreateAuthorizationUser()` を追加し、各 test class の `assignRole()` はどちらの登録方式を使うかだけを選ぶ薄いラッパーへさらに縮小する。`create`/`updateOrCreate` の使い分け自体は維持し、同一内容の属性セットだけを共通化する
- 影響範囲: `tests/Concerns/InteractsWithAuthorizationAssignments.php`、`tests/Feature/Authorization/AuthorizationServiceTest.php`、`tests/Feature/Api/*Test.php`
- 次の推奨アクション: 次に test helper を縮めるなら、`RequiredPermissionsMiddlewareTest` の既定 scope code のように意図差が残っている箇所には触れず、`assignRole()` のシグネチャや戻り値まで完全一致している class 群だけを候補にする

### 完全一致した `assignRole()` は create 系 / upsert 系の base test に寄せる

- 背景: 前回の整理後、resource 系 feature test の多くは `assignRole(string $keycloakSub, string $roleSlug, ?Scope $scope = null): Scope` という同一シグネチャ・同一戻り値になっており、差分は「`ApUser` を `create` するか `updateOrCreate` するか」だけに縮んでいた
- 決定事項: `tests/Feature/Api/CreateAuthorizationApiTestCase.php` と `tests/Feature/Api/UpsertAuthorizationApiTestCase.php` を追加し、完全一致していた `assignRole()` は各 test class から削除して base test へ移す。`AuthorizationServiceTest` と `RequiredPermissionsMiddlewareTest` はシグネチャや scope 生成意図が異なるため現状維持とする
- 影響範囲: `tests/Feature/Api/CreateAuthorizationApiTestCase.php`、`tests/Feature/Api/UpsertAuthorizationApiTestCase.php`、`tests/Feature/Api/Object*Test.php`
- 次の推奨アクション: 次に test 共通化を進めるなら、helper 本体ではなく test class 側に残っている未使用 import や旧 base class 由来のノイズを、挙動差を生まない範囲で整えるかを確認する

### 3 つ目の resource `policies` でも同じ共通化境界で成立することを確認

- 背景: 2 resource だけでは、共通 service がたまたま合っているのか、安定した境界なのか判断しづらかった
- 決定事項: `policies` を 3 つ目の CRUD resource として追加し、一覧は `ScopedIndexQueryService`、単体取得は `AuthorizedScopedResourceService`、`scope_id + code` の一意性は `ScopedCodeUniquenessService` を再利用する構成で実装した
- 影響範囲: `database/migrations/2026_04_14_000002_create_policies_table.php`、`app/Models/Policy.php`、`app/Http/Controllers/Api/Policy*Controller.php`、`app/Services/Policy/*`、`routes/api.php`、`tests/Feature/Api/Policy*Test.php`
- 次の推奨アクション: ここまでで共通化境界は十分安定しているため、次に共通化を広げるなら controller request validation や payload 変換ではなく、resource ごとの業務ルール差分が出てから必要性を見て判断する

### 4 つ目の resource `checklists` でも同じ共通化境界を維持する

- 背景: 3 resource までは同じ構成で実装できても、4 本目で controller/request 共通化や resource service の責務分割が崩れるなら、まだ境界が固定しきれていない可能性があった
- 決定事項: `checklists` を 4 つ目の CRUD resource として追加し、一覧は `ScopedIndexQueryService`、単体取得は `AuthorizedScopedResourceService`、`scope_id + code` の一意性は `ScopedCodeUniquenessService`、store/update の入力は `StoreScopedResourceRequest` / `UpdateScopedResourceRequest` をそのまま再利用する構成で実装した
- 影響範囲: `database/migrations/2026_04_14_000003_create_checklists_table.php`、`app/Models/Checklist.php`、`app/Http/Controllers/Api/Checklist*Controller.php`、`app/Services/Checklist/*`、`routes/api.php`、`tests/Feature/Api/Checklist*Test.php`
- 次の推奨アクション: 次に見直すなら CRUD resource の追加ではなく、index controller の query validation や JWT テストセットアップのように、4 resource で同じ形を保っている補助層に横断的な共通化余地があるかを確認する

### index query validation の共通契約は補助 trait で固定する

- 背景: `IndexScopedResourceRequest` を 4 resource で共通利用する構成にはなっていたが、invalid query の `422 Unprocessable Entity` 契約までは resource 横断で明示的に固定できていなかった
- 決定事項: `tests/Concerns/InteractsWithScopedIndexValidation.php` と `tests/Feature/Api/ScopedIndexValidationApiTestCase.php` を追加し、`objects` / `playbooks` / `policies` / `checklists` の各 index test から同じ invalid filter ケースを共有して検証する。これにより `scope_id` / `sort` / `page` / `per_page` の validation 契約は HTTP 補助層の共通仕様として扱う
- 影響範囲: `tests/Concerns/InteractsWithScopedIndexValidation.php`、`tests/Feature/Api/ScopedIndexValidationApiTestCase.php`、`tests/Feature/Api/ObjectIndexControllerTest.php`、`tests/Feature/Api/PlaybookIndexControllerTest.php`、`tests/Feature/Api/PolicyIndexControllerTest.php`、`tests/Feature/Api/ChecklistIndexControllerTest.php`
- 次の推奨アクション: 将来 resource ごとに許可する sort key や query 項目が分岐する場合は、controller 内で条件分岐を増やさず、`IndexScopedResourceRequest` の継承や resource 別 request 追加で HTTP 入力契約を分離する

### index controller は resource 別 request を受ける形へ先に分離する

- 背景: 前回の推奨アクションどおり、今後 resource ごとに許可する sort key や query 項目が分岐した際に、共通 request と controller に条件分岐を足し始めると HTTP 層の責務境界が崩れやすかった
- 決定事項: `IndexScopedResourceRequest` には共通ルールだけを残し、`allowedSorts()` を override 可能にしたうえで、`ObjectIndexRequest`、`PlaybookIndexRequest`、`PolicyIndexRequest`、`ChecklistIndexRequest` を追加した。各 index controller は対応する resource 別 request を受ける構成に切り替え、現時点の入力契約は維持する
- 影響範囲: `app/Http/Requests/Api/IndexScopedResourceRequest.php`、`app/Http/Requests/Api/ObjectIndexRequest.php`、`app/Http/Requests/Api/PlaybookIndexRequest.php`、`app/Http/Requests/Api/PolicyIndexRequest.php`、`app/Http/Requests/Api/ChecklistIndexRequest.php`、`app/Http/Controllers/Api/*IndexController.php`
- 次の推奨アクション: 実際に resource ごとの index query 差分が必要になったら、共通 request は触りすぎず、対象 resource の request class だけで `allowedSorts()` や追加 rule を拡張して契約差分を閉じ込める

### API feature test の不要 import は class 側から段階的に落とす

- 背景: test helper の共通化後も、一部 feature test には旧継承構成由来の不要 import が残っており、class 本体の意図より補助ノイズが目立つ箇所があった
- 決定事項: `MeControllerTest` と `MeAuthorizationControllerTest` から、継承元で置き換わっていて未使用になっていた `Tests\TestCase` / `AuthorizationSeeder` import を削除した。挙動差を生みやすい helper ロジックや scope 生成意図には触れず、class 冒頭の完全に不要な記述だけを整理する方針とした
- 影響範囲: `tests/Feature/Api/MeControllerTest.php`、`tests/Feature/Api/MeAuthorizationControllerTest.php`
- 次の推奨アクション: 次に test 側のノイズを減らすなら、同じく挙動差を生まない範囲で `tests/Feature/Api` 配下の未使用 import や空行ゆれだけを点検し、helper や fixture の責務には踏み込まない

### API feature test の空行ゆれは同型 class からそろえる

- 背景: import 整理の次に `tests/Feature/Api` を見直すと、同じタイミングで追加した index 系 test に class 末尾の空行ゆれが残っており、小さなことでも横並びの読みやすさを落としていた
- 決定事項: `ObjectIndexControllerTest`、`PlaybookIndexControllerTest`、`PolicyIndexControllerTest`、`ChecklistIndexControllerTest` の class 末尾にだけあった余分な空行を削除し、同型の test class で閉じ方をそろえた。helper や assertion 本体には触れず、整形差分だけに限定する
- 影響範囲: `tests/Feature/Api/ObjectIndexControllerTest.php`、`tests/Feature/Api/PlaybookIndexControllerTest.php`、`tests/Feature/Api/PolicyIndexControllerTest.php`、`tests/Feature/Api/ChecklistIndexControllerTest.php`
- 次の推奨アクション: 次に test 側を整えるなら、機械的にそろえられる空行や import のみを対象にし、assertion の表現統一や helper への寄せ直しのような意味差が入りうる変更は別タスクとして切り分ける

### API feature test の未使用 import は 1 file ずつ安全に落とす

- 背景: 前回の方針どおり `tests/Feature/Api` を点検すると、`MeAuthorizationControllerTest` に `Cache` / `Http` の import が残っていたが、test 本文では参照されていなかった
- 決定事項: `MeAuthorizationControllerTest` から未使用の `Illuminate\Support\Facades\Cache` と `Illuminate\Support\Facades\Http` import を削除した。複数 file を一度に機械処理せず、使用有無を確認できた file だけを個別に整える方針を継続する
- 影響範囲: `tests/Feature/Api/MeAuthorizationControllerTest.php`
- 次の推奨アクション: 次に test 側のノイズを減らすなら、同じく `tests/Feature/Api` 配下で「import はあるが `::` 参照や型参照が無い」file を 1 件ずつ確認し、削除前に README 更新と関連 test 実行をセットで行う

## コンテナでの作業

起動:

```bash
docker compose up -d ap-backend
```

live mode 検証で frontend から `http://localhost:8000/api` を叩く前提では、`ap-backend` は `docker/env/laravel.common.env` と `docker/env/ap-backend.env` を読み、host の `8000` 番を公開する。`ap-server/backend/.env` はローカル scaffold 用として残っていても、compose 経由で起動した `ap-backend` では container env を正として扱う。

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

live mode 初回セットアップ:

```bash
docker compose exec postgres psql -U myapp -d postgres -c "CREATE DATABASE ap_server"
docker compose exec ap-backend php artisan migrate --seed
docker compose exec ap-backend php artisan db:seed --class=ApUserManagementDemoSeeder --force
```

補足:

- `CREATE DATABASE ap_server` は初回だけ必要。既に存在する場合は skip してよい
- `ap-backend` image は `docker/laravel/init-laravel-app.sh` を entrypoint に使う。`docker compose up -d --build ap-backend` 後は container restart ごとに live env を `.env` へ反映し、`php artisan serve --host=0.0.0.0 --port=8000` まで自動で立ち上がる
- `docker/env/ap-backend.env` の `KEYCLOAK_CLIENT_ID` は既定で `global-login` を指す。`app-a` / `app-b` の token で検証する場合はここを合わせる
- `docker/env/ap-backend.env` の `KEYCLOAK_JWKS_URL` は container 内到達性を優先して `http://keycloak:8080/.../certs` を使う。`iss` は引き続き `https://keycloak.example.com/realms/myapp` を維持する
- `KEYCLOAK_JWKS_URL` を空にした場合でも、live compose では `KEYCLOAK_INTERNAL_BASE_URL` があればそこから `/protocol/openid-connect/certs` を優先して組み立てる。discovery が connection error になっても 500 ではなく次の fallback へ進む
- `php artisan migrate --seed` では `AuthorizationSeeder` が走り、roles / permissions の初期候補が入る。users / scopes / assignments の live 実測に必要なデータは次段で別途投入する
- `php artisan db:seed --class=ApUserManagementDemoSeeder --force` は live mode の最小確認用に `tenant-user-a` を `server_user_manager`、`tenant-user-b` を `tenant_viewer` として投入する

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
- API ごとの required permission は `required_permissions` middleware alias で宣言し、`AuthorizationService` が AP DB 上の集約 permission と突き合わせて判定する
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

### ap-backend live 起動は image entrypoint で固定する

- 背景: `docker/env/ap-backend.env` を追加しても、`docker/ap-backend/Dockerfile` が `tail -f /dev/null` のままだと container restart 後に `php artisan serve` が消え、host `8000` 公開だけ残って live 実測が不安定だった
- 決定事項: `docker/ap-backend/Dockerfile` は shared Laravel と同じ `docker/laravel/init-laravel-app.sh` を entrypoint に使い、restart ごとに `.env` へ live env を反映して `php artisan serve --host=0.0.0.0 --port=8000` を自動起動する。live 初回セットアップは `CREATE DATABASE ap_server`、`php artisan migrate --seed`、`php artisan db:seed --class=ApUserManagementDemoSeeder --force` までで揃える
- 影響範囲: `docker/ap-backend/Dockerfile`、`docker-compose.yml`、`docker/env/ap-backend.env`、ap-backend の再起動手順、frontend live mode の前提
- 次の推奨アクション: 次はこの entrypoint 前提で `docker compose up -d --build ap-backend` を共通起点にし、host 公開 `8000` 経由で `GET /api/me` が token を拾えない理由を `php artisan serve` 経路に絞って調べる

### Keycloak JWKS 解決は internal base url を優先し、network fail では 500 にしない

- 背景: live token を `/api/me` へ流したとき、`KEYCLOAK_JWKS_URL` が live env にある前提でも request 実行中に issuer discovery へ落ち、`https://keycloak.example.com/.../.well-known/openid-configuration` の connection error で 500 になる経路が残っていた
- 決定事項: `KeycloakJwksPublicKeyResolver` は `services.keycloak.jwks_url` を最優先にし、未設定時は `services.keycloak.internal_base_url + /protocol/openid-connect/certs` を次優先に使う。JWKS 取得と discovery は `ConnectionException` を握り潰して `null` を返し、次の fallback へ進む。`MeControllerTest` には `AUTHORIZATION`、`HTTP_X_FORWARDED_AUTHORIZATION`、`access_token` query、internal base url、discovery connection failure の coverage を追加する
- 影響範囲: `app/Services/Auth/KeycloakJwksPublicKeyResolver.php`、`app/Services/Auth/CurrentUserResolver.php`、`config/services.php`、`tests/Feature/Api/MeControllerTest.php`
- 次の推奨アクション: 次は host から `http://127.0.0.1:8000/api/*` を叩いたときに `Authorization` / query token が `php artisan serve` request へ見えていない点を切り分け、必要なら live 検証専用に nginx 経由へ切り替える

### nginx proxy で `ap-backend:8000` を挟んでも live auth は変わらない

- 背景: `php artisan serve` 直叩きだけが問題なのかを確かめるため、nginx に `global.example.com/ap-api/* -> ap-backend:8000/api/*` の proxy を追加し、`Authorization` と `X-Forwarded-Authorization` をそのまま流した
- 決定事項: `GET /ap-api/health` は成功したが、`GET /ap-api/me` と `GET /ap-api/me/authorization` は direct `http://127.0.0.1:8000/api/*` と同じく `current_user: null` だった。よって reverse proxy の有無ではなく、`php artisan serve` を含む現行実行経路全体を外した比較が必要と判断し、`ap-backend` image は `php:8.3-fpm` ベースへ寄せ、`/usr/local/bin/init-laravel-fpm-app` と compose service `ap-backend-fpm`、nginx host `ap-backend-fpm.example.com` を追加する方針にした
- 影響範囲: `nginx/conf.d/default.conf`、`docker-compose.yml`、`docker/ap-backend/Dockerfile`、`docker/laravel/init-laravel-fpm-app.sh`、次回の live auth 比較経路
- 次の推奨アクション: 次は `docker compose up -d --build ap-backend ap-backend-fpm nginx` を完了させ、`https://ap-backend.example.com/api/me` と `https://ap-backend-fpm.example.com/api/me` を同じ token で比較して FastCGI 経路だけで解決するかを確認する

### live auth の差分比較では token expiry を先に除外する

- 背景: `ap-backend` と `ap-backend-fpm` の比較中に `current_user: null` が再発したが、後で確認すると比較に使っていた `alice` token がすでに期限切れで、resolver を tinker で直叩きしても `null` になる状態だった。HTTP 経路の差に見えていたものが token expiry の影響だった
- 決定事項: live auth 比較では、まず fresh token を取り直して `KeycloakTokenCurrentUserResolver::resolveFromBearerToken()` が `tenant-user-a` を返すことを確認してから HTTP endpoint を比較する。fresh token では direct `http://127.0.0.1:8000/api/*`、`https://ap-backend.example.com/api/*`、`https://ap-backend-fpm.example.com/api/*` の 3 経路すべてで `GET /api/me` と `GET /api/me/authorization` が成功した
- 影響範囲: live token 比較手順、`ap-backend` / `ap-backend-fpm` の切り分け結果、backend README の検証前提、frontend live mode の API base 候補
- 次の推奨アクション: 次は `https://ap-backend-fpm.example.com/api` を frontend live mode の実利用先として users 一覧 / 詳細 / assignment UI を画面で通し、必要なら CORS や cookie ではなく Bearer token 前提の運用メモを追加する

### required permissions ベースの API 認可入口

- 背景: `GET /api/me/authorization` で現在ユーザーの権限可視化はできるが、API 本体で required permission を宣言して 403 判定する入口がまだ無かった
- 決定事項: 認可判定は `app/Services/Authorization/AuthorizationService.php` に寄せ、Laravel 入口は `required_permissions` middleware alias として `->middleware('required_permissions:object.read')` の形で API ごとに宣言する
- 影響範囲: `ap-server/backend` の API ルーティング、Controller / middleware 設計、Feature test の protected route 作成方針
- 次の推奨アクション: 各 API の required permission 一覧を整理し、実運用ルートへ `required_permissions` を順次適用する。スコープ継承込みの判定が必要になった時点で `AuthorizationService` を拡張する

### 現在の public / introspection API の扱い

- 背景: `required_permissions` を追加した時点では、`ap-server/backend` の実 API が `health`、`me`、`me/authorization` に限られており、いずれも業務操作ではなかった
- 決定事項: 現時点の 3 endpoint は required permissions を付けず、`health` は public、`me` / `me/authorization` は未認証時も `null` を返す introspection endpoint として維持する
- 影響範囲: `routes/api.php` の route 設計、Feature test の期待値、今後の API 追加時の認可適用判断
- 次の推奨アクション: 業務データを扱う API を追加する際は、route 定義と README の required permissions 一覧を同時に更新する

### API feature test の未使用 import は 1 file ずつ README と test をセットで進める

- 背景: 直前の引継ぎどおり `tests/Feature/Api` 配下のノイズを減らす次段では、複数 file をまとめて機械処理せず、未使用 import を本文参照まで確認できた file に限定して安全に落とす必要があった
- 決定事項: 今回は `tests/Feature/Api/ChecklistDeleteControllerTest.php` だけを対象にし、本文でも型参照でも未使用だった `App\Models\Scope` import を削除した。関連確認もこの file の feature test 実行に閉じ、横展開は次タスクに分離する
- 影響範囲: `tests/Feature/Api/ChecklistDeleteControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に同種の整理を進める場合も `tests/Feature/Api` 配下の候補を 1 file だけ選び、未使用 import の実参照確認、README 更新、対象 feature test 実行を同じ作業単位で行う

### API feature test の未使用 import 整理は同系統 file でも 1 件ずつ継続する

- 背景: 前回の整理後も、同じ `UpsertAuthorizationApiTestCase` 系の store / delete test に `Scope` import が残っており、1 file ずつ進める方針を継続しないと、複数 resource をまとめて触る機械差分になりやすかった
- 決定事項: 今回は `tests/Feature/Api/ChecklistStoreControllerTest.php` だけを対象にし、本文でも型参照でも未使用だった `App\Models\Scope` import を削除した。前回と同様に対象 test だけを実行し、隣接 file への横展開はまだ行わない
- 影響範囲: `tests/Feature/Api/ChecklistStoreControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に同種の整理を進める場合は、残っている `Scope` import 候補から 1 file を選び、今回と同じく README 更新と対象 feature test 実行をセットで進める

### API feature test の未使用 import 整理は resource をまたいでも 1 file 単位を崩さない

- 背景: checklist 系を 2 件整理した後も、playbook / policy / checklist の show・store・delete test に同種の `Scope` import が残っており、resource 単位でまとめて処理すると「安全確認済みの 1 file 編集」という前提が崩れやすかった
- 決定事項: 今回は `tests/Feature/Api/PlaybookDeleteControllerTest.php` だけを対象にし、本文でも型参照でも未使用だった `App\Models\Scope` import を削除した。確認もこの file の feature test 実行に限定し、他 resource の同型 file は次タスクへ残す
- 影響範囲: `tests/Feature/Api/PlaybookDeleteControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に同種の整理を進める場合も、残りの show / store / delete test から 1 file を選び、未使用 import の実参照確認、README 更新、対象 feature test 実行を同じ単位で継続する

### API feature test の未使用 import 整理は delete 系でも resource ごとに分けて進める

- 背景: delete controller test には checklist / playbook / policy で同型の未使用 `Scope` import が残っていたが、複数 resource を同時に触ると「1 file ずつ確認して進める」という引継ぎ方針から外れやすかった
- 決定事項: 今回は `tests/Feature/Api/PolicyDeleteControllerTest.php` だけを対象にし、本文でも型参照でも未使用だった `App\Models\Scope` import を削除した。確認もこの file の feature test 実行だけに閉じ、他の delete / show / store test は別作業のまま維持する
- 影響範囲: `tests/Feature/Api/PolicyDeleteControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に同種の整理を進める場合は、残っている show / store test の候補から 1 file を選び、未使用 import の実参照確認、README 更新、対象 feature test 実行を今回と同じ粒度で続ける

### API feature test の未使用 import 整理は show 系も 1 file ずつ閉じる

- 背景: delete 系を個別に整理したあとも、show 系 test に同型の `Scope` import が残っており、resource を横断して一括削除すると README に残している「1 file 単位での安全確認」が曖昧になりやすかった
- 決定事項: 今回は `tests/Feature/Api/ChecklistShowControllerTest.php` だけを対象にし、本文でも型参照でも未使用だった `App\Models\Scope` import を削除した。確認もこの file の feature test 実行だけに限定し、他の show / store test は次タスクへ分離する
- 影響範囲: `tests/Feature/Api/ChecklistShowControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に同種の整理を進める場合は、残っている show / store test の候補から 1 file を選び、未使用 import の実参照確認、README 更新、対象 feature test 実行を同じ単位で継続する

### API feature test の未使用 import 整理は残件が見えている場合に限り連続実施で畳む

- 背景: `ChecklistShowControllerTest` 整理後に残候補を再確認すると、未使用 `Scope` import は `PolicyShowControllerTest` と `PolicyStoreControllerTest` の 2 file のみだった。今回の依頼では、この整理タスクに限って変更確認や `docker compose exec` 実行確認は不要と明示されたため、残件を連続で片づけてシリーズを閉じる方が引継ぎ上も分かりやすかった
- 決定事項: `tests/Feature/Api/PolicyShowControllerTest.php` と `tests/Feature/Api/PolicyStoreControllerTest.php` から、本文でも型参照でも未使用だった `App\Models\Scope` import を削除した。今回の整理ではユーザー指示に従い、各 file ごとの確認メッセージや `docker compose exec` による個別 test 実行は省略した
- 影響範囲: `tests/Feature/Api/PolicyShowControllerTest.php`、`tests/Feature/Api/PolicyStoreControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 側のノイズを減らすなら、今回と同じ「未使用 import」だけに限定せず、`tests/Feature/Api` 配下で空行ゆれや import 並びのような意味差を生まない整形差分がまだ残っているかを 1 テーマずつ確認する

### API feature test の class 末尾空行ゆれは同型の整形差分としてまとめてそろえる

- 背景: 未使用 import 整理の次に `tests/Feature/Api` を点検すると、一部の CRUD controller test で class 末尾の `}` 直前にだけ空行が残っており、同じ系統の test class 間で閉じ方がそろっていなかった
- 決定事項: class 末尾直前の余分な空行だけを対象にし、`ChecklistUpdateControllerTest`、`ObjectDeleteControllerTest`、`ObjectShowControllerTest`、`ObjectStoreControllerTest`、`ObjectUpdateControllerTest`、`PlaybookShowControllerTest`、`PlaybookStoreControllerTest`、`PlaybookUpdateControllerTest`、`PolicyUpdateControllerTest` の閉じ方をそろえた。assertion や helper、import には触れず、整形差分だけに限定する
- 影響範囲: `tests/Feature/Api/ChecklistUpdateControllerTest.php`、`tests/Feature/Api/ObjectDeleteControllerTest.php`、`tests/Feature/Api/ObjectShowControllerTest.php`、`tests/Feature/Api/ObjectStoreControllerTest.php`、`tests/Feature/Api/ObjectUpdateControllerTest.php`、`tests/Feature/Api/PlaybookShowControllerTest.php`、`tests/Feature/Api/PlaybookStoreControllerTest.php`、`tests/Feature/Api/PlaybookUpdateControllerTest.php`、`tests/Feature/Api/PolicyUpdateControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 側のノイズを減らすなら、`tests/Feature/Api` 配下の import 並びや空行配置のうち、class 末尾以外にも意味差を生まない整形ゆれが残っていないかを 1 テーマずつ確認する

### API feature test の import 並びと末尾スペースは現時点で追加整形不要

- 背景: class 末尾空行をそろえた次のテーマとして `tests/Feature/Api` 配下の import 並びと末尾スペースを点検したが、ここで無理に差分を作ると「整形のための整形」になりやすかった
- 決定事項: import block の昇順ゆれと末尾スペースを確認した結果、追加で直すべき file は見つからなかったため、このテーマではコード変更を行わない。意味差を生まない整形差分は、機械的に不一致が確認できたものだけを対象にする方針を継続する
- 影響範囲: `tests/Feature/Api` 配下の整形判断、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 側のノイズを減らすなら、整形テーマの探索はいったん区切り、`tests/Feature/Api` とその基底 test case で責務の薄い重複 helper や fixture 構築がないかを「挙動を変えない小さな共通化候補」として確認する

### API feature test の小さな共通化候補は bearer 付与と role 割り当て helper に絞る

- 背景: 前回の引継ぎどおり、`tests/Feature/Api` と基底 test case を対象に「挙動を変えない小さな共通化候補」を点検したところ、一覧・CRUD・middleware test をまたいで同じ Bearer token 付与と認可 user 準備が何度も繰り返されていた。一方で response の厳密 assertion や resource ごとの fixture 構築は、共通化すると test の読みやすさを落としやすかった
- 決定事項: 現時点で次の候補だけを「小さくて安全な共通化対象」として扱う。1) `KeycloakApiTestCase` か `InteractsWithKeycloakTokens` に、`withHeader('Authorization', 'Bearer '.$this->buildAccessToken(...))` を置き換える薄い helper を追加する候補。これは `ObjectStoreControllerTest`、`ObjectUpdateControllerTest`、`PlaybookIndexControllerTest`、`RequiredPermissionsMiddlewareTest` など `tests/Feature/Api` 全体で繰り返されている。2) `RequiredPermissionsMiddlewareTest` の private `assignRole()` は、既存の `CreateAuthorizationApiTestCase::assignRole()` と責務が近いため、既存 helper を使う方向で寄せる候補。逆に、`CreateAuthorizationApiTestCase` と `UpsertAuthorizationApiTestCase` の統合や、index / CRUD test の JSON assertion 共通化は、create と upsert の意味差や test 可読性を崩す可能性があるため今回の小さな共通化対象には含めない
- 影響範囲: `tests/Feature/Api/KeycloakApiTestCase.php`、`tests/Concerns/InteractsWithKeycloakTokens.php`、`tests/Feature/Api/CreateAuthorizationApiTestCase.php`、`tests/Feature/Api/RequiredPermissionsMiddlewareTest.php`、`tests/Feature/Api` 配下の Bearer token 利用 test
- 次の推奨アクション: 実装に進む場合は、まず Bearer token 付与 helper を 1 つ追加し、影響範囲が狭い `RequiredPermissionsMiddlewareTest` から既存 helper への寄せ替えを行う。その後、`tests/Feature/Api` の 1 系統だけで新 helper へ置換し、対象 feature test 実行で挙動差がないことを確認してから横展開する

### API feature test の最初の共通化実装は middleware test だけで閉じる

- 背景: 小さな共通化候補の確認後、いきなり `tests/Feature/Api` 全体を置換すると差分が広がりやすいため、まずは影響範囲が読みやすい `RequiredPermissionsMiddlewareTest` だけで helper 導入の安全性を確かめる必要があった
- 決定事項: `tests/Concerns/InteractsWithKeycloakTokens.php` に `withAccessToken()` を追加し、Bearer header 構築を 1 箇所へ寄せた。利用側の最初の適用先は `tests/Feature/Api/RequiredPermissionsMiddlewareTest.php` のみに限定し、この test は `CreateAuthorizationApiTestCase` を継承して既存 `assignRole()` を使う形へ寄せ替え、file 内 private helper は削除した
- 影響範囲: `tests/Concerns/InteractsWithKeycloakTokens.php`、`tests/Feature/Api/RequiredPermissionsMiddlewareTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に横展開する場合は、`InteractsWithScopedIndexValidation` を使う index 系か、単独 file で閉じやすい middleware / show 系のどちらか 1 系統だけを選び、`withAccessToken()` への置換と対象 feature test 実行をセットで進める

### API feature test の access token helper 横展開は index 系を 1 file と concern に限定する

- 背景: `withAccessToken()` の最初の導入後、次の横展開先として index 系を選ぶ場合でも、`ObjectIndexControllerTest` まで一気に広げると変更量が急に増えるため、まずは shared concern と単独 file の組み合わせで安全性を確かめる段階が必要だった
- 決定事項: 今回は `tests/Concerns/InteractsWithScopedIndexValidation.php` と `tests/Feature/Api/PlaybookIndexControllerTest.php` だけを `withAccessToken()` へ置換した。`PolicyIndexControllerTest`、`ChecklistIndexControllerTest`、`ObjectIndexControllerTest` はまだ未変更のまま残し、index 系でも 1 concern + 1 file 以上には広げない
- 影響範囲: `tests/Concerns/InteractsWithScopedIndexValidation.php`、`tests/Feature/Api/PlaybookIndexControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に同じ helper の横展開を続ける場合は、今回と同じ index 系の残り 1 file ずつへ進めるか、show 系 1 file に切り替えるかのどちらかに限定し、毎回 README 更新と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は index 系でも 1 file ずつ継続する

- 背景: index 系の最初の横展開で `InteractsWithScopedIndexValidation` と `PlaybookIndexControllerTest` が通った後も、`ChecklistIndexControllerTest` には同型の Bearer header 構築が残っていた。ただし、ここで `PolicyIndexControllerTest` や `ObjectIndexControllerTest` までまとめて触ると再び差分が広がるため、README に残している 1 file 単位を維持する必要があった
- 決定事項: 今回は `tests/Feature/Api/ChecklistIndexControllerTest.php` だけを `withAccessToken()` へ置換した。shared concern 側は前回の変更をそのまま利用し、今回新たに他の index / show / CRUD test へは広げていない
- 影響範囲: `tests/Feature/Api/ChecklistIndexControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に続ける場合は、index 系なら `PolicyIndexControllerTest` か `ObjectIndexControllerTest` のどちらか 1 file だけ、別系統へ切り替えるなら show 系 1 file だけを選び、同じく README 更新と対象 feature test 実行をセットで進める

### API feature test の access token helper 横展開は index 系の残 file も 1 件ずつ閉じる

- 背景: `ChecklistIndexControllerTest` の整理後も、index 系では `PolicyIndexControllerTest` と `ObjectIndexControllerTest` に同型の `withHeader('Authorization', 'Bearer '.$this->buildAccessToken(...))` が残っていた。ここで 2 file 同時に進めると、README で維持している「1 file ごとの安全確認」の粒度が崩れるため、残件も個別に閉じる必要があった
- 決定事項: 今回は `tests/Feature/Api/PolicyIndexControllerTest.php` だけを `withAccessToken()` へ置換した。`ObjectIndexControllerTest` は未変更のまま残し、index 系の横展開もまだシリーズで一括変更しない
- 影響範囲: `tests/Feature/Api/PolicyIndexControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、index 系を続けるなら `ObjectIndexControllerTest` だけ、別系統に切り替えるなら show 系 1 file だけを選び、同じく README 更新と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は object index を最後に index 系を一段落する

- 背景: index 系の 1 file ずつの横展開を続けた結果、最後に残っていた Bearer header 直書きは `ObjectIndexControllerTest` だけになっていた。ここを閉じれば index 系は `withAccessToken()` へ一通り寄せられるため、次のテーマを show 系など別の 1 file 単位へ切り替えやすくなる
- 決定事項: 今回は `tests/Feature/Api/ObjectIndexControllerTest.php` の access token 付与 6 箇所だけを `withAccessToken()` へ置換した。shared concern や assertion には追加変更を入れず、index 系の helper 横展開はこの file をもって一段落とする
- 影響範囲: `tests/Feature/Api/ObjectIndexControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に同じ方針で進める場合は、show 系の `PlaybookShowControllerTest` か `ChecklistShowControllerTest` のどちらか 1 file だけを選び、`withAccessToken()` への置換と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は show 系も 1 file から再開する

- 背景: index 系の横展開が一段落したため、次は別系統へ切り替える必要があった。show 系でも同型の Bearer header 構築が残っているが、最初から複数 resource をまとめると index 系で維持してきた「1 file ごとの安全確認」が崩れやすい
- 決定事項: 今回は show 系の入口として `tests/Feature/Api/PlaybookShowControllerTest.php` だけを `withAccessToken()` へ置換した。`ChecklistShowControllerTest` や他の show / CRUD test にはまだ触れず、show 系でも 1 file 単位を継続する
- 影響範囲: `tests/Feature/Api/PlaybookShowControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、show 系を続けるなら `ChecklistShowControllerTest` だけ、別系統へ切り替えるなら store 系 1 file だけを選び、同じく README 更新と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は show 系も残 file を 1 件ずつ閉じる

- 背景: `PlaybookShowControllerTest` の整理後も、show 系では `ChecklistShowControllerTest` に同型の Bearer header 構築が残っていた。ここで別系統へ移る前に、show 系の残件も 1 file 単位で閉じておく方が次の判断を単純にできる
- 決定事項: 今回は `tests/Feature/Api/ChecklistShowControllerTest.php` だけを `withAccessToken()` へ置換した。show 系でも他 file への横展開はまだ行わず、1 file ごとの README 更新と test 実行を継続する
- 影響範囲: `tests/Feature/Api/ChecklistShowControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、store 系の `ChecklistStoreControllerTest` か `PlaybookStoreControllerTest` のどちらか 1 file だけを選び、同じく `withAccessToken()` への置換と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は store 系も 1 file から切り替える

- 背景: show 系の残件を閉じたあと、次の横展開先として store 系にも同型の Bearer header 構築が残っていた。ただし、show 系と同様に複数 resource を同時に触ると差分が広がるため、store 系でもまず 1 file だけで安全性を確認する必要があった
- 決定事項: 今回は store 系の入口として `tests/Feature/Api/ChecklistStoreControllerTest.php` だけを `withAccessToken()` へ置換した。`PlaybookStoreControllerTest` や他の store / update / delete test にはまだ触れていない
- 影響範囲: `tests/Feature/Api/ChecklistStoreControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、store 系を続けるなら `PlaybookStoreControllerTest` だけ、別系統へ切り替えるなら update 系 1 file だけを選び、同じく README 更新と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は store 系の残 file も 1 件ずつ閉じる

- 背景: `ChecklistStoreControllerTest` の整理後も、store 系では `PlaybookStoreControllerTest` に同型の Bearer header 構築が残っていた。ここで update 系へ移る前に、store 系の残件を 1 file 単位で閉じておくと次の系統切り替えを整理しやすい
- 決定事項: 今回は `tests/Feature/Api/PlaybookStoreControllerTest.php` だけを `withAccessToken()` へ置換した。store 系でも他 file への横展開はまだ行わず、1 file ごとの README 更新と test 実行を継続する
- 影響範囲: `tests/Feature/Api/PlaybookStoreControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、update 系の `ChecklistUpdateControllerTest` か `PlaybookUpdateControllerTest` のどちらか 1 file だけを選び、同じく `withAccessToken()` への置換と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は update 系も 1 file から切り替える

- 背景: store 系の残件を閉じたあと、次の横展開先として update 系にも同型の Bearer header 構築が残っていた。update 系は移動パターンを含んでいるため、なおさら複数 file をまとめず、まず 1 file だけで安全性を確認する必要があった
- 決定事項: 今回は update 系の入口として `tests/Feature/Api/ChecklistUpdateControllerTest.php` だけを `withAccessToken()` へ置換した。`PlaybookUpdateControllerTest` や他の update / delete test にはまだ触れていない
- 影響範囲: `tests/Feature/Api/ChecklistUpdateControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、update 系を続けるなら `PlaybookUpdateControllerTest` だけ、別系統へ切り替えるなら delete 系 1 file だけを選び、同じく README 更新と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は update 系の残 file も 1 件ずつ閉じる

- 背景: `ChecklistUpdateControllerTest` の整理後も、update 系では `PlaybookUpdateControllerTest` に同型の Bearer header 構築が残っていた。ここで delete 系へ移る前に、update 系の残件も 1 file 単位で閉じておく方が次の系統切り替えを整理しやすい
- 決定事項: 今回は `tests/Feature/Api/PlaybookUpdateControllerTest.php` だけを `withAccessToken()` へ置換した。update 系でも他 file への横展開はまだ行わず、1 file ごとの README 更新と test 実行を継続する
- 影響範囲: `tests/Feature/Api/PlaybookUpdateControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、delete 系の `ChecklistDeleteControllerTest` か `PlaybookDeleteControllerTest` のどちらか 1 file だけを選び、同じく `withAccessToken()` への置換と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は delete 系も 1 file から切り替える

- 背景: update 系の残件を閉じたあと、次の横展開先として delete 系にも同型の Bearer header 構築が残っていた。delete 系は file ごとの差分が小さいため、ここでも複数 resource をまとめず 1 file 単位で進める方が引継ぎしやすい
- 決定事項: 今回は delete 系の入口として `tests/Feature/Api/ChecklistDeleteControllerTest.php` だけを `withAccessToken()` へ置換した。`PlaybookDeleteControllerTest` や他の delete / object 系 test にはまだ触れていない
- 影響範囲: `tests/Feature/Api/ChecklistDeleteControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、delete 系を続けるなら `PlaybookDeleteControllerTest` だけ、別系統へ切り替えるなら object 系 CRUD 1 file だけを選び、同じく README 更新と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は delete 系の残 file も 1 件ずつ閉じる

- 背景: `ChecklistDeleteControllerTest` の整理後も、delete 系では `PlaybookDeleteControllerTest` に同型の Bearer header 構築が残っていた。ここで object 系へ移る前に、delete 系の残件も 1 file 単位で閉じておく方が次の系統切り替えを整理しやすい
- 決定事項: 今回は `tests/Feature/Api/PlaybookDeleteControllerTest.php` だけを `withAccessToken()` へ置換した。delete 系でも他 file への横展開はまだ行わず、1 file ごとの README 更新と test 実行を継続する
- 影響範囲: `tests/Feature/Api/PlaybookDeleteControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、object 系 CRUD の `ObjectShowControllerTest` か `ObjectStoreControllerTest` のどちらか 1 file だけを選び、同じく `withAccessToken()` への置換と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は object 系も 1 file から切り替える

- 背景: delete 系の残件を閉じたあと、次の横展開先として object 系 CRUD にも同型の Bearer header 構築が残っていた。object 系は not found / forbidden / success の分岐が多いため、ここでも複数 file をまとめず 1 file だけで安全性を確認する必要があった
- 決定事項: 今回は object 系の入口として `tests/Feature/Api/ObjectShowControllerTest.php` だけを `withAccessToken()` へ置換した。`ObjectStoreControllerTest`、`ObjectUpdateControllerTest`、`ObjectDeleteControllerTest` にはまだ触れていない
- 影響範囲: `tests/Feature/Api/ObjectShowControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、object 系を続けるなら `ObjectStoreControllerTest` だけ、別系統へ切り替えるなら `ObjectDeleteControllerTest` か `ObjectUpdateControllerTest` のどちらか 1 file だけを選び、同じく README 更新と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は object 系も残 file を 1 件ずつ閉じる

- 背景: `ObjectShowControllerTest` の整理後も、object 系では `ObjectStoreControllerTest`、`ObjectUpdateControllerTest`、`ObjectDeleteControllerTest` に同型の Bearer header 構築が残っていた。ここでも複数 file を一度に触ると分岐の多い object 系で差分確認が重くなるため、残件も 1 file 単位で閉じる必要があった
- 決定事項: 今回は `tests/Feature/Api/ObjectStoreControllerTest.php` だけを `withAccessToken()` へ置換した。`ObjectUpdateControllerTest` と `ObjectDeleteControllerTest` は未変更のまま残し、object 系でも 1 file ごとの README 更新と test 実行を継続する
- 影響範囲: `tests/Feature/Api/ObjectStoreControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、object 系の `ObjectDeleteControllerTest` か `ObjectUpdateControllerTest` のどちらか 1 file だけを選び、同じく `withAccessToken()` への置換と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は object 系の delete も 1 file で閉じる

- 背景: `ObjectStoreControllerTest` の整理後も、object 系では `ObjectDeleteControllerTest` と `ObjectUpdateControllerTest` に同型の Bearer header 構築が残っていた。ここでも複数 file を同時に触ると object 系の分岐確認が重くなるため、delete と update も 1 file 単位で閉じる必要があった
- 決定事項: 今回は `tests/Feature/Api/ObjectDeleteControllerTest.php` だけを `withAccessToken()` へ置換した。`ObjectUpdateControllerTest` は未変更のまま残し、object 系でも 1 file ごとの README 更新と test 実行を継続する
- 影響範囲: `tests/Feature/Api/ObjectDeleteControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、object 系の `ObjectUpdateControllerTest` だけを選び、同じく `withAccessToken()` への置換と対象 feature test 実行をセットで継続する

### API feature test の access token helper 横展開は object update を最後に object 系を一段落する

- 背景: object 系の 1 file ずつの横展開を続けた結果、最後に残っていた Bearer header 直書きは `ObjectUpdateControllerTest` だけになっていた。ここを閉じれば object 系 CRUD も `withAccessToken()` へ一通り寄せられるため、次のテーマを別の重複パターン確認へ切り替えやすくなる
- 決定事項: 今回は `tests/Feature/Api/ObjectUpdateControllerTest.php` の access token 付与 8 箇所だけを `withAccessToken()` へ置換した。validation / forbidden / move を含む assertion や fixture には追加変更を入れず、object 系の helper 横展開はこの file をもって一段落とする
- 影響範囲: `tests/Feature/Api/ObjectUpdateControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に同じ観点で進める場合は、`tests/Feature/Api` 全体で `withAccessToken()` 置換が残っていないかを再点検し、残件が無ければ次の「挙動を変えない小さな共通化候補」を README を起点に洗い出す

### API feature test の access token helper 残件は policy 系だけなので 1 file ずつ閉じる

- 背景: `tests/Feature/Api` 全体を再点検した結果、`withHeader('Authorization', 'Bearer '.$this->buildAccessToken(...))` の残件は `PolicyShowControllerTest`、`PolicyDeleteControllerTest`、`PolicyStoreControllerTest`、`PolicyUpdateControllerTest` の 4 file に限られていた。残件が同一 resource 系に絞れたため、ここでも series を一括で触らず 1 file ごとの確認を維持する方が安全だった
- 決定事項: 今回は最小の残件である `tests/Feature/Api/PolicyShowControllerTest.php` だけを `withAccessToken()` へ置換した。残る policy 系 3 file は未変更のまま残し、README 更新と対象 feature test 実行を 1 file ごとに継続する
- 影響範囲: `tests/Feature/Api/PolicyShowControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、policy 系の `PolicyDeleteControllerTest`、`PolicyStoreControllerTest`、`PolicyUpdateControllerTest` のいずれか 1 file だけを選び、同じく `withAccessToken()` への置換と対象 feature test 実行をセットで継続する

### API feature test の access token helper 残件は policy delete も 1 file で閉じる

- 背景: `PolicyShowControllerTest` の整理後も、policy 系では `PolicyDeleteControllerTest`、`PolicyStoreControllerTest`、`PolicyUpdateControllerTest` に同型の Bearer header 構築が残っていた。ここでも series をまとめて触らず 1 file ごとの確認を維持する方が安全だった
- 決定事項: 今回は `tests/Feature/Api/PolicyDeleteControllerTest.php` だけを `withAccessToken()` へ置換した。残る policy 系 2 file は未変更のまま残し、README 更新と対象 feature test 実行を 1 file ごとに継続する
- 影響範囲: `tests/Feature/Api/PolicyDeleteControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、policy 系の `PolicyStoreControllerTest` か `PolicyUpdateControllerTest` のどちらか 1 file だけを選び、同じく `withAccessToken()` への置換と対象 feature test 実行をセットで継続する

### API feature test の access token helper 残件は policy store も 1 file で閉じる

- 背景: `PolicyDeleteControllerTest` の整理後も、policy 系では `PolicyStoreControllerTest` と `PolicyUpdateControllerTest` に同型の Bearer header 構築が残っていた。ここでも series をまとめて触らず 1 file ごとの確認を維持する方が安全だった
- 決定事項: 今回は `tests/Feature/Api/PolicyStoreControllerTest.php` だけを `withAccessToken()` へ置換した。残る policy 系 1 file は未変更のまま残し、README 更新と対象 feature test 実行を 1 file ごとに継続する
- 影響範囲: `tests/Feature/Api/PolicyStoreControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、policy 系の残りである `PolicyUpdateControllerTest` だけを選び、同じく `withAccessToken()` への置換と対象 feature test 実行をセットで継続する

### API feature test の access token helper 残件は policy update を最後に解消する

- 背景: policy 系の 1 file ずつの横展開を続けた結果、最後に残っていた Bearer header 直書きは `PolicyUpdateControllerTest` だけになっていた。ここを閉じれば `tests/Feature/Api` の access token 直書きは一通り `withAccessToken()` へ寄せられ、次の小さな共通化候補へ移りやすくなる
- 決定事項: 今回は `tests/Feature/Api/PolicyUpdateControllerTest.php` の access token 付与 2 箇所だけを `withAccessToken()` へ置換した。policy 系の helper 横展開はこの file をもって完了とし、次は `tests/Feature/Api` 全体で別の「挙動を変えない小さな共通化候補」を README を起点に再探索する
- 影響範囲: `tests/Feature/Api/PolicyUpdateControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、`tests/Feature/Api` と基底 test case を再点検し、`withAccessToken()` 以外で still small かつ挙動非変更の共通化候補があるかを確認する。候補が無ければ、その時点で「今回の helper 横展開テーマは完了」と README に明示する

### API feature test の次の小さな共通化候補は custom JWT の Bearer header 直書き

- 背景: `withAccessToken()` の横展開完了後に `tests/Feature/Api` と基底 test case を再点検すると、access token 自動生成の直書きは消えた一方で、custom JWT を自前生成する `MeControllerTest` と `MeAuthorizationControllerTest` には `withHeader('Authorization', 'Bearer '.$token)` が残っていた。これは既存の `withAccessToken()` と同じ責務境界で薄く吸収できる
- 決定事項: `tests/Concerns/InteractsWithKeycloakTokens.php` に `withBearerToken()` を追加し、`withAccessToken()` もその helper を経由する形へそろえた。最初の適用先は `tests/Feature/Api/MeControllerTest.php` のみに限定し、custom JWT を使う request header 構築だけを `withBearerToken($token)` へ置換した
- 影響範囲: `tests/Concerns/InteractsWithKeycloakTokens.php`、`tests/Feature/Api/MeControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、残る custom JWT 系の `tests/Feature/Api/MeAuthorizationControllerTest.php` だけを `withBearerToken()` へ置換し、同じく README 更新と対象 feature test 実行をセットで継続する

### API feature test の custom JWT Bearer helper は MeAuthorization で横展開を閉じる

- 背景: `MeControllerTest` へ `withBearerToken()` を適用したあとも、custom JWT を自前生成する直書き Bearer header は `MeAuthorizationControllerTest` に残っていた。対象が 1 file に絞れたため、この段階で同じ helper へ寄せ切るのが最も小さな差分だった
- 決定事項: 今回は `tests/Feature/Api/MeAuthorizationControllerTest.php` の custom JWT Bearer 付与 2 箇所だけを `withBearerToken()` へ置換した。これにより、`tests/Feature/Api` で確認できた Bearer header 直書きは `withAccessToken()` 系・custom JWT 系の両方で解消した
- 影響範囲: `tests/Feature/Api/MeAuthorizationControllerTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進める場合は、`tests/Feature/Api` と基底 test case を再点検し、Bearer header 以外で still small かつ挙動非変更の共通化候補があるかを確認する。候補が見つからなければ、この helper 系の共通化テーマはいったん完了として README に明示する

### API feature test の Bearer helper 共通化テーマはいったん完了とする

- 背景: `tests/Feature/Api` と基底 test case を再点検した結果、Bearer header の直書きは `withAccessToken()` 系・custom JWT 系の両方で解消していた。一方で残る重複は、resource ごとの fixture 構築、`required_permissions` を含む 403 response の厳密 assertion、`MeAuthorizationControllerTest` の permission payload 組み立てなど、共通化すると test の読みやすさや責務境界を崩しやすいものが中心だった
- 決定事項: 現時点では、Bearer helper 以外に「挙動を変えない小さな共通化候補」は追加採用しない。`assertForbidden()` の共通 helper 化や permission payload 取得の trait 化は、抽象化の利得よりも各 test の意図が見えにくくなる懸念が大きいため見送る
- 影響範囲: `tests/Feature/Api` 配下の test helper 方針、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 側を整える場合は、共通化よりも「新しい重複が追加されていないか」の監視に切り替え、同種の request header 構築や helper 直書きが再発した時だけ今回の helper を再利用して小さく閉じる

### API feature test の Bearer header 重複は監視テストで再発検知する

- 背景: Bearer helper 共通化テーマを完了したあとも、今後の test 追加時に `tests/Feature/Api` 配下へ `withHeader('Authorization', 'Bearer '.$token)` の直書きが戻ると、同じ整形作業が再発する。レビュー時の目視だけでは見逃す余地があるため、自動で検知できる軽い監視が必要だった
- 決定事項: `tests/Feature/Api/BearerTokenHelperUsageTest.php` を追加し、`tests/Feature/Api` 配下の PHP file を走査して Bearer Authorization header の直書きを検知したら失敗するようにした。Bearer 付与は今後も `withAccessToken()` または `withBearerToken()` に統一し、helper 実装を持つ `tests/Concerns` 側は監視対象に含めない
- 影響範囲: `tests/Feature/Api` 配下の Keycloak 認証つき request test、`tests/Concerns/InteractsWithKeycloakTokens.php` の利用方針、`ap-server/backend/README.md`
- 次の推奨アクション: 今後 Bearer header を伴う API test を追加する場合は、まず既存 helper を使う。監視テストが落ちた場合は新しい helper を足す前に `withAccessToken()` と `withBearerToken()` のどちらで表現できるかを先に確認する

### API feature test の Bearer header 監視は文字列補間の揺れも拾う

- 背景: 初回の監視テストは `Bearer '.$token` のような連結パターンには効いていたが、`"Bearer $token"` や `"Bearer {$token}"` のような文字列補間まで含めると、同じ重複が別表記で再発しても取りこぼす余地があった
- 決定事項: `tests/Feature/Api/BearerTokenHelperUsageTest.php` の検知パターンを広げ、`withHeader()` と `withHeaders()` の両方で Bearer header の連結・文字列補間を監視対象に含めた。今後は「Authorization header をその場で組み立てているか」を基準に helper 利用へ寄せる
- 影響範囲: `tests/Feature/Api/BearerTokenHelperUsageTest.php` の失敗条件、`tests/Feature/Api` 配下での Bearer token 付与方法、`ap-server/backend/README.md`
- 次の推奨アクション: 今後監視テストが落ちたときは、まず直書きを `withAccessToken()` か `withBearerToken()` へ置換する。もしそのどちらでも表現できない新しい Bearer 付与パターンが出た場合だけ、helper 側の責務拡張を README 更新とセットで検討する

### Bearer header の重複監視は backend test 全体へ広げる

- 背景: `tests/Feature/Api` 向けの監視を入れたあとに backend の test 全体を再点検すると、Bearer Authorization header の直書きは helper 実装である `tests/Concerns/InteractsWithKeycloakTokens.php` に限られていた。今後は API 以外の test が増える可能性もあるため、監視を `Feature/Api` のみへ閉じる理由が薄くなっていた
- 決定事項: `tests/Feature/Api/BearerTokenHelperUsageTest.php` の走査対象を `base_path('tests')` へ広げ、`tests/Concerns` だけを除外する形にした。これにより backend test 全体で Bearer header のその場組み立てを検知しつつ、helper 実装自身の責務は監視対象から外す
- 影響範囲: `ap-server/backend/tests` 配下の Bearer token 利用方針、`tests/Feature/Api/BearerTokenHelperUsageTest.php` の監視範囲、`ap-server/backend/README.md`
- 次の推奨アクション: 今後 backend test を追加して監視テストが落ちた場合は、まず `withAccessToken()` か `withBearerToken()` への置換で解消する。もし `tests/Concerns` 以外で Bearer header の組み立てを残す必要が出た場合だけ、その理由と責務境界を README に記録してから例外扱いを検討する

### Bearer header の重複監視テーマはいったん定常運用へ移す

- 背景: 監視テストの検知パターン拡張と backend test 全体への適用まで完了し、`ap-backend` コンテナで backend 全体の test が通ることも確認できた。この段階でさらに先回りした抽象化や監視拡張を足すと、重複抑止より監視ロジック自体の複雑化が先に増える
- 決定事項: Bearer header の重複監視テーマは、現時点では追加の proactive 実装を行わず定常運用へ移す。監視テスト名も backend 全体を見ている実態に合わせ、意図が読み取りやすい名前へそろえる
- 影響範囲: `tests/Feature/Api/BearerTokenHelperUsageTest.php` の命名、backend test の Bearer header 運用方針、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、このテーマを広げ続けるのではなく、README の別テーマとして「挙動非変更で閉じる小さな重複」や「監視テストで拾いにくい新しいノイズ」を再探索する

### Authorization API test の `assignRole()` 重複は親 test case に寄せる

- 背景: Bearer header 監視テーマの次候補を再探索すると、`CreateAuthorizationApiTestCase` と `UpsertAuthorizationApiTestCase` には `assignRole()` の同型実装が残っていた。差分は AP user の準備方法だけで、scope 生成と role assignment 付与の流れは完全に一致していたため、ここは責務を崩さずに薄く共通化できた
- 決定事項: `assignRole()` 本体は `AuthorizationApiTestCase` へ移し、子クラス側は `prepareAuthorizationUser()` だけを実装する形に整理した。`create` と `updateOrCreate` の使い分けは従来どおり各子クラスに残し、role 付与の主処理だけを親へ寄せた
- 影響範囲: `tests/Feature/Api/AuthorizationApiTestCase.php`、`tests/Feature/Api/CreateAuthorizationApiTestCase.php`、`tests/Feature/Api/UpsertAuthorizationApiTestCase.php`、これらを継承する authorization 系 API test
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく「子クラス間で流れは同じだが差し替え点が 1 箇所だけ」の補助処理が他にないかを確認する。候補が無ければ、以降は import や assertion message などよりノイズ寄りの整形候補へ切り替える

### 子クラス差し替え型の test helper 共通化候補はいったん出尽くした

- 背景: `assignRole()` 整理後に `tests/Feature/Api` の補助メソッドと index 系 trait / base test case を再点検したが、追加で見つかった独自 helper は `ApiRoutePermissionPolicyTest` 内の route assertion や `MeAuthorizationControllerTest` の permission payload 取得など、個別 test の文脈に強く結びつくものが中心だった。`ScopedIndexValidationApiTestCase` と `InteractsWithScopedIndexValidation` はすでに責務境界が薄く、今回と同型の差し替えポイントも残っていなかった
- 決定事項: 現時点では「子クラス間で流れは同じだが差し替え点が 1 箇所だけ」の補助処理について、追加の共通化は採用しない。このテーマはここでいったん完了とし、今後は import や assertion message など、よりノイズ寄りで安全に閉じる整形候補の探索へ切り替える
- 影響範囲: `tests/Feature/Api` 配下の test helper 方針、共通化探索の打ち止め判断、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、未使用 import、命名のずれ、assertion message の重複など「挙動に影響しないノイズ」を 1 file または 1 パターンずつ確認し、小さく閉じる

### Route permission policy test の route 解決重複は 1 helper に寄せる

- 背景: ノイズ寄りの整形候補を再探索すると、`ApiRoutePermissionPolicyTest` では route 取得と `Route [%s] was not found.` の assertion が 2 helper に重複していた。対象は 1 file に閉じており、route policy 自体の期待値や assertion message の意味は変えずに読み筋だけ整えられた
- 決定事項: `tests/Feature/Api/ApiRoutePermissionPolicyTest.php` に `resolveRoute()` を追加し、route lookup と存在確認を 1 箇所へ寄せた。permission 有無の assertion helper 自体は分けたまま残し、各 test の意図が読める粒度は維持する
- 影響範囲: `tests/Feature/Api/ApiRoutePermissionPolicyTest.php`、route permission policy test の補助メソッド構成、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Required permissions middleware test の response assertion 重複を 1 file で整理する

- 背景: 次のノイズ候補を再探索すると、`RequiredPermissionsMiddlewareTest` では `['status' => 'ok']` の成功 response と `message + required_permissions` の forbidden response が file 内で 2 回ずつ重複していた。middleware 挙動そのものではなく assertion の書き方だけが重なっていたため、1 file に閉じた整形として安全に扱えた
- 決定事項: `tests/Feature/Api/RequiredPermissionsMiddlewareTest.php` に `assertOkStatusResponse()` と `assertForbiddenResponse()` を追加し、同型 assertion を file 内 helper へ寄せた。route 定義や permission payload の期待値は変更せず、test 本体では「誰が通るか / 弾かれるか」が先に読める形を優先した
- 影響範囲: `tests/Feature/Api/RequiredPermissionsMiddlewareTest.php`、required permissions middleware test の assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Object show controller test の固定 error response assertion を file 内 helper に寄せる

- 背景: 次の 1 file ノイズ候補として `ObjectShowControllerTest` を見ると、`Not Found` と `Forbidden` の固定 error response assertion が file 内で繰り返されていた。対象は 3 test のうち 2 test に限られ、object 表示の成功系 assertion とは責務が分かれていたため、挙動を変えずに読み筋だけ整えやすかった
- 決定事項: `tests/Feature/Api/ObjectShowControllerTest.php` に `assertNotFoundResponse()` と `assertForbiddenResponse()` を追加し、固定 payload の assertion を file 内 helper へ寄せた。成功系の `data` assertion は個別性が高いためそのまま残し、error response だけを整理対象にした
- 影響範囲: `tests/Feature/Api/ObjectShowControllerTest.php`、object show test の error response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Object delete controller test の固定 error response assertion も file 内 helper に寄せる

- 背景: `ObjectShowControllerTest` に続いて近い構造の `ObjectDeleteControllerTest` を確認すると、こちらも `Not Found` と `Forbidden` の固定 error response assertion が file 内で繰り返されていた。delete 成功時は `assertNoContent()` と DB 確認で責務が分かれており、error response だけを整理対象にしても test の読み味を崩さなかった
- 決定事項: `tests/Feature/Api/ObjectDeleteControllerTest.php` に `assertNotFoundResponse()` と `assertForbiddenResponse()` を追加し、固定 payload の assertion を file 内 helper へ寄せた。delete 成功系は個別の永続化確認が主目的なので、そのまま残した
- 影響範囲: `tests/Feature/Api/ObjectDeleteControllerTest.php`、object delete test の error response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Object update controller test の固定 error response assertion も file 内 helper に寄せる

- 背景: object 系の近接 file を続けて確認すると、`ObjectUpdateControllerTest` にも `Not Found` と `Forbidden` の固定 error response assertion が複数残っていた。更新成功系や validation error は個別差分が大きい一方、error response は payload 形状が揃っており、ここだけを切り出すと 1 file に閉じて安全に整えられた
- 決定事項: `tests/Feature/Api/ObjectUpdateControllerTest.php` に `assertNotFoundResponse()` と `assertForbiddenResponse()` を追加し、固定 payload の assertion を file 内 helper へ寄せた。成功系 response と validation error assertion は個別性が高いためそのまま残した
- 影響範囲: `tests/Feature/Api/ObjectUpdateControllerTest.php`、object update test の error response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Object store controller test の forbidden response assertion を file 内 helper に寄せる

- 背景: object 系の store test を確認すると、`ObjectStoreControllerTest` には固定の forbidden response payload が 1 箇所だけ残っていた。重複回数は多くないものの、近接 file と同じ error response の記述方針へそろえると、object 系 CRUD test の読み方が揃いやすかった
- 決定事項: `tests/Feature/Api/ObjectStoreControllerTest.php` に `assertForbiddenResponse()` を追加し、固定 payload の forbidden assertion を file 内 helper へ寄せた。validation error と成功 response は個別の期待値が主題なので、そのまま残した
- 影響範囲: `tests/Feature/Api/ObjectStoreControllerTest.php`、object store test の forbidden response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Playbook show controller test の forbidden response 記述を近接 file とそろえる

- 背景: 次の 1 file ノイズ候補として `PlaybookShowControllerTest` を見ると、forbidden response は 1 箇所だけだったが、`ObjectShowControllerTest` など近い show 系 test では file 内 helper に寄せる書き方へそろい始めていた。回数は少なくても、近接シリーズ内で error response の書き味をそろえると読み替えコストを下げやすかった
- 決定事項: `tests/Feature/Api/PlaybookShowControllerTest.php` に `assertForbiddenResponse()` を追加し、固定 payload の forbidden assertion を file 内 helper へ寄せた。成功系の `data` assertion は個別性が高いためそのまま残した
- 影響範囲: `tests/Feature/Api/PlaybookShowControllerTest.php`、playbook show test の forbidden response 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Playbook index controller test の forbidden response 記述も近接 file とそろえる

- 背景: `PlaybookShowControllerTest` に続いて `PlaybookIndexControllerTest` を確認すると、こちらも permission 不足時の固定 forbidden response を直接書いていた。index の成功系 assertion は pagination/filter の差分が大きい一方、forbidden response は固定形なので、file 内 helper 化しても読みやすさを崩さなかった
- 決定事項: `tests/Feature/Api/PlaybookIndexControllerTest.php` に `assertForbiddenResponse()` を追加し、固定 payload の forbidden assertion を file 内 helper へ寄せた。index 成功系と invalid filter の assertion は個別性が高いためそのまま残した
- 影響範囲: `tests/Feature/Api/PlaybookIndexControllerTest.php`、playbook index test の forbidden response 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Playbook store controller test の重複 code validation assertion を file 内 helper に寄せる

- 背景: playbook 系の次候補として `PlaybookStoreControllerTest` を確認すると、重複 code 時の validation payload は 1 箇所だけだったが、store 系 test では成功系と失敗系の読み分けが主になるため、固定の failure assertion を helper へ寄せると本文の見通しを少し保ちやすかった
- 決定事項: `tests/Feature/Api/PlaybookStoreControllerTest.php` に `assertDuplicateCodeValidationResponse()` を追加し、重複 code 時の固定 validation assertion を file 内 helper へ寄せた。作成成功系の response は個別性が高いためそのまま残した
- 影響範囲: `tests/Feature/Api/PlaybookStoreControllerTest.php`、playbook store test の duplicate validation assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Policy store controller test の重複 code validation assertion も file 内 helper に寄せる

- 背景: `PolicyStoreControllerTest` を確認すると、`PlaybookStoreControllerTest` と同じく重複 code 時の固定 validation payload を直接書いていた。store 系 test では失敗ケースの assertion 形状が揃っているため、近い file 同士で helper 化の粒度を合わせると読み方をそろえやすかった
- 決定事項: `tests/Feature/Api/PolicyStoreControllerTest.php` に `assertDuplicateCodeValidationResponse()` を追加し、重複 code 時の固定 validation assertion を file 内 helper へ寄せた。作成成功系の response と DB assertion は個別性が高いためそのまま残した
- 影響範囲: `tests/Feature/Api/PolicyStoreControllerTest.php`、policy store test の duplicate validation assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Policy update controller test の scope immutability validation assertion を file 内 helper に寄せる

- 背景: `PolicyUpdateControllerTest` を確認すると、scope 変更禁止時の validation payload を直接書いていた。policy 系の store/update が並んでいる範囲では、固定の失敗 assertion を helper 化した方が本文で「何を検証しているか」が見やすく、近接 file 間の読み方もそろえやすかった
- 決定事項: `tests/Feature/Api/PolicyUpdateControllerTest.php` に `assertScopeImmutableValidationResponse()` を追加し、scope 変更禁止時の固定 validation assertion を file 内 helper へ寄せた。更新成功系の response は個別性が高いためそのまま残した
- 影響範囲: `tests/Feature/Api/PolicyUpdateControllerTest.php`、policy update test の scope immutability validation assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Checklist store controller test の重複 code validation assertion を file 内 helper に寄せる

- 背景: `ChecklistStoreControllerTest` を確認すると、`PlaybookStoreControllerTest` や `PolicyStoreControllerTest` と同型の重複 code validation payload をまだ直接書いていた。store 系 test の failure assertion 形状を近接 file でそろえる方が、成功ケースとの差分を追いやすかった
- 決定事項: `tests/Feature/Api/ChecklistStoreControllerTest.php` に `assertDuplicateCodeValidationResponse()` を追加し、重複 code 時の固定 validation assertion を file 内 helper へ寄せた。作成成功系の response と DB assertion は個別性が高いためそのまま残した
- 影響範囲: `tests/Feature/Api/ChecklistStoreControllerTest.php`、checklist store test の duplicate validation assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Object store controller test の重複 code validation assertion も file 内 helper に寄せる

- 背景: `ObjectStoreControllerTest` を確認すると、playbook / policy / checklist の store test と同型の重複 code validation payload をまだ直接書いていた。近接する store 系 test で failure assertion の置き方をそろえると、成功・forbidden・validation の切り替えが追いやすかった
- 決定事項: `tests/Feature/Api/ObjectStoreControllerTest.php` に `assertDuplicateCodeValidationResponse()` を追加し、重複 code 時の固定 validation assertion を file 内 helper へ寄せた。既存の `assertForbiddenResponse()` と成功系 assertion は役割が分かれているためそのまま残した
- 影響範囲: `tests/Feature/Api/ObjectStoreControllerTest.php`、object store test の duplicate validation assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Object update controller test の重複 code validation assertion も file 内 helper に寄せる

- 背景: `ObjectUpdateControllerTest` には not found / forbidden helper はすでにあったが、target scope 内での重複 code validation payload だけは本文に残っていた。object の store/update を並べて読むと failure assertion の置き方が少しだけ揃っていなかった
- 決定事項: `tests/Feature/Api/ObjectUpdateControllerTest.php` に `assertDuplicateCodeValidationResponse()` を追加し、重複 code 時の固定 validation assertion を file 内 helper へ寄せた。no fields payload は object update 固有の文脈が強いため本文のまま残した
- 影響範囲: `tests/Feature/Api/ObjectUpdateControllerTest.php`、object update test の duplicate validation assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Playbook update controller test の success response assertion を file 内 helper に寄せる

- 背景: 固定 failure assertion の整理が一巡したあと `PlaybookUpdateControllerTest` を見直すと、更新成功時と scope 移動成功時で response の shape 自体は同じなのに、本文に `assertExactJson()` が 2 回並んでいた。差し替わるのは `scope_id` / `code` / `name` だけなので、本文では操作の違いだけを追える方が読みやすかった
- 決定事項: `tests/Feature/Api/PlaybookUpdateControllerTest.php` に `assertPlaybookResponse()` を追加し、success response assertion を file 内 helper に寄せた。store 系や index 系のようにレスポンス差分が大きい file には広げず、同じ shape が 1 file 内で繰り返されるケースだけに留める
- 影響範囲: `tests/Feature/Api/PlaybookUpdateControllerTest.php`、playbook update test の success response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### Checklist update controller test の success response assertion も file 内 helper に寄せる

- 背景: `ChecklistUpdateControllerTest` も `PlaybookUpdateControllerTest` と同じく、更新成功時と scope 移動成功時で response の shape は同じなのに `assertExactJson()` が 2 回並んでいた。差し替え点だけを本文で読めるようにした方が、近接 file 間で見通しをそろえやすかった
- 決定事項: `tests/Feature/Api/ChecklistUpdateControllerTest.php` に `assertChecklistResponse()` を追加し、success response assertion を file 内 helper に寄せた。今回は直近で同じ整理をした playbook update と粒度をそろえる範囲に留め、他 file への一括展開は行わない
- 影響範囲: `tests/Feature/Api/ChecklistUpdateControllerTest.php`、checklist update test の success response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、今回と同じく 1 file に閉じるノイズ候補を選び、未使用 import、命名のずれ、assertion message の重複を優先して小さく整える

### success response helper の近接展開をいったんここで止める

- 背景: `PlaybookUpdateControllerTest` と `ChecklistUpdateControllerTest` では同じ shape の success response が 1 file 内で 2 回出ていたため helper 化の効果があった。一方で、その後 `PolicyUpdateControllerTest`、store/show/delete 周辺、`ScopedIndexValidationApiTestCase` と `InteractsWithScopedIndexValidation` まで見直すと、残りは 1 回しか出ない assertion か、shared helper 側ですでに十分短いものが大半だった
- 決定事項: success response helper の横展開は、同じ shape が 1 file 内で明確に重複している update 系までで止める。単発の success assertion や shared trait の短い validation assertion には追加 helper を入れず、次は未使用 import、命名のずれ、message 文言の重複といった別種のノイズ探索へ戻す
- 影響範囲: `tests/Feature/Api` の test 整理方針、update/store/show/delete/index まわりの helper 採用判断、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、unused import や命名のずれを優先して再走査し、helper 追加ではなく削除・命名統一で閉じる 1 file 候補を選ぶ

### Me authorization controller test の current_user payload を file 内 helper に寄せる

- 背景: unused import の再走査では明確な削除候補が見つからなかったため、次に `MeAuthorizationControllerTest` を見直した。`current_user` の payload は 2 箇所で同じ shape を保っており、authorization 本体の配列が長い file ではこの小さな共通化だけでも本文の見通しを少し保ちやすかった
- 決定事項: `tests/Feature/Api/MeAuthorizationControllerTest.php` に `currentUserPayload()` を追加し、`current_user` の固定 shape を file 内 helper に寄せた。assignment / permission payload はテスト意図の中心で個別性が高いため、そのまま残した
- 影響範囲: `tests/Feature/Api/MeAuthorizationControllerTest.php`、me authorization test の current_user assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、unused import の再走査を続けつつ、今回と同じく長い assertion の中で shape だけが重複している 1 file 候補があれば小さく整える

### Me controller test の current_user assertion を file 内 helper に寄せる

- 背景: `MeControllerTest` を見直すと、`current_user` の object payload が複数箇所で同じ shape を保っており、`current_user => null` の response も複数回繰り返していた。認証経路ごとの違いを追う test なので、payload の shape より「どの入力で user が返るか / 返らないか」を本文で見やすくしたかった
- 決定事項: `tests/Feature/Api/MeControllerTest.php` に `currentUserPayload()` と `assertNullCurrentUserResponse()` を追加し、`current_user` の同型 assertion を file 内 helper に寄せた。JWKS discovery や token 条件の前提は各 test に固有なので、その周辺ロジックはそのまま残した
- 影響範囲: `tests/Feature/Api/MeControllerTest.php`、me controller test の current_user assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、unused import の再走査を続けつつ、`Me*` 系以外でも長い response assertion の中で shape だけが重複している 1 file 候補があれば小さく整える

### Object index controller test の meta payload を file 内 helper に寄せる

- 背景: `ObjectIndexControllerTest` は filter / pagination 系の確認が多く、`meta` の shape が 1 file 内で何度も繰り返されていた。response 全体を helper 化すると data 部分まで隠れてしまうが、`meta` だけなら共通形を寄せても各 test の意図を保ちやすかった
- 決定事項: `tests/Feature/Api/ObjectIndexControllerTest.php` に `metaPayload()` を追加し、`current_page` / `per_page` / `total` / `last_page` / `filters` の共通 shape を file 内 helper に寄せた。data 配列は各 test の中心なのでそのまま残し、index 系の共通 helper へは広げない
- 影響範囲: `tests/Feature/Api/ObjectIndexControllerTest.php`、object index test の meta assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、index 系の他 file でも `meta` の繰り返しが 1 file 内で目立つかを見つつ、広域共通化ではなく 1 file 単位で小さく整える

### Playbook index controller test の meta payload も file 内 helper に寄せる

- 背景: `PlaybookIndexControllerTest` も index 系の近接 file と同じく、一覧成功時と filter/sort 成功時で `meta` の shape が繰り返されていた。forbidden response helper はすでに分かれているため、`meta` だけを寄せると file 内の役割分担も崩さずに済んだ
- 決定事項: `tests/Feature/Api/PlaybookIndexControllerTest.php` に `metaPayload()` を追加し、共通の `meta` assertion を file 内 helper に寄せた。`data` 配列と forbidden response helper はテスト意図の中心なのでそのまま残した
- 影響範囲: `tests/Feature/Api/PlaybookIndexControllerTest.php`、playbook index test の meta assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、index 系の残り file でも `meta` の繰り返しが helper 化に見合うかを見つつ、同じく 1 file 単位で小さく整える

### index 系の meta helper 展開を object/playbook で止める

- 背景: `ObjectIndexControllerTest` と `PlaybookIndexControllerTest` では `meta` の shape が 1 file 内で複数回繰り返されていたため helper 化の効果があった。一方で、その後 `ChecklistIndexControllerTest` と `PolicyIndexControllerTest` を見直すと、`meta` assertion は実質 1 回だけで、invalid filter 側は shared trait に集約されていた
- 決定事項: index 系の `metaPayload()` 展開は object/playbook までで止める。checklist/policy のように `meta` が 1 回しか出ない file には helper を追加せず、次は index 以外も含めて「1 file 内で shape だけが 2 回以上重複している assertion」を優先して探す
- 影響範囲: `tests/Feature/Api` の index test 整理方針、`ChecklistIndexControllerTest` / `PolicyIndexControllerTest` の helper 採用判断、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、index 系から少し離れて、show/store/update の長い response assertion や payload 配列で shape だけが複数回重なる 1 file 候補を再探索する

### Object update controller test の success response assertion を file 内 helper に寄せる

- 背景: index 系の区切り後に `ObjectUpdateControllerTest` を見直すと、更新成功時と scope 移動成功時で `data` の shape は同じなのに `assertExactJson()` が 2 回並んでいた。playbook/checklist update と同じく、本文では更新内容の差分だけを追える方が読みやすかった
- 決定事項: `tests/Feature/Api/ObjectUpdateControllerTest.php` に `assertObjectResponse()` を追加し、success response assertion を file 内 helper に寄せた。既存の forbidden / not found / duplicate validation helper と役割が分かれるようにし、DB assertion は各 test に残した
- 影響範囲: `tests/Feature/Api/ObjectUpdateControllerTest.php`、object update test の success response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、show/store/update を中心に、同じ shape の success response や payload 配列が 1 file 内で 2 回以上出る候補を引き続き小さく整える

### Playbook store controller test の request payload を file 内 helper に寄せる

- 背景: `PlaybookStoreControllerTest` を見直すと、作成成功時と duplicate validation 時で `postJson()` に渡す payload 配列の shape が同じまま 2 回並んでいた。store 系では response assertion より入力差分を読みたいので、payload の骨格だけ helper に寄せる方が本文の意図を追いやすかった
- 決定事項: `tests/Feature/Api/PlaybookStoreControllerTest.php` に `playbookPayload()` を追加し、`scope_id` / `code` / `name` を持つ request payload 配列を file 内 helper に寄せた。success response assertion は 1 回だけなので helper 化せず、そのまま残した
- 影響範囲: `tests/Feature/Api/PlaybookStoreControllerTest.php`、playbook store test の request payload 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、同じ store 系の checklist/policy や show 系を見直しつつ、1 file 内で payload 配列や success response の shape が 2 回以上揃っている候補だけを同じ粒度で小さく整える

### Checklist / Policy store controller test の request payload を file 内 helper に寄せる

- 背景: `PlaybookStoreControllerTest` と同じ観点で `ChecklistStoreControllerTest` と `PolicyStoreControllerTest` を見直すと、どちらも作成成功時と duplicate validation 時で `postJson()` に渡す payload 配列の shape が 2 回ずつ並んでいた。store 系の本文では入力値の違いだけを追える方が読みやすいため、同じ粒度で payload の骨格だけを helper に寄せた
- 決定事項: `tests/Feature/Api/ChecklistStoreControllerTest.php` に `checklistPayload()`、`tests/Feature/Api/PolicyStoreControllerTest.php` に `policyPayload()` を追加し、`scope_id` / `code` / `name` を持つ request payload 配列を各 file 内 helper に寄せた。response assertion や duplicate validation helper は役割が分かれているので、そのまま残した
- 影響範囲: `tests/Feature/Api/ChecklistStoreControllerTest.php`、`tests/Feature/Api/PolicyStoreControllerTest.php`、store test の request payload 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、show 系の `ObjectShowControllerTest` などを見直しつつ、1 file 内で `Not Found` / `Forbidden` / success response の shape が 2 回以上揃っている候補だけを小さく整える

### Object show controller test の tenant scope 作成 payload を file 内 helper に寄せる

- 背景: show 系を見直すと success response 自体は各 file で 1 回ずつしか出ていなかったが、`ObjectShowControllerTest` では `Scope::query()->create([...])` に渡す tenant scope 用の配列が 1 file 内で複数回繰り返されていた。show test では権限制御と取得結果を読みたいので、scope 作成 payload の骨格だけ helper に寄せると本文の視線移動を減らせた
- 決定事項: `tests/Feature/Api/ObjectShowControllerTest.php` に `createTenantScope()` を追加し、tenant scope 作成時の `layer` / `code` / `name` / `parent_scope_id` 配列を file 内 helper に寄せた。forbidden / not found helper と success response assertion は役割が分かれているため、そのまま残した
- 影響範囲: `tests/Feature/Api/ObjectShowControllerTest.php`、object show test の scope 作成記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、show/store/update をもう一巡して、1 file 内で shape の重複がまだ 2 回以上ある payload や response だけを拾い、helper 化の効果が薄い file には手を広げない

### Playbook / Policy update controller test の tenant scope 作成 payload を file 内 helper に寄せる

- 背景: show 系の次に update 系を再走査すると、`PlaybookUpdateControllerTest` と `PolicyUpdateControllerTest` の move 関連 test で `Scope::query()->create([...])` に渡す tenant scope 用の配列がそれぞれ 2 回並んでいた。update test では移動可否や response の差分を読みたいので、scope 作成 payload の骨格だけ helper に寄せる方が本文を追いやすかった
- 決定事項: `tests/Feature/Api/PlaybookUpdateControllerTest.php` と `tests/Feature/Api/PolicyUpdateControllerTest.php` に `createTenantScope()` を追加し、tenant scope 作成時の `layer` / `code` / `name` 配列を各 file 内 helper に寄せた。既存の success response helper や validation helper は役割が分かれているため、そのまま残した
- 影響範囲: `tests/Feature/Api/PlaybookUpdateControllerTest.php`、`tests/Feature/Api/PolicyUpdateControllerTest.php`、update test の scope 作成記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、`ObjectStoreControllerTest` や `ObjectUpdateControllerTest` のように複数種の helper が混在する file を見直しつつ、同じ shape が 2 回以上あっても helper の責務が増えすぎる場合は無理に寄せず、その判断も README に残す

### Object 系 test では store の scope helper だけ追加し、update は現状維持にする

- 背景: `ObjectStoreControllerTest` と `ObjectUpdateControllerTest` を見直すと、どちらも tenant scope 作成配列の重複は残っていた。一方で `ObjectUpdateControllerTest` は success / forbidden / not found / duplicate validation helper がすでに共存しており、ここに scope helper まで足すと file 内 helper の責務が増えすぎて、かえって読み筋が分かれやすかった
- 決定事項: `tests/Feature/Api/ObjectStoreControllerTest.php` には `createTenantScope()` を追加して scope 作成配列だけを小さく寄せる。`tests/Feature/Api/ObjectUpdateControllerTest.php` は今回あえて触らず、既存 helper 群の役割分担を優先する
- 影響範囲: `tests/Feature/Api/ObjectStoreControllerTest.php`、object store test の scope 作成記述、`ObjectUpdateControllerTest` の helper 採用判断、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、同じく helper が多い file では「重複削減」より「役割の明瞭さ」を優先し、変更しない判断も含めて 1 file 単位で README に残す

### 残りの show 系は helper を増やさず現状維持にする

- 背景: 上の基準に沿って `PlaybookShowControllerTest`、`PolicyShowControllerTest`、`ChecklistShowControllerTest` を見直したところ、`PolicyShowControllerTest` と `ChecklistShowControllerTest` は success response が 1 回だけで、helper 化できる同型 payload が実質なかった。`PlaybookShowControllerTest` には tenant scope 作成配列の近い形が 2 箇所あるものの、file 自体が短く、forbidden helper 以外を増やすほどの重複量ではなかった
- 決定事項: show 系の残候補には今回は helper を追加しない。とくに `PlaybookShowControllerTest` は、scope 作成 payload の小さな重複よりも file の短さと既存の読みやすさを優先して現状維持にする
- 影響範囲: `tests/Feature/Api/PlaybookShowControllerTest.php`、`tests/Feature/Api/PolicyShowControllerTest.php`、`tests/Feature/Api/ChecklistShowControllerTest.php` の helper 採用判断、`ap-server/backend/README.md`
- 次の推奨アクション: 次に test 整理を進める場合は、新しい重複候補を足す前に、まず今回までに現状維持とした file 群の判断を前提にし、helper を増やすなら本文の読みやすさが明確に上がる file に限定する

### show/store/update の helper 整理はいったん打ち止めにする

- 背景: 直近の基準に沿って show/store/update を再走査し、`MeControllerTest` のように helper 化の効果が大きい長めの file と比べてみると、残り候補は「shape の重複はあるが file が短い」か「既存 helper が多く責務が増えすぎる」のどちらかに寄っていた。これ以上の追加は、重複削減より helper 数の増加が先に目立ちやすかった
- 決定事項: 現時点の show/store/update 整理はここでいったん止める。今後は新しい重複候補を機械的に追わず、本文の読みやすさが明確に改善する長い file が見つかったときだけ再開する
- 影響範囲: `tests/Feature/Api` の helper 追加判断、show/store/update 整理シリーズの継続条件、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、このシリーズを続けるより、別の明確な読みづらさや保守コストの高い file を起点に新しい小タスクを切る

### Object index controller test の object payload assertion を file 内 helper に寄せる

- 背景: show/store/update の整理を打ち止めにした後、別の読みづらさが強い file を見直すと、`ObjectIndexControllerTest` は 300 行超の中で `data` 配列内の object shape が何度も繰り返されていた。index 系では filter / pagination 条件の差分を本文で追いたいので、個々の object payload を helper に寄せる効果がはっきりあった
- 決定事項: `tests/Feature/Api/ObjectIndexControllerTest.php` に `objectPayload()` を追加し、response assertion 内の `id` / `scope_id` / `code` / `name` を持つ object shape を file 内 helper に寄せた。fixture 作成や `metaPayload()`、filter 条件はテスト意図の中心なのでそのまま残した
- 影響範囲: `tests/Feature/Api/ObjectIndexControllerTest.php`、object index test の response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、同じく長めの index / authorization 系 file を見ながら、response payload の固定 shape が本文の見通しを下げている箇所だけを小さく寄せる

### Playbook index controller test の playbook payload assertion も file 内 helper に寄せる

- 背景: `ObjectIndexControllerTest` の次に近い粒度の file を見ると、`PlaybookIndexControllerTest` でも `data` 配列内の playbook shape が複数回繰り返されていた。index 系では fixture や filter 条件より response item の固定 shape の方がノイズになりやすいため、同じパターンで寄せると読み筋を揃えやすかった
- 決定事項: `tests/Feature/Api/PlaybookIndexControllerTest.php` に `playbookPayload()` を追加し、response assertion 内の `id` / `scope_id` / `code` / `name` を持つ playbook shape を file 内 helper に寄せた。forbidden response helper と `metaPayload()`、filter 条件は役割が分かれているため、そのまま残した
- 影響範囲: `tests/Feature/Api/PlaybookIndexControllerTest.php`、playbook index test の response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、authorization 系の長めの assertion を見直しつつ、固定 shape の繰り返しが本文の見通しを下げている箇所だけを小さく寄せる

### Me authorization controller test の scope / role assertion を file 内 helper に寄せる

- 背景: authorization 系の長めの assertion を見直すと、`MeAuthorizationControllerTest` では assignment ごとの差分よりも `scope` と `role` の固定 shape が目に入りやすく、1 つの長い response assertion の見通しを下げていた。`currentUserPayload()` と `permissionPayload()` はすでに分かれていたので、その隣で `scope` / `role` も同じ粒度に揃えるのが自然だった
- 決定事項: `tests/Feature/Api/MeAuthorizationControllerTest.php` に `scopePayload()` と `rolePayload()` を追加し、assignment 内の固定 shape を file 内 helper に寄せた。authorization 全体や assignment 全体は test 意図の中心なので helper 化せず、そのまま残した
- 影響範囲: `tests/Feature/Api/MeAuthorizationControllerTest.php`、me authorization test の response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、authorization / middleware 系 file を見ながら、response 全体ではなく部分的な固定 shape だけが本文の見通しを下げている箇所を同じ粒度で小さく寄せる

### Me authorization controller test の permission payload 配列だけを追加で helper に寄せる

- 背景: 上の基準を引き継いで authorization / middleware 系 file を見直すと、`MeAuthorizationControllerTest` は `scope` / `role` を寄せた後も assignment 内の `permissions` 配列だけが複数行の固定 shape として残っていた。一方で `RequiredPermissionsMiddlewareTest` は 80 行台と短く、既存 helper のままで十分追いやすかったため、middleware 側は今回は触らない方が粒度を揃えやすかった
- 決定事項: `tests/Feature/Api/MeAuthorizationControllerTest.php` に `permissionsPayload()` を追加し、assignment 内で繰り返す permission object 配列だけを file 内 helper に寄せた。authorization 全体の shape や top-level の `permissions` slug 一覧、`RequiredPermissionsMiddlewareTest` の response helper は現状維持にした
- 影響範囲: `tests/Feature/Api/MeAuthorizationControllerTest.php`、`tests/Feature/Api/RequiredPermissionsMiddlewareTest.php` の helper 採用判断、authorization / middleware 系 response assertion の整理方針、`ap-server/backend/README.md`
- 次の推奨アクション: 次に authorization / middleware 系を整える場合も、短い file に新しい helper を足す前に、長めの file で固定 object 配列だけが本文を重くしている箇所があるかを先に見る

### Me controller / middleware / shared auth helper は現状維持にする

- 背景: 上の推奨アクションに沿って `MeControllerTest`、`RequiredPermissionsMiddlewareTest`、`tests/Concerns/InteractsWithKeycloakTokens.php` を見直した。`MeControllerTest` は `currentUserPayload()` と `assertNullCurrentUserResponse()` ですでに response shape のノイズが十分抑えられており、残っている繰り返しは JWT claims や JWKS fixture で、今回の「response 全体ではなく部分的な固定 shape」を寄せる基準から外れていた。`RequiredPermissionsMiddlewareTest` も file 自体が短く、shared concern 側の `buildJwk()` / `fakeJwks()` も response assertion ではなく test setup の責務として分かれていた
- 決定事項: 今回は `MeControllerTest`、`RequiredPermissionsMiddlewareTest`、`tests/Concerns/InteractsWithKeycloakTokens.php` には helper を追加しない。authorization / middleware 系のこの小タスクはいったんここで止め、今後は新しい長めの file で response assertion 内の固定 object 配列が明確にノイズになっている場合だけ再開する
- 影響範囲: `tests/Feature/Api/MeControllerTest.php`、`tests/Feature/Api/RequiredPermissionsMiddlewareTest.php`、`tests/Concerns/InteractsWithKeycloakTokens.php` の helper 採用判断、authorization / middleware 系 test 整理の継続条件、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、authorization / middleware 系からはいったん離れ、別の長めの test file で response assertion の固定 shape が本文の見通しを下げている箇所を探す

### store 系 3 file は成功 response helper で横並びを揃える

- 背景: authorization / middleware 系から離れて長めの file を見直したが、`ObjectUpdateControllerTest` や `MeControllerTest` はすでに必要な helper が揃っており、今回の基準で追加する余地が小さかった。一方で `PlaybookStoreControllerTest`、`PolicyStoreControllerTest`、`ChecklistStoreControllerTest` には、成功時の `data` object shape が各 file に 1 回ずつ直接残っていて、3 resource を横断すると同じ読み筋だけが素直に揃っていなかった
- 決定事項: `tests/Feature/Api/PlaybookStoreControllerTest.php`、`tests/Feature/Api/PolicyStoreControllerTest.php`、`tests/Feature/Api/ChecklistStoreControllerTest.php` にそれぞれ `assertPlaybookResponse()` / `assertPolicyResponse()` / `assertChecklistResponse()` を追加し、成功 response の `id` / `scope_id` / `code` / `name` shape だけを helper に寄せた。duplicate validation helper と request payload helper は既存の役割のまま維持した
- 影響範囲: 上記 3 file の success response assertion 記述、store 系 resource test の helper 粒度、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、resource ごとの store / update / show を横断して、同じ response item shape が 2 回以上出る小さな系列を優先して揃える

### show 系 3 file も成功 response helper で横並びを揃える

- 背景: 上の方針に沿って `show` 系を横断で見直すと、`PlaybookShowControllerTest`、`PolicyShowControllerTest`、`ChecklistShowControllerTest` は file 単体では短くても、3 resource で同じ `data` object shape が繰り返されていた。以前は単独 file 判断で show 系 helper を増やさなかったが、今回は「小さな系列を横断して揃える」段階に入ったため、ここは基準に合う重複になった
- 決定事項: 上記 3 file にそれぞれ `assertPlaybookResponse()` / `assertPolicyResponse()` / `assertChecklistResponse()` を追加し、成功 response の `id` / `scope_id` / `code` / `name` shape だけを helper に寄せた。`PlaybookShowControllerTest` の `assertForbiddenResponse()` は既存のまま残し、show 系でも response helper の粒度を store 系と揃えた
- 影響範囲: `tests/Feature/Api/PlaybookShowControllerTest.php`、`tests/Feature/Api/PolicyShowControllerTest.php`、`tests/Feature/Api/ChecklistShowControllerTest.php`、show 系 resource test の helper 粒度、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、update 系でも resource を横断して同じ success response shape が残っている系列を優先して揃える

### update 系は Policy を揃えて横並びを完了する

- 背景: `update` 系を resource 横断で見ると、`ObjectUpdateControllerTest`、`PlaybookUpdateControllerTest`、`ChecklistUpdateControllerTest` はすでに success response helper を持っていた一方、`PolicyUpdateControllerTest` だけが success 時の `data` object shape を本文に直接書いたままだった。系列としては最後の 1 file だけ粒度がずれていたため、ここを合わせると update 系の読み筋が揃った
- 決定事項: `tests/Feature/Api/PolicyUpdateControllerTest.php` に `assertPolicyResponse()` を追加し、success response の `id` / `scope_id` / `code` / `name` shape を helper に寄せた。`assertScopeImmutableValidationResponse()` と `createTenantScope()` は既存の責務のまま維持し、update 系 helper の粒度を他 resource と合わせた
- 影響範囲: `tests/Feature/Api/PolicyUpdateControllerTest.php`、update 系 resource test の success response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、resource 横断の系列整理はいったん打ち止めにして、validation や forbidden response の重複が長めの file で本文を重くしている箇所があるかを探す

### Object update controller test の validation failed shape を file 内 helper に寄せる

- 背景: resource 横断の系列整理を終えた後に長めの file を見直すと、`ObjectUpdateControllerTest` は success / forbidden / not found helper がある一方で、`Validation failed` の外側 shape だけが「未入力 payload」と「重複 code」の 2 箇所で繰り返されていた。差分として読みたいのは各 `errors` の中身なので、外側だけを helper に寄せると本文の見通しを少し揃えやすかった
- 決定事項: `tests/Feature/Api/ObjectUpdateControllerTest.php` に `assertValidationFailedResponse()` を追加し、`message` と `errors` を持つ validation response の外側 shape を helper に寄せた。`assertDuplicateCodeValidationResponse()` はその wrapper として残し、`assertJsonValidationErrors()` を使うケースや forbidden / not found helper は現状維持にした
- 影響範囲: `tests/Feature/Api/ObjectUpdateControllerTest.php`、object update test の validation response assertion 記述、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、同じく長めの object 系 file を見ながら、forbidden や validation の外側 shape が 2 回以上出ている箇所だけを file 内 helper に寄せる

### Object store controller test も validation failed shape だけを揃える

- 背景: object 系の残りを見直すと、`ObjectShowControllerTest` と `ObjectDeleteControllerTest` は `forbidden` / `not found` をすでに file 内 helper に寄せており、今回の基準で追加の余地は小さかった。一方で `ObjectStoreControllerTest` は `Validation failed` の外側 shape が `ObjectUpdateControllerTest` と同じ形で残っていたため、object store / update の validation helper 粒度だけがまだずれていた
- 決定事項: `tests/Feature/Api/ObjectStoreControllerTest.php` に `assertValidationFailedResponse()` を追加し、重複 code の validation response で `message` と `errors` を持つ外側 shape を helper に寄せた。`assertJsonValidationErrors()` を使う invalid payload case、`assertForbiddenResponse()`、success response の記述は現状維持にした
- 影響範囲: `tests/Feature/Api/ObjectStoreControllerTest.php`、object store test の validation response assertion 記述、object 系 validation helper の粒度、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、object 系 helper の追加はいったん止めて、別の長めの file で validation や forbidden response の外側 shape が複数回出ている箇所があるかを探す

### validation / forbidden response 整理はここでいったん打ち止めにする

- 背景: 上の推奨アクションに沿って object 系以外も含めて長めの file と shared base を見直したが、`PlaybookIndexControllerTest` は forbidden helper が 1 箇所だけ、`ScopedIndexValidationApiTestCase` / `InteractsWithScopedIndexValidation` はすでに validation を共通化済みだった。`PolicyIndexControllerTest` と `ChecklistIndexControllerTest` に残っている重複は success payload / meta 側で、今回の「validation や forbidden response の外側 shape」を小さく寄せる基準とはずれていた
- 決定事項: validation / forbidden response を起点にした helper 追加はここでいったん止める。今後はこの軸で新しい helper を機械的に足さず、別の明確な重複系列が見つかったときにだけ再開する
- 影響範囲: `tests/Feature/Api` の validation / forbidden helper 追加判断、index 系 test の今後の探索起点、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、`PolicyIndexControllerTest` と `ChecklistIndexControllerTest` を起点に、index 系の success payload / meta assertion で残っている固定 shape を `ObjectIndexControllerTest` / `PlaybookIndexControllerTest` と同じ粒度で揃えられるかを見る

### Policy / Checklist index controller test の payload と meta を helper に寄せる

- 背景: 上の推奨アクションに沿って `PolicyIndexControllerTest` と `ChecklistIndexControllerTest` を `ObjectIndexControllerTest` / `PlaybookIndexControllerTest` と見比べると、前者 2 file だけ success response の `data` item shape と `meta` shape を本文に直接書いていた。index 系では fixture や filter 条件より response item / meta の固定 shape がノイズになりやすく、ここは既存の index 系と同じ粒度に揃える効果がはっきりしていた
- 決定事項: `tests/Feature/Api/PolicyIndexControllerTest.php` に `policyPayload()` と `metaPayload()`、`tests/Feature/Api/ChecklistIndexControllerTest.php` に `checklistPayload()` と `metaPayload()` を追加し、success response の固定 shape を file 内 helper に寄せた。invalid filter validation は shared base ですでに共通化済みなので、そのまま維持した
- 影響範囲: `tests/Feature/Api/PolicyIndexControllerTest.php`、`tests/Feature/Api/ChecklistIndexControllerTest.php`、index 系 success response assertion の helper 粒度、`ap-server/backend/README.md`
- 次の推奨アクション: 次に backend test を整える場合は、index 系の helper 粒度が揃った前提で、残る長めの file に本文を重くする固定 shape があるかを個別に見直す

### backend test 整理はいったんここで終了にする

- 背景: ここまでで resource 横断の success response shape、object 系の一部 validation shape、index 系の payload / meta helper 粒度まで揃い、本文で読みたい差分と固定 shape のバランスがかなり安定した。残る候補は file ごとの細かい最適化が中心で、これ以上は重複削減より helper 増加や判断コストの方が目立ちやすかった
- 決定事項: backend test 整理はこの段階でいったん終了にする。以後は「長い file に明確な読みづらさがある」と再確認できたときだけ個別に再開し、次の開発ステップでは別テーマを優先する
- 影響範囲: `tests/Feature/Api` の helper 整理シリーズの終了判断、次チャットでの探索優先順位、`ap-server/backend/README.md`
- 次の推奨アクション: 次は backend test 整理から離れ、backend 実装や API 契約の次タスクを切る前提で、README 上の未着手項目や新しい要求を起点に進める

### AP 側ユーザー管理の最小入口を `GET /api/users` として追加

- 背景: `user.manage` permission は初期設計に入っていたが、実際にそれを使う route と role がまだ無く、AP 側ユーザー管理の API 契約が具体化していなかった
- 決定事項: `GET /api/users` を追加し、route では `required_permissions:user.manage` を必須にする。返却対象は `AuthorizationService::accessibleScopeIds(..., ['user.manage'])` で確定した管理可能 scope 配下に assignment を持つ AP ユーザーのみに限定し、response の assignment / permissions も同じ scope 範囲に絞る。初期 seed には `server_user_manager` / `service_user_manager` / `tenant_user_manager` を追加し、`admin` へ `user.manage` を暗黙包含させない方針を維持する
- 影響範囲: `routes/api.php`、`app/Http/Controllers/Api/UserIndexController.php`、`app/Http/Requests/Api/UserIndexRequest.php`、`app/Services/User/*`、`app/Services/Query/ListQueryService.php`、`database/seeders/AuthorizationSeeder.php`、`tests/Feature/Api/UserIndexControllerTest.php`、`tests/Feature/Api/ApiRoutePermissionPolicyTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次は `GET /api/users/{keycloak_sub}` や assignment 更新 API を検討し、同じ `user.manage` と scope 継承ルールの上で「見える assignment だけ返す」「付与・剥奪できる scope をどう制限するか」を service 契約として固定する

### AP 側ユーザー詳細は visible assignment が無ければ `404` に寄せる

- 背景: `GET /api/users` を追加した段階では、個別ユーザー詳細で「対象ユーザーは存在するが、管理可能 scope に assignment が無い」ケースを `403` にするか `404` にするかがまだ未固定だった。ここを曖昧にすると、次の assignment 更新 API でも存在漏えいの扱いがぶれやすかった
- 決定事項: `GET /api/users/{keycloak_sub}` を追加し、route では `required_permissions:user.manage` を必須にする。service では対象ユーザーの assignment を visible scope に絞って読み込み、1 件も visible assignment が無い場合は「存在しない」と同じく `404 Not Found` を返す。返却時の assignment / permissions は visible なものだけに限定する
- 影響範囲: `routes/api.php`、`app/Http/Controllers/Api/UserShowController.php`、`app/Services/User/FindVisibleUser.php`、`app/Services/User/UserShowService.php`、`tests/Feature/Api/UserShowControllerTest.php`、`tests/Feature/Api/ApiRoutePermissionPolicyTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次は assignment 更新 API を進め、付与・剥奪時にも「visible scope だけを操作対象にできる」「visible でない既存 assignment は存在を前提にしない」という契約で store/update/delete の振る舞いを明文化する

### user assignment の付与・剥奪も visible scope 限定で扱う

- 背景: user 詳細を `404` ベースで隠す方針を決めた後、assignment 付与・剥奪で invisible scope や invisible assignment をどう扱うかが未固定のままだと、管理 API ごとに存在漏えいの境界が変わる懸念があった
- 決定事項: `POST /api/users/{keycloak_sub}/assignments` と `DELETE /api/users/{keycloak_sub}/assignments` を追加し、どちらも route では `required_permissions:user.manage` を必須にする。付与時は target `scope_id` が visible scope に含まれることを要求し、`role.scope_layer` と `scope.layer` が一致しない組み合わせや重複 assignment は `422` とする。剥奪時は visible assignment のみ削除対象とし、存在しない assignment と invisible assignment は同じく `404 Not Found` に寄せる
- 影響範囲: `routes/api.php`、`app/Http/Requests/Api/UserAssignmentRequest.php`、`app/Http/Controllers/Api/UserAssignment*Controller.php`、`app/Services/User/*Assignment*`、`tests/Feature/Api/UserAssignment*ControllerTest.php`、`tests/Feature/Api/ApiRoutePermissionPolicyTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次は assignment の一括更新 API を急いで入れる前に、`role_id` 直指定のままで十分か、`role slug` へ寄せるか、あるいは visible assignment 一覧に識別子を追加するかを README 上の API 契約として先に比較して決める

### OpenAPI は未採用のまま移行容易性を維持する

- 背景: 現プロジェクトはプロトタイプ段階で、現時点では仕様管理の厳密さより試行錯誤の速度を優先したい。一方で、実開発ではこのプロトタイプをベースに進める前提のため、将来 OpenAPI を導入しやすい状態は保っておきたい
- 決定事項: 現段階では OpenAPI は採用せず、`ap-server/backend/README.md` を API 契約の記録先として継続利用する。ただし endpoint ごとに request / success response / error case を分け、設計判断は引継ぎメモへ分離して、後から `openapi.yaml` へ移しやすい粒度を維持する
- 影響範囲: `ap-server/backend` の API 記述運用、新規 endpoint 追加時の README 更新粒度、将来の OpenAPI 導入作業の前提
- 次の推奨アクション: 次に API 契約を広げる場合も、README の endpoint セクションでは shape と status code を先に揃え、OpenAPI 導入そのものは実開発へ移るタイミングでまとめて判断する

### user assignment の識別は `role_id` 直指定を維持しつつ response に `assignment.id` を追加する

- 背景: assignment 更新 API を追加した段階で、次に一括更新や個別更新を考えるなら `role_id` 直指定を続けるか `role slug` へ寄せるか、また visible assignment 一覧に識別子が要るかを先に決めておきたかった。`role slug` へ切り替えると既存 request や validation の修正範囲が広がる一方、今の課題は「見えている assignment を安定して指せること」に寄っていた
- 決定事項: request は当面 `role_id` + `scope_id` のまま維持し、visible assignment の response に `assignment.id` を追加する。これにより現行 API を崩さず、将来の update/delete/bulk API で「一覧から見えた assignment をそのまま指定する」導線を確保する
- 影響範囲: `app/Services/User/UserPayload.php`、`tests/Feature/Api/User*Test.php`、`ap-server/backend/README.md` の users 系 payload 契約
- 次の推奨アクション: 次に users 系 API を進めるなら、assignment 一括更新 API を入れる前に `assignment.id` ベースの個別更新/削除 route に寄せるか、現在の `scope_id + role_id` 指定を維持するかを route 設計として比較し、1 つに固定してから実装へ進む

### users 系 assignment 個別削除は `assignment.id` route を基準にする

- 背景: response に `assignment.id` を追加した後も、削除 API が `scope_id + role_id` payload 前提のままだと、一覧で見えた assignment をそのまま操作する導線が途切れ、将来の個別更新 API 設計も揃えづらかった
- 決定事項: `DELETE /api/users/{keycloak_sub}/assignments/{assignmentId}` を追加し、users 系 assignment の個別操作はこの `assignment.id` ベース route を今後の基準にする。既存の `DELETE /api/users/{keycloak_sub}/assignments` は互換目的で残すが、新しい個別操作を足す場合は `assignmentId` を優先する
- 影響範囲: `routes/api.php`、`app/Http/Controllers/Api/UserAssignmentItemDeleteController.php`、`app/Services/User/FindVisibleAssignment.php`、`app/Services/User/UserAssignmentDeleteService.php`、`tests/Feature/Api/UserAssignmentDeleteControllerTest.php`、`tests/Feature/Api/ApiRoutePermissionPolicyTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に users 系 API を進めるなら、`PATCH /api/users/{keycloak_sub}/assignments/{assignmentId}` のような assignment 単体更新が本当に必要かを先に見極め、必要なら `assignmentId` ベースで role 変更を許すか、削除 + 再付与に限定するかを API 契約として固定する

### users 系 assignment の変更は当面 `DELETE + POST` に限定する

- 背景: `assignment.id` ベースの個別削除 route を追加した段階で、次に `PATCH /api/users/{keycloak_sub}/assignments/{assignmentId}` を足す選択肢もあったが、visible scope 判定、role と scope layer の整合、存在漏えいの扱いを update API に重ねると契約が一気に複雑になりやすかった
- 決定事項: 現段階では assignment 単体更新 API は追加せず、変更は `DELETE /api/users/{keycloak_sub}/assignments/{assignmentId}` で既存 assignment を剥奪し、`POST /api/users/{keycloak_sub}/assignments` で新しい role を再付与する手順に限定する。この方針を users 系 assignment の基準運用として README に固定する
- 影響範囲: `ap-server/backend/README.md` の users 系 API 契約、`tests/Feature/Api/UserAssignmentStoreControllerTest.php` の運用シナリオ、今後の users 系 route 設計判断
- 次の推奨アクション: 次に users 系 API を進めるなら、assignment 一括更新 API を入れる前に「現状の delete + post 手順で運用上十分か」を確認し、不足が出た時だけ bulk endpoint か patch endpoint のどちらを採るかを改めて比較する

### users 系 assignment の一括更新 endpoint は当面追加しない

- 背景: assignment 単体更新を見送った次の論点として、一括更新 endpoint を先に入れるかどうかがあった。ただ、現状の users 系 API でも visible assignment の追加、個別削除、再付与を組み合わせれば目的状態を構成でき、ここで bulk を先回り導入すると payload 契約や partial failure の扱いが先に複雑化しやすかった
- 決定事項: 現段階では users 系 assignment の bulk endpoint は追加しない。複数 assignment の変更が必要な場合も、`POST /api/users/{keycloak_sub}/assignments` と `DELETE /api/users/{keycloak_sub}/assignments/{assignmentId}` を繰り返して表現することを基準運用とする
- 影響範囲: `ap-server/backend/README.md` の users 系 API 契約、`tests/Feature/Api/UserAssignmentStoreControllerTest.php` の sequential workflow、今後の users 系 route 増設判断
- 次の推奨アクション: 次に users 系 API を進めるなら、bulk が本当に必要になるまで route は増やさず、代わりに role 一覧や scope 選択補助のような UI 支援 API が必要かを見極める

### assignment 付与 UI の最小補助として `GET /api/roles` を追加する

- 背景: bulk や patch を見送った後も、実際に assignment 付与 UI を組むには「選択した scope に対応する role 候補」を安定して取得できる入口が必要だった。role 定義は AP 全体で共有されるため、まずは global resource として一覧取得できれば UI 側の選択肢生成をかなり簡素化できる
- 決定事項: `GET /api/roles` を追加し、route では `required_permissions:user.manage` を必須にする。`scope_layer`, `permission_role`, `slug`, `name`, `sort` を受け付け、response には role 本体と紐づく permission 一覧を含める。assignment 付与 UI では `scope_layer` filter を使って選択中 scope に対応する候補を絞る前提とする
- 影響範囲: `routes/api.php`、`app/Http/Requests/Api/RoleIndexRequest.php`、`app/Http/Controllers/Api/RoleIndexController.php`、`app/Services/Role/*`、`tests/Feature/Api/RoleIndexControllerTest.php`、`tests/Feature/Api/ApiRoutePermissionPolicyTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に users 系 UI 支援を広げるなら、scope 選択補助が本当に必要かを見極め、必要なら visible scope だけを返す `GET /api/scopes` 系 API を同じ `user.manage` 契約で追加する

### assignment 付与 UI の scope 選択補助として `GET /api/scopes` を追加する

- 背景: role 一覧だけでは assignment 付与 UI の候補生成が完結せず、実際には「今の管理者が選べる scope を可視化する入口」も必要だった。visible scope 判定はすでに `AuthorizationService::accessibleScopeIds(..., ['user.manage'])` に寄っていたため、同じ契約を API としてそのまま出せれば UI 側の独自判定を避けられる
- 決定事項: `GET /api/scopes` を追加し、route では `required_permissions:user.manage` を必須にする。返却対象は visible scope のみに限定し、`layer`, `parent_scope_id`, `code`, `name`, `sort` で絞り込めるようにする。scope 選択 UI では `layer` や `parent_scope_id` を使って drill-down しながら候補を取得する前提とする
- 影響範囲: `routes/api.php`、`app/Http/Requests/Api/ScopeIndexRequest.php`、`app/Http/Controllers/Api/ScopeIndexController.php`、`app/Services/Scope/*`、`tests/Feature/Api/ScopeIndexControllerTest.php`、`tests/Feature/Api/ApiRoutePermissionPolicyTest.php`、`ap-server/backend/README.md`
- 次の推奨アクション: 次に users 系 UI 支援を広げるなら、role 一覧と scope 一覧が揃った前提で、assignment 付与 UI に追加で必要なのが「現在ユーザーの visible assignment 一覧」なのか「候補 API の pagination / search 強化」なのかを見極める

### 一覧状態復元を見据えても pagination/search は endpoint ごとに温度差を持たせる

- 背景: 一覧画面から詳細画面へ遷移して戻るときに、遷移前の一覧状態を復元したい要件を考えると、API 側でも filter / sort / page の再現性が必要になる。ただし `users`, `roles`, `scopes` は件数の伸び方や UI での使い方が異なり、同じ粒度で pagination/search を先回り導入するとプロトタイプ段階では過剰になりやすかった
- 決定事項: 一覧状態復元は「URL やフロント state に保持した filter / sort / page を同じ API に再送して再現できること」を前提に考える。そのうえで、`GET /api/users` は管理一覧の主画面候補なので現状の `page`, `per_page`, `sort`, 検索条件を維持し、今後も一覧状態復元の基準 endpoint とする。一方 `GET /api/roles` と `GET /api/scopes` は現段階では候補選択 UI 向けの補助 API と位置づけ、まずは filter / sort のみで運用し、pagination は実際に件数や UX 上の不足が確認できた時点で追加する
- 影響範囲: `GET /api/users`, `GET /api/roles`, `GET /api/scopes` の今後の拡張判断、一覧画面状態復元の前提、フロントエンド側の URL / state 設計、`ap-server/backend/README.md`
- 次の推奨アクション: 次に users 系 UI を具体化するなら、まずはフロント側でどの一覧画面に「戻った時の状態復元」が必要かを洗い出し、その対象が `roles` / `scopes` にも及ぶと分かった時だけ `page`, `per_page`, `meta` を追加する

### `GET /api/users` は一覧状態復元の基準 endpoint として現状維持する

- 背景: `users` 一覧は詳細画面への遷移元になりやすく、一覧状態復元の要求がもっとも自然に出る endpoint だった。すでに `scope_id`, `keycloak_sub`, `display_name`, `email`, `sort`, `page`, `per_page`, `meta` を備えているため、ここを不用意に削るより基準として扱う方が後続の画面設計と相性が良かった
- 決定事項: `GET /api/users` は現在の pagination/search 契約を維持し、一覧状態復元を支える基準 endpoint とする。フロント側で URL や state に保持する query 条件も、この endpoint の request 契約をそのまま使う前提でよい
- 影響範囲: `GET /api/users` の request / response 契約、users 一覧画面の URL 設計、将来の OpenAPI 化での query parameter 表現
- 次の推奨アクション: users 一覧 UI を作る段階では、`scope_id`, `keycloak_sub`, `display_name`, `email`, `sort`, `page`, `per_page` を URL へ保持するかをフロント側で先に決める

### `GET /api/roles` と `GET /api/scopes` は当面 filter/sort のみで十分とする

- 背景: `roles` は seed ベースの固定的な候補一覧、`scopes` も現段階では assignment 付与 UI の drill-down 補助という位置づけで、`users` 一覧ほど「詳細へ遷移して戻る時に同じ page を再現したい」要求が強くなかった。現時点で pagination を先回り追加すると、`meta` 契約や page 管理だけが先に増えて運用コストが上がりやすかった
- 決定事項: `GET /api/roles` と `GET /api/scopes` は当面 `filter + sort` のみをサポートし、pagination / `meta` は未導入のままとする。状態復元が必要になった場合も、まずは `scope_layer`, `permission_role`, `slug`, `name`, `layer`, `parent_scope_id`, `code`, `sort` のような filter/sort 条件だけを URL に保持する前提で運用する
- 影響範囲: `GET /api/roles`, `GET /api/scopes` の current contract、候補選択 UI の実装方針、今後の pagination 追加判断
- 次の推奨アクション: `roles` / `scopes` の候補件数が実際に多くなったか、または一覧状態復元で page 単位の再現が必要になった時点で、`users` と同じ `meta.current_page`, `meta.per_page`, `meta.total`, `meta.last_page` を追加するかを再評価する

### フロント雛形段階では users 系候補 API の追加拡張を止めてよい

- 背景: 一覧状態復元の前提を整理したうえで `ap-server/frontend` を確認すると、現時点では `app/pages/index.vue` の雛形だけがあり、`users`, `roles`, `scopes` の一覧画面や詳細画面、一覧から詳細へ遷移して戻る具体フローはまだ実装されていなかった。この段階で backend 側の候補 API に pagination/search や追加 resource を先回りで増やすと、実際の画面要件が固まる前に契約だけが広がりやすい
- 決定事項: 現時点では `GET /api/users` を一覧状態復元の基準 endpoint として維持しつつ、`GET /api/roles` と `GET /api/scopes` の契約は当面このまま据え置く。users 系 UI 支援 API の次拡張は、フロント側で実際の一覧画面や詳細遷移フローが具体化してから再開する
- 影響範囲: `ap-server/backend` の API 拡張優先順位、`ap-server/frontend` の画面設計前提、README を正とするプロトタイプ段階の契約管理
- 次の推奨アクション: 次は `ap-server/frontend` 側で users 一覧と詳細の最小画面フローを定義し、「戻る時に復元したい query 条件」と「roles/scopes 候補で本当に必要な search 条件」を画面単位で洗い出してから、必要な backend 契約だけを追加する

### users 一覧は状態復元を前提にせず、曖昧検索だけを活かす

- 背景: フロント側の users 一覧要件として、「詳細から戻った時に遷移前の一覧状態を復元する必要はない」「検索条件は必須ではないが、名前 / メールアドレスでの曖昧検索は欲しい」という方針が固まった。これにより、users 一覧を一覧状態復元の代表例として強く扱う必要は薄れ、むしろ現在の検索条件が UI 要件をすでに満たしているかが焦点になった
- 決定事項: `GET /api/users` は `keyword` filter を採用し、`display_name` / `email` を横断する曖昧検索を users 一覧 UI の最小契約とする。一方で、users 一覧について「戻る時の query / page 復元」を前提にした追加 backend 対応は当面行わない
- 影響範囲: `GET /api/users` の今後の拡張判断、users 一覧 UI の検索仕様、一覧状態復元を前提とした backend 優先度
- 次の推奨アクション: 次に進めるなら、frontend 側で users 一覧の検索 UI を `keyword` 1 入力前提で具体化し、`keycloak_sub` を UI に出すかどうかと、一覧の sort をどこまで露出するかを画面単位で決める

### users 一覧は管理可能な配下 scope を含めて無条件表示し、初期 sort はメール昇順にする

- 背景: users 一覧 UI の要件として、「上位ユーザーは配下のユーザー管理が可能で、初期の管理者登録や代行メンテナンスにも使う」「初回表示は条件なし」「sort は名称 / メールアドレスで、初期はメールアドレス昇順」という前提が固まった。`所属 scope そのものだけ` に絞ると、システムユーザーが service や tenant のユーザー一覧へ遷移した時に必要な参照と管理ができなかった
- 決定事項: `GET /api/users` の一覧対象は `AuthorizationService::accessibleScopeIds(..., ['user.manage'])` ベースとし、`user.manage` を持つ scope とその配下 scope の assignment を一覧対象に含める。初回表示は query なしで取得し、default sort は `email` 昇順とする。`keyword` はオプションの横断曖昧検索として維持する
- 影響範囲: `app/Services/User/UserIndexService.php`、`tests/Feature/Api/UserIndexControllerTest.php`、users 一覧 UI の表示前提、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進めるなら、frontend 側で users 一覧の column と sort UI を固めつつ、`scope_id` を使った service / tenant 切り替え導線をどう置くかを画面側で決める

### users 管理 UI は drill-down 操作を基本にし、配下一覧は `scope_id` で絞る

- 背景: 画面イメージとして、上位ユーザーが下位レイヤーの対象を操作する時は、server -> service -> tenant のように対象レイヤーまで drill-down してから操作する前提が固まった。一方で、上位ユーザーは配下の対象一覧自体は参照できる必要があり、service ユーザーが配下 tenant ユーザーを一覧表示して tenant 条件で絞るような使い方も想定されている
- 決定事項: users 管理 UI の基本動線は drill-down とし、実際の一覧表示や操作対象の確定は選択済み scope を `scope_id` として `GET /api/users` に渡して行う。上位ユーザーは配下 scope を参照できるが、作成やメンテナンスの実操作は対象 scope を明示的に選んだ状態で進める前提とする
- 影響範囲: `GET /api/users` の `scope_id` filter の位置づけ、frontend 側の service / tenant 選択導線、users 一覧画面の drill-down UX、`ap-server/backend/README.md`
- 次の推奨アクション: 次に進めるなら、frontend 側の画面遷移を前提に `GET /api/scopes` の `parent_scope_id` を使った drill-down 候補取得フローを具体化し、service 一覧から tenant 一覧へどう繋ぐかを決める

### frontend の users 雛形では `scope_id + keyword + sort` だけを live query に使う

- 背景: `ap-server/frontend` 側で users 一覧と詳細の最小フローを追加し、service -> tenant の drill-down、一覧検索、詳細表示までを先に具体化した。現段階では CurrentUser / Bearer token の受け口がまだ無く、roles/scopes 候補 API へ pagination や追加 search を先回りで足すより、画面で本当に使う query を固定するほうが次の接続作業へ繋げやすかった
- 決定事項: frontend の一覧状態は `service_scope_id` / `tenant_scope_id` を持っても、backend へ送る live query は `GET /api/users` の `scope_id`, `keyword`, `sort` に限定する。`GET /api/scopes` は drill-down 候補取得のため `layer` と `parent_scope_id` だけを使い、roles/scopes 向けの追加 search や pagination は当面追加しない
- 影響範囲: `GET /api/users`, `GET /api/scopes` の拡張優先順位、`ap-server/frontend/README.md` の users UI 前提、CurrentUser / token 受け口を追加する次工程、`ap-server/backend/README.md`
- 次の推奨アクション: 次は frontend 側で CurrentUser または Bearer token の受け口を 1 箇所に寄せ、`mock` から `live` へ切り替えても `GET /api/users`, `GET /api/users/{keycloakSub}`, `GET /api/scopes` を同じ画面で確認できるようにする

### AP Frontend の SSO bridge では `global-login` と `ap-frontend` の両 token audience を受け入れる

- 背景: AP Frontend に `global login -> /auth/bridge -> /auth/callback` の SSO bridge を追加すると、bridge 後に browser が保持する access token の `azp` は `ap-frontend` になる。一方、live の `ap-backend` は `KEYCLOAK_CLIENT_ID=global-login` 前提のままだったため、実際に bridge で取った token を `GET /api/me` へ流すと `current_user: null` になり、AP Frontend の自然復帰が最後で詰まった
- 決定事項: backend の Keycloak token resolver は単一 `KEYCLOAK_CLIENT_ID` 固定ではなく、`KEYCLOAK_ACCEPTED_CLIENT_IDS` で複数 audience / `azp` を受け入れる。live の AP backend 既定値は `global-login,ap-frontend` とし、従来の debug token と AP Frontend bridge token の両方を同じ `/api/me*` / `/api/users*` 系で扱えるようにする
- 影響範囲: `ap-server/backend/config/services.php`、`app/Services/Auth/KeycloakTokenCurrentUserResolver.php`、`docker/env/ap-backend.env`、AP Frontend の SSO bridge live 実測、今後 app-a / app-b token を追加で許可する時の env 運用
- 次の推奨アクション: 次は `docker compose up -d --force-recreate ap-backend ap-backend-fpm nginx` で accepted client ids を反映し、AP Frontend bridge で取った `ap-frontend` token が `GET /api/me` と `GET /api/me/authorization` を live で解決できることを確認する

### live の AP Frontend bridge token でも `GET /api/me*` は `Alice A` を返せる

- 背景: accepted client ids を追加したあとも、実 token で確認しない限り「env を足しただけで本当に bridge token が通るのか」は断言できなかった。特に AP Frontend 側の bridge は `prompt=none + PKCE` の code flow を使うため、従来の `global-login` direct grant token と claim 構成が少し違っていた
- 決定事項: `docker compose up -d --force-recreate ap-backend ap-backend-fpm nginx` 後に live の SSO bridge を HTTP で再現すると、`ap-frontend` token の payload は `sub=tenant-user-a`, `azp=ap-frontend`, `allowed-origins=[https://ap.example.com]` で、これを `https://ap-backend-fpm.example.com/api/me` と `/api/me/authorization` へ流した結果、どちらも `current_user = Alice A` を返した。`/api/me/authorization` では `user.manage` を含む authorization まで取得できたため、backend 側では AP Frontend bridge token を live で受け入れられると扱う
- 影響範囲: AP Frontend の SSO bridge live 検証手順、`KEYCLOAK_ACCEPTED_CLIENT_IDS` の既定値、今後 `/api/users*` を bridge token で叩く前提、backend と frontend README の整合
- 次の推奨アクション: 次は実ブラウザで AP Frontend の users 画面から `SSO Login` を押し、bridge token で `Current User = Alice A` と users 一覧 / 詳細の query 復元が UI 上でも自然に見えるかを通し確認する

### frontend の CurrentUser 取得口は `GET /api/me` を基準にする

- 背景: frontend 側で auth 入口を 1 箇所に寄せる作業を進め、app shell から mode 切り替えと Bearer token 設定を扱えるようにした。これにより users 一覧 / 詳細がどちらも同じ token で live API を叩ける状態になり、CurrentUser の解決も backend 契約に沿って一本化できた
- 決定事項: frontend は `GET /api/me` を CurrentUser の唯一の取得入口として扱い、users 系 API の live 呼び出しも同じ Bearer token を使う。backend 側では users 系 API 追加時も `GET /api/me` と矛盾しない CurrentUser 表現を維持する
- 影響範囲: `GET /api/me` の継続運用、frontend の auth composable 前提、今後の assignment UI 実装時の token 受け渡し、`ap-server/backend/README.md`
- 次の推奨アクション: 次は frontend の users 詳細で assignment 追加 / 削除 UI を具体化し、その時点で `GET /api/roles` と `GET /api/scopes` に追加 filter が必要かを再評価する

### dashboard のメニュー切り替えは `GET /api/me/authorization` の permissions を基準にする

- 背景: frontend 側でログイン後ホーム、header / sidebar / footer を含む dashboard shell を追加し、ヘッダーやサイドバーのメニュー構成をログインユーザーごとに切り替える要件が入った。`GET /api/me` だけでは権限情報が足りないため、既存の `GET /api/me/authorization` を同じ auth 入口で併用する形にした
- 決定事項: frontend では `GET /api/me` と `GET /api/me/authorization` を同じ Bearer token で取得し、dashboard sidebar のメニューは `authorization.permissions` と assignment の primary scope layer を基準に組み立てる。backend 側では dashboard メニュー制御に使う前提で `permissions` 集約の表現を維持する
- 影響範囲: `GET /api/me/authorization` の継続運用、frontend dashboard shell、今後のメニュー追加時に必要な permission 粒度、`ap-server/backend/README.md`
- 次の推奨アクション: 次は users 詳細の assignment 操作 UI を dashboard shell 上へ載せ、role / scope 候補 API で frontend が本当に必要とする filter 条件を確認する

### 認可は「上位から下位への継承」を許容しつつ、実操作では対象 scope の明示を残す

- 背景: 認可設計を見直す中で、server / service レイヤーに下位レイヤーを個別に表現しすぎると、1 AP あたりの service 数が少ない前提に対して過剰設計になりやすいことが分かった。一方で、上位レイヤーは下位レイヤーの管理や業務代行を担うため、`上位の権限は配下にも届く` という運用自体は維持したい
- 決定事項: 直接付与された role / permission は、その所属 scope と descendant scope に対する scope-bound な操作に限って有効とする。つまり `上位は配下を管理できる` を基本方針にする一方、実際の作成・更新・削除・付与などの操作は、従来どおり対象 scope を明示して進める。`権限は継承してよいが、操作は必ず対象を指定して行う` を backend 認可方針の要約とする
- 影響範囲: `AuthorizationService` の granted / accessible の役割分担、`required_permissions` middleware の使い方、users/object/playbook/policy/checklist など scope を持つ API の認可前提、frontend の drill-down UI、今後の認可仕様メモ全体
- 次の推奨アクション: 次に実装を進めるときは、1) route 単位の `permissions` 判定だけで完結している API がないか確認する、2) scope を持つ操作は対象 scope を必ず受け取る契約に寄せる、3) `direct grant` と `descendant 由来の access` を API 表現やテストで混同していないかを点検する

### `GET /api/me/authorization` は permission ごとの direct grant と descendant access を併記する

- 背景: 認可方針を `上位は配下を管理できる` に寄せても、API 上で direct grant と descendant 由来の access を区別できないと、frontend の判断材料や後続テストが再び曖昧になりやすかった。既存の `assignments` と集約済み `permissions` は維持しつつ、その permission がどの scope に直接付与され、どこまで配下へ届くかを同じレスポンス内で確認できる形が必要だった
- 決定事項: `GET /api/me/authorization` の `authorization` には既存の `assignments` と `permissions` を残したまま、permission slug ごとの `permission_scopes` を追加する。各 permission には `granted_scope_ids` と `accessible_scope_ids` を持たせ、前者は direct grant、後者は descendant を含む実効 access として扱う
- 影響範囲: `AuthorizationService` の response 形式、`tests/Feature/Api/MeAuthorizationControllerTest.php`、今後 frontend が認可の根拠表示や drill-down 補助を足す時の参照契約、`ap-server/backend/README.md`
- 次の推奨アクション: 次に frontend 側へ認可根拠表示を足すなら、まずは `permission_scopes` を debug / 補助表示に限定して使い、メニュー切り替えの一次判定は引き続き `authorization.permissions` で簡潔に保つ
