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
