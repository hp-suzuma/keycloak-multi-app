# AP Frontend

`ap-server/frontend` は、新しい AP サーバー向けの `Nuxt 4 + Nuxt UI` フロントエンドです。

## コンテナでの作業

起動:

```bash
docker compose up -d ap-frontend
```

コンテナへ入る:

```bash
docker compose exec ap-frontend sh
```

ブラウザ確認:

```bash
https://ap.example.com
```

`global.example.com` はログインポータル / BFF 側で、`Auth Entry` パネルは `ap.example.com` の AP Frontend 側に出る。

## アプリ操作

依存関係のインストール:

```bash
npm install
```

開発サーバー起動:

```bash
npm run dev -- --host 0.0.0.0 --port 3000
```

本番ビルド:

```bash
npm run build
```

## 現在の画面フロー

- `/`
  ログイン後ホームとして使う dashboard 画面。header / sidebar / footer を含む共通 shell を使う
- `/users`
  service -> tenant の drill-down で対象 scope を決め、`keyword` 1 入力と `sort` で users 一覧を絞る最小画面を追加した
- `/users/[keycloakSub]`
  users 詳細として identity / visible assignments / aggregated permissions を確認できる
- app shell
  `Auth Entry` パネルで `GET /api/me` と `GET /api/me/authorization` を叩き、CurrentUser / authorization / mode / Bearer token を共通管理する
- `/objects`, `/playbooks`, `/policies`, `/checklists`, `/settings/security`
  サイドバー導線確認用の暫定ページ

## API 接続モード

- 既定値は `mock`
  認証受け口が未実装でも画面フローを先に詰められるようにする
- live 接続を使う場合は公開 runtime config を設定する

```bash
NUXT_PUBLIC_AP_USER_MANAGEMENT_MODE=live
NUXT_PUBLIC_AP_API_BASE=https://ap-backend-fpm.example.com/api
NUXT_PUBLIC_AP_API_BEARER_TOKEN=<user.manage を持つ Bearer token>
```

dev 用 token 取得:

```bash
curl -k https://keycloak.example.com/realms/myapp/protocol/openid-connect/token \
  -H 'Content-Type: application/x-www-form-urlencoded' \
  --data-urlencode 'grant_type=password' \
  --data-urlencode 'client_id=global-login' \
  --data-urlencode 'client_secret=global-secret' \
  --data-urlencode 'username=alice' \
  --data-urlencode 'password=password'
```

返ってきた JSON の `access_token` を `Auth Entry` の `Bearer Token Override` にそのまま貼る。`Bearer ` 接頭辞や JSON 全体は貼らない。

## live mode 検証の現状前提

- `ap-backend` の live 用 env は `docker/env/ap-backend.env` を正とし、compose では `docker/env/laravel.common.env` と合わせて読む
- `ap-server/backend/.env` は scaffold / 単体作業用として残っていても、frontend の live mode 検証前提には使わない
- frontend から `http://localhost:8000/api` を叩くには、`docker compose up -d --build postgres keycloak ap-backend` を使う。`ap-backend` は image entrypoint で `php artisan serve --host=0.0.0.0 --port=8000` まで自動起動する
- live mode の users 管理 UI を実測するには、少なくとも ap-backend 側で次を揃える必要がある
  - Keycloak 検証設定: `docker/env/ap-backend.env` の `KEYCLOAK_ISSUER`, `KEYCLOAK_CLIENT_ID`, `KEYCLOAK_JWKS_URL`
  - users / roles / scopes を返せる data source: `ap_server` DB を作成し、`php artisan migrate --seed` で初期 schema / AuthorizationSeeder を投入し、必要なら `ApUserManagementDemoSeeder` で最小 demo データも入れる
  - `user.manage` を持つ Bearer token の取得導線
- 実測ログ: `docker compose exec postgres psql -U myapp -d postgres -c "CREATE DATABASE ap_server"`、`docker compose exec ap-backend php artisan migrate --seed --force`、`docker compose exec ap-backend php artisan db:seed --class=ApUserManagementDemoSeeder --force`、Keycloak の `global-login` / `alice:password` から token 取得、`GET /api/health` は host / container 両方で成功まで確認済み
- 比較ログ: `https://global.example.com/ap-api/*` で nginx proxy 経路も追加し、`GET /ap-api/health` は成功した。さらに `https://ap-backend.example.com/api/*` と `https://ap-backend-fpm.example.com/api/*` を用意して direct `http://127.0.0.1:8000/api/*` と比較したところ、fresh token では `GET /api/me` / `GET /api/me/authorization` が 3 経路とも成功した
- 実測済み endpoint: `https://ap-backend-fpm.example.com/api/me`、`/me/authorization`、`/scopes?sort=name`、`/roles?sort=name`、`/users?sort=email`、`/users/tenant-user-b`
- 注意点: 直前に `current_user: null` になっていた主因は HTTP 経路ではなく expired token だった。live 切り分け時は token を取り直してから比較する
- frontend の既定 `runtimeConfig.public.apApiBase` も `https://ap-backend-fpm.example.com/api` に寄せた。必要なら `NUXT_PUBLIC_AP_API_BASE` で上書きする
- 次チャットで live mode 実測へ進む場合は、先に [ap-server/backend/README.md](/home/wsat/projects/keycloak-multi-app/ap-server/backend/README.md:875) の初回セットアップを済ませてから Bearer token 取得へ進む

## 引継ぎメモ

### users 管理 UI は keyword 検索と scope drill-down を最小契約にする

- 背景: `ap-server/backend/README.md` の推奨アクションに従い、先に users 一覧と詳細の実画面フローを置いてから backend 契約の追加要否を見極める段階だった。認証受け口は未整備なので、live API だけに寄せると画面検討そのものが止まりやすかった
- 決定事項: frontend では `service_scope_id` / `tenant_scope_id` を画面状態として持ち、backend へ送る実クエリは `scope_id = tenant_scope_id ?? service_scope_id`、`keyword`、`sort` に限定する。`roles` / `scopes` 向けの追加 search UI や pagination UI はまだ入れない
- 影響範囲: `ap-server/frontend/app/pages/users/index.vue`、`ap-server/frontend/app/pages/users/[keycloakSub].vue`、live API 接続時の runtime config、今後の backend 契約追加判断
- 次の推奨アクション: 次は `GET /api/users/{keycloakSub}` と users 一覧の live 表示を使いながら、assignment 追加 / 削除 UI に必要な role / scope 候補の見せ方を具体化する

### frontend の auth 入口は `GET /api/me` に固定する

- 背景: users 画面を live 化するには Bearer token と CurrentUser を毎画面で別々に扱わないほうがよかった。backend にはすでに `GET /api/me` があり、認証方式を隠したまま CurrentUser を返せる契約がある
- 決定事項: frontend では `useApAuth()` を共通 auth composable とし、`GET /api/me` を CurrentUser の唯一の取得入口として扱う。mode 切り替え、Bearer token override、CurrentUser refresh は app shell の `Auth Entry` パネルに集約し、users 系 API も同じ token を使う
- 影響範囲: `ap-server/frontend/app/composables/useApAuth.ts`、`ap-server/frontend/app/components/AppAuthPanel.vue`、`ap-server/frontend/app/composables/useApUserManagement.ts`、`ap-server/frontend/app/app.vue`
- 次の推奨アクション: 次は users 詳細に assignment 追加 / 削除 UI の雛形を置き、`GET /api/roles` と `GET /api/scopes` をどの順序で候補表示するのが扱いやすいかを画面で詰める

### users 詳細の assignment 操作は scope 選択先行 + single-assignment API に揃える

- 背景: users 詳細で assignment の追加 / 削除 UI を具体化する段階になり、backend 側にはすでに `GET /api/scopes`, `GET /api/roles`, `POST /api/users/{keycloakSub}/assignments`, `DELETE /api/users/{keycloakSub}/assignments/{assignmentId}` が揃っていた。ここで role 候補を先に見せるより、target scope を先に確定し、その layer に一致する role だけへ絞るほうが既存契約と UI の責務分離が素直だった
- 決定事項: frontend の users 詳細では、route query の drill-down 状態を引き継ぎつつ assignment 追加フォームを置き、候補表示は `scope -> role` の順で行う。scope 候補は `GET /api/scopes` の `layer` / `parent_scope_id` を既存一覧と同じ前提で使い、role 候補は選択済み scope の `layer` を `GET /api/roles` の `scope_layer` へ渡して絞る。削除は `assignmentId` を使う個別 DELETE endpoint を基準にする
- 影響範囲: `ap-server/frontend/app/pages/users/[keycloakSub].vue`、`ap-server/frontend/app/composables/useApUserManagement.ts`、users 詳細の mock/live 両モード、今後の role / scope 候補 UI 拡張判断
- 次の推奨アクション: 次は live mode で実際の token を使い、service / tenant drill-down ごとに表示される scope 候補数と role 候補数を確認して、必要なら scope 絞り込み補助や確認ダイアログを追加する

### users 詳細の assignment UI は候補絞り込みと削除確認を先に持つ

- 背景: users 詳細で assignment 追加 / 削除 UI を載せたあと、live mode で scope 候補数が増えた時に選択しづらくなる懸念と、誤削除を即時反映してしまう懸念が残った。実 token を使った live 確認前でも、frontend 側で先に吸収できる扱いに寄せておく価値があった
- 決定事項: assignment 追加フォームには `scope 名 / code / layer` を横断する絞り込み入力を置き、表示候補数も見せる。assignment 削除は即時実行せず、対象 scope / role を確認できる modal を挟んでから `DELETE /api/users/{keycloak_sub}/assignments/{assignmentId}` を呼ぶ
- 影響範囲: `ap-server/frontend/app/pages/users/[keycloakSub].vue`、今後の live mode 操作性確認、users 詳細の誤操作防止方針
- 次の推奨アクション: 次は live mode で実 token を使い、service / tenant drill-down ごとの候補件数と modal 導線の操作感を確認し、必要なら role 候補側にも search や permission 要約を追加する

### users 詳細の role 候補は search と permission 要約を同じフォーム内で見せる

- 背景: scope 候補の絞り込みと削除確認を入れたあとも、role 候補数が増えた時に「どの role を選ぶと何が付与されるか」を一覧だけで判断しづらかった。live mode の実確認前でも、role 名だけでなく permission 観点で絞れて、選択結果をその場で確認できる形にしておくほうが扱いやすい
- 決定事項: assignment 追加フォームの role 選択には `name / slug / permission` 横断 search を追加し、現在選択中 role の `permission_role` と付与される permission badge を同じフォーム内に summary 表示する
- 影響範囲: `ap-server/frontend/app/pages/users/[keycloakSub].vue`、今後の live mode での role 候補確認、permission 要約の見せ方に関する後続判断
- 次の推奨アクション: 次は live mode で実 token を使い、scope / role 候補件数と summary 表示の操作感を確認し、必要なら backend 契約を増やさずに role 一覧のソートや補助ラベルを整える

### users 詳細の role 候補は permission_role filter と sort を先に持つ

- 背景: 次の推奨アクションとして live mode の実 token 確認に進もうとしたが、このリポジトリ内だけでは AP backend にそのまま通る `user.manage` 付き Bearer token の取得導線をまだ確定できなかった。一方で、backend 契約にはすでに `GET /api/roles` の `permission_role` / `sort` があり、候補数増加への備えは frontend 側だけでも前進できた
- 決定事項: users 詳細の assignment 追加フォームでは、role 候補に `permission_role` filter と `sort` selector を追加し、summary 表示のラベルも `admin / operator / viewer / user_manager` の生値ではなく UI 向け表記へ寄せる。live token の実確認は次チャット以降も継続課題として扱う
- 影響範囲: `ap-server/frontend/app/pages/users/[keycloakSub].vue`、`ap-server/frontend/app/composables/useApUserManagement.ts`、`GET /api/roles` 既存契約の frontend 利用方法、次回の live mode 検証前提
- 次の推奨アクション: 次は AP backend に通る `user.manage` 付き Bearer token の取得導線を確定し、live mode で `GET /api/me`, `GET /api/roles`, `GET /api/scopes`, `GET /api/users/{keycloakSub}` を実際に叩いて候補件数と操作感を記録する

### live mode 実測は ap-backend の env 未整備を先に解消する

- 背景: 次の推奨アクションに従って ap-backend コンテナ内の実設定を確認したところ、`ap-server/backend/.env` のまま `APP_ENV=local` / `DB_CONNECTION=sqlite` で起動しており、Keycloak 関連 env も未設定だった。この状態では `user.manage` 付き Bearer token を frontend に入れても、users 管理 UI の live 実測条件が揃わない
- 決定事項: users 管理 UI の live mode 実測では、`ap-server/backend/.env` ではなく `docker/env/ap-backend.env` を正とし、compose でもその env を読む形へ寄せる。host から frontend が叩けるよう `ap-backend` は `8000` 番を公開し、DB は `ap_server` を別名で持つ前提にする
- 影響範囲: `docker-compose.yml`、`docker/env/ap-backend.env`、`ap-server/backend/README.md`、`ap-server/frontend/README.md`、次回の live mode 実測手順
- 次の推奨アクション: 次は `ap_server` DB 作成と `php artisan migrate --seed` を済ませ、`user.manage` 付き token の取得導線を確定してから `GET /api/me`, `GET /api/roles`, `GET /api/scopes`, `GET /api/users/{keycloakSub}` の live 実測へ戻る

### live mode auth fallback は frontend でも header + query を併送する

- 背景: `ap-backend` 側の env と data source は揃い、Keycloak token も実取得できたが、`php artisan serve` を host 公開 `8000` 経由で叩く live 実測では `Authorization` が request へ見えず `GET /api/me` が `current_user: null` のまま残った。backend では `AUTHORIZATION` / `HTTP_X_FORWARDED_AUTHORIZATION` / `access_token` query まで読めるようにしても、built-in server 経路の実測はまだ未解決だった
- 決定事項: frontend の live mode request では Bearer token を `Authorization` に加えて `X-Forwarded-Authorization` と `access_token` query にも載せる。対象は `useApAuth()` の `/me`, `/me/authorization` と `useApUserManagement()` の `/users`, `/users/{keycloakSub}`, `/roles`, `/scopes`, assignment 追加 / 削除 request 全体とする
- 影響範囲: `ap-server/frontend/app/composables/useApAuth.ts`、`ap-server/frontend/app/composables/useApUserManagement.ts`、backend の live token fallback 実測、次回の live mode 認証切り分け
- 次の推奨アクション: 次は nginx など `php artisan serve` 以外の HTTP 経路で同じ request を流し、どの経路なら `current_user` が解決されるかを比較して users 管理 UI の live 実測ルートを確定する

### nginx proxy だけでは `current_user: null` は変わらない

- 背景: `php artisan serve` の host 公開 `8000` だけが原因かを切り分けるため、nginx に `global.example.com/ap-api/* -> ap-backend:8000/api/*` の proxy 経路を追加して同じ Keycloak token で `GET /api/me` と `GET /api/me/authorization` を比較した
- 決定事項: simple proxy 経路では解消せず、`GET /ap-api/health` は成功する一方で `GET /ap-api/me` / `GET /ap-api/me/authorization` は direct 経路と同じく `current_user: null` だった。次段では `php artisan serve` を完全に外した比較として `ap-backend-fpm` と `ap-backend-fpm.example.com` を用意し、FastCGI 経路での差を見る
- 影響範囲: `nginx/conf.d/default.conf`、`docker-compose.yml`、`docker/ap-backend/Dockerfile`、`docker/laravel/init-laravel-fpm-app.sh`、live mode の API base 候補
- 次の推奨アクション: 次は `docker compose up -d --build ap-backend ap-backend-fpm nginx` の完了後に `https://ap-backend.example.com/api/me` と `https://ap-backend-fpm.example.com/api/me` を同じ token で比較し、FPM 経路で `current_user` が解決されるかを確認する

### direct / nginx-artisan / nginx-fpm の 3 経路とも live users API は通る

- 背景: `ap-backend.example.com` と `ap-backend-fpm.example.com` を用意しても最初は `current_user: null` が出たが、その時点の token がすでに期限切れだった。fresh token を取り直したうえで再比較しないと HTTP 経路の差を誤判定する状態だった
- 決定事項: fresh token を取り直すと `GET /api/me` と `GET /api/me/authorization` は direct `http://127.0.0.1:8000/api/*`、`https://ap-backend.example.com/api/*`、`https://ap-backend-fpm.example.com/api/*` の 3 経路すべてで成功した。さらに `https://ap-backend-fpm.example.com/api/scopes`、`/roles`、`/users`、`/users/tenant-user-b` も live で成功したため、frontend live mode の推奨 API base は nginx 経由の `https://ap-backend-fpm.example.com/api` に置いてよい
- 影響範囲: `ap-server/frontend` の live mode API base 設定、`nginx/conf.d/default.conf`、`docker-compose.yml`、live 実測手順、次回の users UI 操作確認
- 次の推奨アクション: 次は frontend 側の live mode API base を `https://ap-backend-fpm.example.com/api` で実際に流し、users 一覧 / 詳細 / assignment 追加削除 UI の操作感を画面で確認する

### users 詳細の assignment 追加 / 削除 API は nginx-fpm 経路で往復確認済み

- 背景: `GET /me`、`/users`、`/users/{keycloakSub}` が live で通っても、users 詳細 UI の本命は assignment 追加 / 削除なので、ここが実 API で往復できるかを確認する必要があった
- 決定事項: fresh token を使った `https://ap-backend-fpm.example.com/api/users/tenant-user-b/assignments` への `POST` と `DELETE /assignments/{assignmentId}` を実測し、existing assignment には `422 The assignment already exists.`、新規 assignment には `201`、削除には `204`、削除後の `GET /users/tenant-user-b` では元の assignment 構成へ戻ることを確認した。live token は 5 分で expiry するので、add / delete 確認は fresh token で続けて実行する
- 影響範囲: users 詳細の assignment 追加 / 削除 UI、`ap-server/frontend/app/composables/useApUserManagement.ts`、live mode 操作確認手順、README 上の token 運用前提
- 次の推奨アクション: 次は frontend 画面上で users 一覧 / 詳細 / assignment modal を実際に操作し、成功時メッセージや再取得タイミングが体感上問題ないかを見る

### live mode の画面導線は auth 入口に API base と token expiry の注意を出す

- 背景: live API 自体は `https://ap-backend-fpm.example.com/api` で通る状態になったが、画面側では `CurrentUser 未取得` や `Forbidden` が出た時に HTTP 経路の問題と token expiry を見分けづらかった。users 一覧 / 詳細 / assignment 操作を通しで見るには、Auth Entry から最初に確認すべき前提を UI 上で明示しておくほうがぶれにくい
- 決定事項: `AppAuthPanel` に live mode の推奨 API Base と「token は約 5 分で期限切れになる」注意を追加し、users 一覧 / 詳細にも live 時だけ短い再確認メッセージを出す。live 切り分けでは backend URL より先に fresh token を入れ直す運用を UI 側でも促す
- 影響範囲: `ap-server/frontend/app/components/AppAuthPanel.vue`、`ap-server/frontend/app/pages/users/index.vue`、`ap-server/frontend/app/pages/users/[keycloakSub].vue`、今後の live mode 手動確認手順
- 次の推奨アクション: 次は frontend 画面上で Auth Entry から fresh token を適用し、users 一覧 / 詳細 / assignment 追加削除を通しで操作して、文言と再取得タイミングが実際の導線に合っているかを確認する

### live mode の手動確認は `user.manage` と再取得ボタンを auth 入口で先に見る

- 背景: 実際の users live 検証では、token を貼った直後に `/users` へ進むより、`Current User` と `authorization.permissions` を先に見たほうが切り分けが速い。特に expiry が近い token では、token 自体を変えずに再取得だけしたい場面もあった
- 決定事項: `AppAuthPanel` に `user.manage` の有無を示す補助文、live 検証の簡易チェックリスト、token を変えずに `GET /me` / `/me/authorization` を再取得する `Refresh Only` ボタンを追加する。users 一覧 / 詳細にも正常系の目印を短く追記する
- 影響範囲: `ap-server/frontend/app/components/AppAuthPanel.vue`、`ap-server/frontend/app/pages/users/index.vue`、`ap-server/frontend/app/pages/users/[keycloakSub].vue`、live mode の手動確認フロー
- 次の推奨アクション: 次は frontend 画面上で `alice` の fresh token を入れ、`Refresh Only` も使いながら `Current User = Alice A` と `user.manage` を確認したうえで users 一覧 / 詳細 / assignment 追加削除を通しで見る

### AP Frontend は `ap.example.com` を専用入口にする

- 背景: `global.example.com` は `frontend` と BFF のログイン導線につながっており、SSO 完了後も `{"message":"SSO login completed"}` の JSON が返るため、`ap-server/frontend` の dashboard 画面へは入れなかった。`Auth Entry` や users UI の手動確認には、AP Frontend を別 host で明示的に出す必要があった
- 決定事項: `ap-server/frontend` は `ap-frontend` コンテナで `npm run dev -- --host 0.0.0.0 --port 3000` を自動起動し、nginx では `https://ap.example.com` を `ap-frontend:3000` へ proxy する。以後、`global.example.com` は Global Login Portal、`ap.example.com` は AP Frontend と呼び分ける
- 影響範囲: `docker/ap-frontend/Dockerfile`、`docker/ap-frontend/init-ap-frontend.sh`、`docker-compose.yml`、`nginx/conf.d/default.conf`、手動確認時のアクセス先、今後の会話での URL 呼称
- 次の推奨アクション: 次は `docker compose up -d --build ap-frontend nginx` 後に `https://ap.example.com` を開き、Auth Entry から live mode で users 一覧 / 詳細 / assignment 追加削除を通し確認する

### Keycloak の dev token 取得は `global-login` の direct access grant を一時的に使う

- 背景: `Auth Entry` の live mode は生の Bearer token を要求するが、既存の `global.example.com/login` は BFF セッションを作るだけで access token をそのまま見せない。`global-login` は当初 `directAccessGrantsEnabled: false` だったため、`alice` の token を `curl` で素直に取得できなかった
- 決定事項: ローカル開発では `keycloak/realm-myapp.json` の `global-login` だけ `directAccessGrantsEnabled: true` にし、`grant_type=password` で `alice` の fresh token を取れるようにする。client は `global-login` / `global-secret` のままにして、`ap-backend` の `KEYCLOAK_CLIENT_ID=global-login` 前提と揃える
- 影響範囲: `keycloak/realm-myapp.json`、`ap-server/frontend` の live 手動確認手順、`Auth Entry` に貼る token の取得方法、Keycloak 再作成手順
- 次の推奨アクション: 次は `docker compose up -d --force-recreate keycloak` で realm を再読込し、`alice` の `access_token` を `curl` で取得して `Auth Entry` へ貼り、`Current User = Alice A` と `user.manage` を確認する

### AP Frontend の live mode では `ap.example.com` を backend CORS に含める

- 背景: `ap.example.com` で `Auth Entry` を `Live` に切り替えても、browser から `https://ap-backend-fpm.example.com/api/*` を叩く段階で `Failed to fetch` になった。既存の `CORS_ALLOWED_ORIGINS` は `global.example.com`、`a.example.com`、`b.example.com`、`keycloak.example.com` までしか含まず、AP Frontend 専用 host を許可していなかった
- 決定事項: `ap-server/backend/config/cors.php` を repo に追加し、`CORS_ALLOWED_ORIGINS` を backend の正規 CORS 設定として読む。許可 origin には `https://ap.example.com` も加え、AP Frontend から `ap-backend-fpm.example.com` の API を browser で直接叩けるようにする
- 影響範囲: `ap-server/backend/config/cors.php`、`docker/env/laravel.common.env`、`ap.example.com` からの `Auth Entry` / users live mode 実測、backend 再起動手順
- 次の推奨アクション: 次は `docker compose up -d --force-recreate ap-backend ap-backend-fpm` 後に `Auth Entry` の `Apply & Refresh` を再試行し、`Current User = Alice A` と `user.manage` を確認する

### AP Frontend の users live 確認では追加 / 削除は通り、期限切れ token は `403 Forbidden` で見える

- 背景: `ap.example.com` から `Auth Entry` の live mode を通したあと、実画面で users 一覧 / 詳細 / assignment 操作を手動確認した。ここで UI 上の正常系と、token expiry 時の見え方を次のチャットでも前提にできる形で残しておく必要があった
- 決定事項: live 実画面では `Current User = Alice A`、users 一覧表示、詳細遷移、assignment 追加、assignment 削除、成功 message、削除後再取得まで確認済みとする。既存 assignment は追加候補一覧から除外する UI なので、重複追加 `422` は API 直叩きでは確認済みでも、画面上では通常導線から再現しない。Bearer token 期限切れ時は browser console / request で `403 Forbidden` として見える
- 影響範囲: `ap-server/frontend` の live mode 手動確認手順、assignment 追加 UI の期待挙動、token expiry 時の切り分け、今後のエラー文言改善判断
- 次の推奨アクション: 次は期限切れ token 時の `403` を UI 上でも分かりやすくする文言改善か、重複追加 API を通常導線で再現しないことを前提に assignment 候補除外の説明を補う

### live mode の `403` / fetch failure は UI 上で切り分け文言に変換する

- 背景: 実画面確認では、期限切れ token のとき browser console では `403 Forbidden`、証明書や hosts 未整備のときは `Failed to fetch` と見えたが、UI では低レベルな英語エラーがそのまま出て分かりづらかった
- 決定事項: frontend では auth / users の API エラーを共通 helper で整形し、`403` は「fresh token を取り直す」、`401` は「token を再設定する」、`Failed to fetch` は「hosts と証明書を確認する」と案内する。users 一覧 / 詳細のエラー表示も同じ helper を使う
- 影響範囲: `ap-server/frontend/app/utils/apApiError.ts`、`useApAuth.ts`、`app/pages/users/index.vue`、`app/pages/users/[keycloakSub].vue`、live mode のエラー切り分け UX
- 次の推奨アクション: 次は期限切れ token をあえて再現し、Auth Entry と users 画面で新しい案内文が期待どおりに出るかを確認する

### 保留議題: 実運用の 401 / 403 は token 手入力ではなく再認証導線で扱う

- 背景: prototype の live mode では `Auth Entry` に Bearer token を手入力するため、期限切れ token のときも UI は「fresh token を取り直す」と案内している。一方で、実運用の SSO では users 画面から毎回 token を貼り直す運用にはならず、期限切れや未認証を再認証導線へどうつなぐかを別途決める必要がある
- 決定事項: 次チャットで詰める検討事案として、`401 Unauthorized` と `403 Forbidden` を実運用でどう扱うかを保留議題に固定する。比較候補は「silent login / token refresh を試す」「SSO ログインへ戻す」「権限不足画面として留める」の 3 系統とし、このチャットではまだ実装しない
- 影響範囲: `ap-server/frontend` の auth UX、BFF / SSO との連携方針、Auth Entry の今後の役割、users 以外の resource page での未認証 / 権限不足 handling
- 次の推奨アクション: 新しいチャットでは [ap-server/frontend/README.md](/home/wsat/projects/keycloak-multi-app/ap-server/frontend/README.md:180) を起点に、この保留議題の方針を決めてから UI と auth 導線の実装へ進む

### dashboard shell は `vue_example/frontend` の構成に寄せて先に共通化する

- 背景: ログイン後ホーム、header / sidebar / footer、ページ共通レイアウトを今の段階で揃えておく要望があり、参照元として `~/projects/vue_example/frontend` の dashboard layout を使う前提が増えた。users 管理だけ単独ページで育てるより、共通 shell を先に置いたほうが resource 追加時の見通しが良かった
- 決定事項: AP frontend では `app/layouts/dashboard.vue` を追加し、header / sidebar / footer を component 分割して共通 shell 化する。sidebar のメニューは `GET /api/me/authorization` で取れる `permissions` と assignment の layer に応じて切り替え、`/` をログイン後ホームとして使う
- 影響範囲: `ap-server/frontend/app/layouts/dashboard.vue`、`app/components/dashboard/*`、`app/utils/dashboard.ts`、`app/pages/index.vue`、暫定 resource pages、今後のメニュー追加判断
- 次の推奨アクション: 次は users 詳細に assignment 追加 / 削除 UI を載せつつ、dashboard shell の page header / toolbar パターンで role / scope 候補フォームをどう収めるかを決める

### Auth Entry では `permission_scopes` を debug 表示し、メニュー判定は `permissions` のまま保つ

- 背景: backend の認可見直しで `GET /api/me/authorization` に permission ごとの `granted_scope_ids` / `accessible_scope_ids` が追加され、frontend でも direct grant と descendant access の違いを確認できるようになった。ただし、これをすぐ menu 表示ロジックへ混ぜると、既存の dashboard shell が必要以上に複雑になりやすかった
- 決定事項: `AppAuthPanel` に `permission_scopes` の補助表示を追加し、permission ごとの direct grant と accessible scope を debug 用に確認できるようにする。一方で sidebar や home のメニュー切り替えは引き続き `authorization.permissions` を一次判定に使い、`permission_scopes` は認可根拠の可視化に限定する
- 影響範囲: `ap-server/frontend/app/composables/useApAuth.ts`、`ap-server/frontend/app/components/AppAuthPanel.vue`、`GET /api/me/authorization` の frontend 利用方針、dashboard shell の今後の認可表示拡張
- 次の推奨アクション: 次に frontend の認可表示を広げるなら、users 一覧や詳細で現在選択中 scope に対して `user.manage` が direct grant なのか descendant access なのかを表示するかを検討し、必要になった時だけ `permission_scopes` の利用箇所を増やす

### users 一覧と詳細では選択中 scope に対する `user.manage` の根拠だけを補助表示する

- 背景: `permission_scopes` を Auth Entry だけで見せても、実際の users 操作と結び付けて見ないと「この tenant を触れるのが直付与なのか、上位からの継承なのか」が分かりづらかった。一方で、一覧や詳細で全 permission を並べ始めると画面が重くなるため、まずは users 導線で重要な `user.manage` だけに絞るのが妥当だった
- 決定事項: users 一覧では drill-down で選択中の `activeScope`、users 詳細では assignment フォームの `selectedAssignmentScope` に対して、`user.manage` が `direct grant` / `descendant access` / `権限なし` のどれかを補助表示する。表示判定は `permission_scopes` を使うが、一覧取得やメニュー切り替えの本判定自体は変えない
- 影響範囲: `ap-server/frontend/app/pages/users/index.vue`、`ap-server/frontend/app/pages/users/[keycloakSub].vue`、`ap-server/frontend/app/utils/permissionScopes.ts`、mock/live 両モードでの users 認可表示、今後の scope ごとの権限根拠説明
- 次の推奨アクション: 次に進めるなら、users 一覧から詳細へ遷移した後も同じ scope 文脈で認可表示が自然に読めるかを live mode で確認し、必要なら assignment 削除確認 modal や role summary にも同じ access 表示を広げる

### users 詳細の role summary と削除確認 modal でも同じ `user.manage` access 表示を使う

- 背景: 選択中 scope に対する `user.manage` の根拠を一覧や assignment フォームで見せられるようになっても、実際に操作直前で見る `Role Summary` と `Remove Assignment` modal に同じ情報が無いと、画面の場所によって説明が揺れて見えやすかった
- 決定事項: users 詳細では `Role Summary` に「この scope へ付与する操作の根拠」を、assignment 削除確認 modal に「この削除操作の根拠」を、それぞれ `direct grant` / `descendant access` / `権限なし` の same helper で表示する。users 詳細内の `user.manage` 根拠表示は同じ `permissionScopes` helper に統一する
- 影響範囲: `ap-server/frontend/app/pages/users/[keycloakSub].vue`、assignment 追加/削除前の認可根拠表示、live mode 手動確認時に見るべき UI 要素、今後の modal / summary パターン再利用
- 次の推奨アクション: 次は live mode で users 一覧から詳細へ入り、scope 切り替え時に一覧バッジ、assignment フォーム、role summary、削除確認 modal の `user.manage` 表示が同じ文脈で読めるかを通しで確認する

### live token で確認した scope 文脈では `Service Alpha` が direct、`Tenant A` が descendant と読める

- 背景: users 一覧、assignment フォーム、role summary、削除確認 modal に `user.manage` の根拠表示を増やしたあと、実データでも同じ文脈で読めるかを確認する必要があった。この環境では browser UI の自動操作手段が無かったため、同じ `alice` token と endpoint を CLI で辿って live API の事実関係を先に固めた
- 決定事項: `alice` の fresh token で `GET /api/me/authorization` を確認すると、`user.manage` は `granted_scope_ids = [1, 2]`、`accessible_scope_ids = [1, 2, 3]` だった。つまり `AP Root` と `Service Alpha` は direct grant、`Tenant A` は descendant access と読める。さらに `GET /api/users?scope_id=2` では `tenant-user-a`、`GET /api/users?scope_id=3` では `tenant-user-b`、`GET /api/users/tenant-user-b` では `Tenant A / tenant_viewer` が返り、`GET /api/roles?scope_layer=tenant` でも tenant role 候補が揃っていたため、users 詳細で表示している access 文脈は live API と整合していると扱う
- 影響範囲: `ap-server/frontend` の users 一覧 / 詳細の認可根拠表示、live mode 手動確認時の期待値、`Auth Entry` の `permission_scopes` debug 表示との整合、今後の token expiry 確認作業
- 次の推奨アクション: 次に進めるなら、期限切れ token をあえて使って `GET /api/me/authorization` と `GET /api/users*` を失敗させ、Auth Entry / users 一覧 / users 詳細で `403` の案内文が期待どおりに読めるかを確認する

### 期限切れ token の live 実挙動では Auth Entry は `null`、users 系は `403` になる

- 背景: token expiry 時の案内文を UI で確認しようとしたところ、実際の backend 応答は endpoint によって異なっていた。README では「Auth Entry / users 画面で `403` を見る」前提になっていたが、live API を失効済み token で叩くとそのままではズレがあった
- 決定事項: 失効済み token で `GET /api/me` と `GET /api/me/authorization` を叩くと、どちらも `200` で `current_user: null` / `authorization: null` を返す。一方、`GET /api/users*` は `403 Forbidden` を返す。これに合わせて `AppAuthPanel` では「Auth Entry 上は `403` ではなく `null` として見えることがある」案内を追加し、users 一覧 / 詳細の `403` 文言とは切り分けて扱う
- 影響範囲: `ap-server/frontend/app/components/AppAuthPanel.vue`、期限切れ token 時の live mode UX、Auth Entry と users 画面のエラー理解、今後の再認証導線検討
- 次の推奨アクション: 次に進めるなら、実運用向けの auth UX を詰める保留議題へ戻り、`401/403/null current_user` を silent login / token refresh / SSO へ戻す導線のどれで吸収するかを決める

### 暫定 auth UX では Auth Entry を再認証ハブに統一する

- 背景: live mode の auth 失敗は `401` / `403` / `current_user: null` に分かれて見えるため、users 一覧や詳細で個別に説明すると「どこへ戻れば復旧できるか」が画面ごとにぶれやすかった。一方、この段階では silent login や refresh token、SSO redirect の本実装はまだ無く、既存 UI で確実に吸収できる導線を先に揃える必要があった
- 決定事項: 当面は `AppAuthPanel` を再認証ハブとして扱い、live mode で Bearer token 未設定・`current_user: null`・API error のいずれでも「まず Auth Entry で fresh token を更新してから users 画面へ戻る」導線を共通表示する。silent login / token refresh / SSO redirect の選定は保留し、users 一覧 / 詳細には `/#auth-entry` へ戻す CTA を置く
- 影響範囲: `ap-server/frontend/app/composables/useApAuth.ts`、`ap-server/frontend/app/components/AppAuthPanel.vue`、`ap-server/frontend/app/pages/users/index.vue`、`ap-server/frontend/app/pages/users/[keycloakSub].vue`、live mode の再認証オペレーション、今後の本実装 auth 導線差し替え
- 次の推奨アクション: 次は Keycloak / Nuxt 間で silent login、refresh token、SSO redirect のどれを正式採用するかを決め、暫定の Auth Entry 導線を本番向け session recovery に置き換える

### ap-frontend 検証コンテナは Node 22 系を基準にする

- 背景: `ap-frontend` コンテナ内で `npm run lint` を回したところ、ESLint 10 系が依存経由で使う `Object.groupBy` を Node 20.20.2 が持たず、frontend の差分確認前に `TypeError: Object.groupBy is not a function` で停止した。検証を継続的に回すには、repo 側で container runtime を先に揃える必要があった
- 決定事項: `docker/ap-frontend/Dockerfile` の base image を `node:22-alpine` に上げ、`ap-server/frontend` の lint / typecheck は Node 22 系の `ap-frontend` コンテナを基準に実行する
- 影響範囲: `docker/ap-frontend/Dockerfile`、`docker compose up -d --build ap-frontend` 後の frontend 検証手順、今後の ESLint / Nuxt 更新時の runtime 前提
- 次の推奨アクション: 次は `ap-frontend` を rebuild して `npm run lint` と `npm run typecheck` を回し、Node 22 前提で frontend 検証が安定して通ることを確認する

### 実運用向け session recovery は `global.example.com/login` へ戻す SSO redirect を正式採用する

- 背景: 保留していた比較候補を見直すと、現行の AP Frontend は refresh token を保持せず、AP 専用の silent login callback もまだ持っていない。この状態で silent login や token refresh を frontend 単体へ先行実装すると、BFF / Keycloak との責務分離が崩れやすかった
- 決定事項: 実運用向け session recovery は `https://global.example.com/login` へ戻す SSO redirect を正式採用する。`Auth Entry` の Bearer token 入力は live debug 専用と位置づけ、users 一覧 / 詳細 / dashboard header の recovery CTA は SSO Login を主導線、`/#auth-entry` は debug 用補助導線として扱う
- 影響範囲: `ap-server/frontend/app/composables/useApAuth.ts`、`ap-server/frontend/app/components/AppAuthPanel.vue`、`ap-server/frontend/app/components/dashboard/DashboardHeader.vue`、`ap-server/frontend/app/pages/users/index.vue`、`ap-server/frontend/app/pages/users/[keycloakSub].vue`、`ap-server/frontend/app/utils/apApiError.ts`、`ap-server/frontend/nuxt.config.ts`、今後の AP 向け BFF 追加判断
- 次の推奨アクション: 次は AP Frontend 自身が SSO 完了後に自然復帰できるよう、`ap.example.com` 側の callback / session bridge をどこへ置くかを決め、`global.example.com/login` から AP へ戻る正式経路を追加する

### frontend の lint warning は早めに auto-fix で解消していく

- 背景: Node 22 化後に `ap-frontend` コンテナで lint を正常実行できるようになった結果、既存の `vue/max-attributes-per-line` と `vue/attributes-order` 由来の warning が大量に見えるようになった。この状態を残すと、今後の実装差分で本当に見たい lint signal が warning 群に埋もれやすい
- 決定事項: `ap-server/frontend` では lint warning を将来まとめて片づけるのではなく、見つかった段階で `eslint --fix` を使って早めに解消する。今回も `ap-frontend` コンテナ内で auto-fix を適用し、template 属性改行や順序の整形を repo の lint ルールへ合わせた
- 影響範囲: `ap-server/frontend/app/components/dashboard/*`、`app/pages/index.vue`、`app/pages/users/*` を含む template formatting、`docker/ap-frontend/Dockerfile` を使った lint 運用、今後の frontend 差分レビューのノイズ量
- 次の推奨アクション: 次は AP Frontend の callback / session bridge を進める実装でも、作業の最後に `docker compose exec ap-frontend npm run lint` と `npm run typecheck` を回し、warning を再び溜めない運用を続ける

### AP Frontend の SSO 復帰は `global login -> /auth/bridge -> /auth/callback` でつなぐ

- 背景: 実運用向け session recovery を `global.example.com/login` に寄せても、そのままでは AP Frontend 側に SSO 完了後の token 受け口が無く、ログイン後に `ap.example.com` へ自然復帰できなかった。tenant BFF と同じ server-side session をそのまま持ち込むより、AP Frontend 専用の Bearer token を browser 側で受け取り直す薄い bridge を置く方が責務分離に合っていた
- 決定事項: Global BFF の `/login` は `return_to` を受け付け、AP Frontend の `SSO Login` は `https://global.example.com/login?return_to=https://ap.example.com/auth/bridge?...` を使う。`/auth/bridge` では Keycloak SSO session を使って `prompt=none + PKCE` の認可コードフローを開始し、`/auth/callback` で `ap-frontend` public client の access token を受け取って `useApAuth()` の Bearer token と live mode へ反映する
- 影響範囲: `laravel-overlay/app/Http/Controllers/GlobalAuthController.php`、`keycloak/realm-myapp.json`、`ap-server/frontend/app/composables/useApSso.ts`、`ap-server/frontend/app/pages/auth/bridge.vue`、`ap-server/frontend/app/pages/auth/callback.vue`、既存の `SSO Login` CTA、Keycloak realm 再読込手順、今後の AP 向け logout / callback 拡張
- 次の推奨アクション: 次は `docker compose up -d --force-recreate keycloak bff-global ap-frontend nginx` で realm と Global BFF を反映し、`https://ap.example.com` の `SSO Login` から users 一覧 / 詳細へ元の path・query を保ったまま自然復帰できるかを live で通し確認する

### live の SSO bridge では users path/query は保たれ、backend 側は `ap-frontend` token audience を許可する必要があった

- 背景: 次の推奨アクションに従って live で `global.example.com/login?return_to=...` を辿ったところ、最初は Global BFF の image を rebuild していなかったため `return_to` が反映されず `a.example.com/auth/silent-login` へ戻っていた。さらに rebuild 後は `ap.example.com/auth/bridge?next=...` と `ap.example.com/auth/callback?...` までは進めたが、bridge 後の `ap-frontend` token を `GET /api/me*` へ流すと `current_user: null` になり、backend 側の audience 前提も揃える必要が分かった
- 決定事項: live 実測では `SSO Login` から `https://ap.example.com/auth/bridge?next=%2Fusers%3Fservice_scope_id%3D2%26tenant_scope_id%3D3%26keyword%3Dalice%26sort%3D-email` へ戻り、bridge HTML 上でも同じ users query を確認できた。加えて backend が `global-login,ap-frontend` の両 client audience を受け入れるようにした後は、bridge で取った token でも `GET /api/me` が `Alice A`、`GET /api/me/authorization` が `user.manage` を含む authorization を返し、AP Frontend 自然復帰の前提が live で通った
- 影響範囲: `ap-server/frontend` の SSO recovery 実測手順、`laravel-overlay/app/Http/Controllers/GlobalAuthController.php` の rebuild 必要性、`ap-server/backend` の accepted client ids 前提、今後の users 一覧 / 詳細の live 手動確認
- 次の推奨アクション: 次は実ブラウザで `https://ap.example.com/users?service_scope_id=2&tenant_scope_id=3&keyword=alice&sort=-email` から `SSO Login` を押し、callback 後に同じ users 画面と query のまま復帰して `Current User = Alice A` と `user.manage` が見えることを UI で確認する

### browser 実測の入口は `e2e/` の Playwright doctor と SSO spec に寄せる

- 背景: 次の確認作業は「Ubuntu Server へ直接入れた browser 実行環境」で AP Frontend の SSO 自然復帰を通す段階になった。ここで毎回手動で `Node` 版数、`*.example.com` の名前解決、stack 起動待ちを確認すると、Playwright 本体に入る前の前提漏れで止まりやすい
- 決定事項: browser 実測の入口はルート `e2e/README.md` とし、まず `pnpm --dir e2e run doctor` で Node 22 / hosts / URL 疎通をまとめて確認し、その後 `pnpm --dir e2e run wait:stack` と `pnpm --dir e2e run test:sso` で AP Frontend の SSO recovery UI を流す
- 影響範囲: Ubuntu Server 直の browser 実行手順、`ap-server/frontend` の SSO recovery 実ブラウザ確認、次チャット以降の UI 実測開始地点
- 次の推奨アクション: 次は Ubuntu Server 上で `corepack enable` と `pnpm --dir e2e install` / `install:browsers` を済ませ、`doctor -> wait:stack -> test:sso` の順に実行して `Current User = Alice A` と `user.manage` の表示まで実ブラウザで確認する

### Ubuntu Server 初回導入は `bootstrap:ubuntu` を入口にする

- 背景: browser 実測の前提を毎回手で入れるより、Ubuntu Server 初回導入をスクリプト化した方が派生プロジェクトでも流用しやすく、Node 版数や `pnpm` 有効化の揺れも減らせる
- 決定事項: Ubuntu Server 初回導入の入口は `pnpm --dir e2e run bootstrap:ubuntu` ではなく、Node 未導入でも動けるよう `bash e2e/scripts/bootstrap-ubuntu.sh` でも直接叩ける `e2e/scripts/bootstrap-ubuntu.sh` を正とする。Node 導入後は `doctor -> wait:stack -> test:sso` の順で AP Frontend の SSO 実測へ進む
- 影響範囲: Ubuntu Server 直の browser 環境構築、`e2e/.env` の初期化、今後の Playwright 実測開始手順
- 次の推奨アクション: 次は Ubuntu Server 上で `bash e2e/scripts/bootstrap-ubuntu.sh` を実行し、必要なら `e2e/.env` の認証情報を調整した上で `pnpm --dir e2e run doctor -> wait:stack -> test:sso` を流す

### 実ブラウザの SSO recovery は `PLAYWRIGHT_HOST_MAP` と Playwright 公式コンテナでも通せる

- 背景: Ubuntu Server 実機で `doctor` と `wait:stack` は通ったが、`/etc/hosts` を直接更新できない環境と、`libatk-1.0.so.0` 不足で Ubuntu 直の Chromium が起動できない環境差分が見えた。それでも AP Frontend の SSO recovery UI 実測は止めずに進めたかった
- 決定事項: `e2e` では `PLAYWRIGHT_HOST_MAP` を既定で持ち、`ap.example.com`, `global.example.com`, `keycloak.example.com`, `ap-backend-fpm.example.com` を `127.0.0.1` へ解決できるようにした。Ubuntu 直の shared library が足りない時は `docker run --rm --network host --user 1000:1000 -e CI=true -v /home/wsat/projects/keycloak-multi-app:/work -w /work/e2e mcr.microsoft.com/playwright:v1.59.1-noble ...` で `test:sso` を流してよい
- 影響範囲: Ubuntu Server 直の browser 実測、`e2e/playwright.config.ts` の host resolver、`e2e/scripts/doctor.mjs` と `wait-for-stack.mjs` の疎通確認、今後の AP Frontend SSO 回帰テスト
- 次の推奨アクション: 次は Ubuntu Server 側で必要なら OS shared library を root 権限で整え、Ubuntu 直の `pnpm --dir e2e run test:sso` でも同じシナリオが通るかを確認する。暫定運用では Playwright 公式コンテナ実行を browser 実測の既定 fallback として扱ってよい

### browser 実測の標準入口は `test:sso:auto` に寄せる

- 背景: Ubuntu Server ごとに Chromium shared library の揺れがあり、毎回「まずローカルを試すか、最初から container へ行くか」を人が判断すると運用がぶれやすい
- 決定事項: `e2e/scripts/run-sso-auto.sh` を追加し、標準入口は `pnpm --dir e2e run test:sso:auto` に寄せる。これはまず Ubuntu 直の `test:sso` を試し、`browserType.launch` や `libatk-1.0.so.0` 由来の library エラー時だけ `test:sso:container` へ fallback する。アプリ側 assertion 失敗では自動 fallback せず、そのままテスト失敗として扱う
- 影響範囲: Ubuntu Server 直の Playwright 実行運用、Playwright 公式コンテナの呼び出し手順、今後の AP Frontend SSO 回帰の入口コマンド
- 次の推奨アクション: 次は Ubuntu Server 上で `pnpm --dir e2e run test:sso:auto` を定常運用コマンドにしつつ、別途 root 権限が取れるタイミングで Chromium 依存 library を導入し、auto fallback せずローカルだけで通る状態へ寄せる

### Ubuntu 直の Chromium 依存 library は installer script でまとめて入れる

- 背景: 実機の `ldd` では `libatk-1.0.so.0`, `libatk-bridge-2.0.so.0`, `libcups.so.2`, `libasound.so.2`, `libgbm.so.1`, `libcairo.so.2`, `libpango-1.0.so.0`, `libXcomposite.so.1`, `libXdamage.so.1`, `libXfixes.so.3`, `libXrandr.so.2`, `libatspi.so.0` が不足していたが、作業時点では `sudo` パスワードが無く即時導入までは進められなかった
- 決定事項: root 権限が取れるタイミングに備えて `e2e/scripts/install-ubuntu-playwright-libs.sh` と `pnpm --dir e2e run install:ubuntu-libs` を追加し、この Ubuntu 24 系サーバで必要だった package 群を 1 回で入れられるようにした
- 影響範囲: Ubuntu 直の Playwright/Chromium 実行、browser 実測の root 作業手順、今後の server セットアップ再現性
- 次の推奨アクション: 次は root 権限が使えるタイミングで `pnpm --dir e2e run install:ubuntu-libs` を実行し、その直後に `pnpm --dir e2e run test:sso` を再実行して container fallback なしで pass するか確認する

### この Ubuntu Server では apt source を `https` に替えたあとローカル `test:sso` まで通った

- 背景: `install:ubuntu-libs` 実行中に `archive.ubuntu.com` / `security.ubuntu.com` への `http` 接続が繰り返し timeout し、package download が進まなかった
- 決定事項: Ubuntu 側の `/etc/apt/sources.list.d/ubuntu.sources` の `URIs` を `http://archive.ubuntu.com/ubuntu/` / `http://security.ubuntu.com/ubuntu/` から `https://...` へ変更したうえで library 導入を進めた。結果として、この実機では Ubuntu 直の `pnpm --dir e2e run test:sso` が pass し、container fallback なしでも AP Frontend の SSO recovery を確認できた
- 影響範囲: Ubuntu Server の apt 運用、`install:ubuntu-libs` 実行前の network troubleshooting、今後の browser 実測の既定手順
- 次の推奨アクション: 次は `test:sso:auto` を日常入口として維持しつつ、別の Ubuntu Server を立てる時も apt source を最初から `https` に寄せるか確認する

### `doctor` でも apt source の `http/https` を先に見る

- 背景: apt source の `http` 問題は `install:ubuntu-libs` 実行まで見えず、browser 実測より前の段で時間を使いやすかった
- 決定事項: `e2e/scripts/doctor.mjs` でも `/etc/apt/sources.list.d/ubuntu.sources` と `/etc/apt/sources.list` を見て、`archive.ubuntu.com` / `security.ubuntu.com` が `http` のままなら warning 相当ではなく失敗として返すようにした
- 影響範囲: 新しい Ubuntu Server での初回 browser セットアップ、`doctor -> install:ubuntu-libs` の順序、apt network troubleshooting の開始地点
- 次の推奨アクション: 次は別の Ubuntu Server でも `pnpm --dir e2e run doctor` を最初に流し、apt source が `http` のままなら `https` へ直してから library 導入に進む

### fresh Ubuntu Server の通し確認は `verify:ubuntu` に寄せる

- 背景: 新しい server では `doctor`, `wait:stack`, `test:sso:auto` を順に打つ必要があり、確認順序が人によってぶれやすかった
- 決定事項: `e2e/scripts/verify-ubuntu-e2e.sh` と `pnpm --dir e2e run verify:ubuntu` を追加し、fresh server の通し確認はこのコマンドを入口にする。中では `doctor -> wait:stack -> test:sso:auto` を順に流す
- 影響範囲: 新しい Ubuntu Server での browser 実測開始手順、初回セットアップ後の smoke test、今後の handoff
- 次の推奨アクション: 次は別の Ubuntu Server で `pnpm --dir e2e run verify:ubuntu` を実行し、`doctor` の apt source 判定から `test:sso:auto` まで同じ導線で通るかを確認する

### apt source の `http` 修正も repo 内 script に寄せる

- 背景: `doctor` が apt source の `http` を検知できるようになっても、修正手順が会話頼みだと別 server で手が止まりやすい
- 決定事項: `e2e/scripts/fix-ubuntu-apt-sources.sh` と `pnpm --dir e2e run fix:ubuntu-apt-sources` を追加し、`/etc/apt/sources.list.d/ubuntu.sources` の `archive/security` URI を backup つきで `https` へ置き換えられるようにした
- 影響範囲: 新しい Ubuntu Server の apt recovery 手順、`doctor` 後の修正導線、browser 実測の再現性
- 次の推奨アクション: 次は別の Ubuntu Server で `doctor` が apt source `http` を検知した時に `pnpm --dir e2e run fix:ubuntu-apt-sources` を実行し、その後 `verify:ubuntu` が通るかを確認する

### apt source `http` の検知から修正までは `recover:ubuntu` に寄せる

- 背景: `doctor` が apt source `http` を検知しても、その後に `fix -> doctor -> verify` を人手でつなぐ必要があり、fresh server で少し煩雑だった
- 決定事項: `e2e/scripts/recover-ubuntu-e2e.sh` と `pnpm --dir e2e run recover:ubuntu` を追加し、`doctor` が `apt:ubuntu-sources` で落ちた時だけ `fix:ubuntu-apt-sources -> doctor -> verify:ubuntu` を自動でつなぐようにした。他の failure では自動 recovery せず、そのまま停止する
- 影響範囲: 新しい Ubuntu Server の初回 recovery 導線、`doctor` failure 後の運用、browser 実測の再現性
- 次の推奨アクション: 次は別の Ubuntu Server で apt source が `http` の状態を作り、`pnpm --dir e2e run recover:ubuntu` が `fix -> verify` まで同じ導線で通るかを確認する

### recovery 分岐の自己検証は temp fixture で回せるようにする

- 背景: 実際の別 Ubuntu Server が無い状態だと、`recover:ubuntu` の `apt:ubuntu-sources` 分岐そのものを手元で検証しづらかった
- 決定事項: `doctor` には `E2E_UBUNTU_SOURCE_FILES`、`fix-ubuntu-apt-sources` には `E2E_UBUNTU_SOURCE_TARGET` の override 口を追加し、`e2e/scripts/selfcheck-recover-ubuntu.sh` と `pnpm --dir e2e run selfcheck:recover-ubuntu` で temp の `ubuntu.sources` fixture を `http` から `https` へ直せるかを自己検証できるようにした
- 影響範囲: recovery 分岐のローカル検証、別 server 実機が無い段階での回帰確認、`doctor/fix/recover` のテスト容易性
- 次の推奨アクション: 次は `pnpm --dir e2e run selfcheck:recover-ubuntu` を実行し、temp fixture 上で `recover:ubuntu` の修正分岐が通ることを確認する

### 別 server 実機の切り分けは `report:ubuntu` を先に採る

- 背景: 実機で `recover:ubuntu` や `verify:ubuntu` が詰まった時、会話ごとに `uname`, apt source, `doctor`, `ldd`, `wait:stack` を個別に聞くのは往復が増えやすかった
- 決定事項: `e2e/scripts/report-ubuntu-e2e.sh` と `pnpm --dir e2e run report:ubuntu` を追加し、別 server 実機ではまずこの出力を採る前提にする
- 影響範囲: 別 Ubuntu Server 実機の切り分け開始手順、サポート時の共有情報、今後の handoff
- 次の推奨アクション: 次は別の Ubuntu Server 実機で `pnpm --dir e2e run report:ubuntu` を実行し、その出力を起点に `recover:ubuntu` か `verify:ubuntu` へ進む

### 別 server 実機の最初の入口は `triage:ubuntu` に寄せる

- 背景: 実機に入った直後は `report` を採るべきか、そのまま `verify` へ進むべきか、apt source 修正が要るのかを人が判断していた
- 決定事項: `e2e/scripts/triage-ubuntu-e2e.sh` と `pnpm --dir e2e run triage:ubuntu` を追加し、最初に `report:ubuntu` を採ったあと `doctor` の結果に応じて `verify:ubuntu` か `recover:ubuntu` へ自動で進む入口に寄せる
- 影響範囲: 別 Ubuntu Server 実機の初手コマンド、診断情報の採取順序、今後の handoff
- 次の推奨アクション: 次は別の Ubuntu Server 実機で `pnpm --dir e2e run triage:ubuntu` を実行し、そのまま `verify` か `recover` へつながるかを確認する

### browser 実行環境整備はいったん区切り、AP 側の session lifecycle へ戻る

- 背景: browser 実行環境の整備を続けるうちに、本題の AP Frontend 実装よりも「別 Ubuntu Server 実機での運用確認」に作業が寄り始めていた。一方、この Ubuntu Server ではすでに `pnpm --dir e2e run test:sso:auto` とローカル `pnpm --dir e2e run test:sso` が通っており、SSO recovery の実測基盤としては十分な到達点に達していた
- 決定事項: browser 実行環境まわりは現時点で一区切りにし、別 server 実機向けの `triage / recover / report` は将来の運用確認用として残す。本題の次工程は AP Frontend / Backend の auth UX と session lifecycle 実装へ戻す
- 影響範囲: `e2e` の今後の優先度、次チャット以降の実装対象、AP Frontend の auth handoff
- 次の推奨アクション: 次は AP Frontend で `SSO Login` の対になる logout / session reset 導線を揃え、bridge で取得した token を明示的に手放せるようにする

### AP Frontend の session 終了は `SSO Logout` と local `Reset Session` を分けて扱う

- 背景: `global login -> /auth/bridge -> /auth/callback` の復帰導線は通ったが、AP Frontend 側には `SSO Login` の対になる終了導線が薄く、live mode token を localStorage に残したままになりやすかった。debug 時の token クリアと、実運用の global SSO logout は目的が違うため、同じボタンに寄せると意味が曖昧になりやすい
- 決定事項: AP Frontend では session 終了を 2 系統に分ける。実運用向けには `global.example.com/logout?return_to=https://ap.example.com/?logged_out=1#auth-entry` を使う `SSO Logout` を用意し、押下前に AP Frontend 側の local token / current_user / authorization も clear する。debug 用には redirect を伴わない `Reset Session` を残し、token override と local auth state だけを外せるようにする
- 影響範囲: `laravel-overlay/app/Http/Controllers/GlobalAuthController.php`、`ap-server/frontend/app/composables/useApSso.ts`、`ap-server/frontend/app/composables/useApAuth.ts`、`ap-server/frontend/app/components/AppAuthPanel.vue`、`ap-server/frontend/app/components/dashboard/DashboardHeader.vue`、今後の logout 完了後 UX
- 次の推奨アクション: 次は `SSO Logout` を実ブラウザで押して `logged_out=1#auth-entry` へ戻り、Auth Entry に logout 完了案内と再ログイン導線が期待どおり出るかを確認する

### `SSO Logout` は global BFF の logout callback を挟むと `logged_out=1#auth-entry` まで戻せる

- 背景: `SSO Logout` を browser 実測したところ、最初は Keycloak logout 後に `https://global.example.com/` へ戻ってしまい、AP Frontend の `logged_out=1#auth-entry` へは復帰できなかった。原因は `global-login` client の post logout redirect が `global.example.com/*` 前提で、AP へ直接返すと吸収されていたことだった
- 決定事項: `global.example.com/logout` は Keycloak へ直接 AP URL を渡さず、いったん `global.example.com/logout/callback?return_to=...` を post logout redirect に使う。callback から `ap.example.com/?logged_out=1#auth-entry` へ server-side redirect し、Playwright 実測でも `SSO Logout -> Logout Complete -> Bearer Token: missing` まで確認できた
- 影響範囲: `laravel-overlay/routes/web.php`、`laravel-overlay/app/Http/Controllers/GlobalAuthController.php`、`e2e/tests/ap-frontend-sso-recovery.spec.ts`、`e2e/README.md`、AP Frontend の logout 完了後 UX、今後の logout 回帰確認
- 次の推奨アクション: 次は logout 後の dashboard home だけでなく、users 一覧や users 詳細からでも同じ `SSO Logout` 導線が自然に見つかるかを確認し、必要なら users 画面の recovery 文言にも logout 後の戻り先を補足する

### users 一覧 / 詳細の recovery 文言にも logout 後の戻り先を補足する

- 背景: `SSO Logout` 自体は dashboard header menu に置いてあったが、users 一覧や users 詳細で再認証案内だけを読むと「logout した時はどこへ戻るのか」が分かりにくかった。session をいったん閉じてから切り分け直す導線は users 画面上でも説明が揃っている方が迷いにくい
- 決定事項: users 一覧 / 詳細の `Re-auth Flow` に「`SSO Logout` は右上のユーザーメニューにあり、実行後は `Auth Entry` へ戻る」案内を追加した。Playwright 実測でも users 一覧で `SSO Logout` menu item が見つかり、そのまま users 詳細へ進んだ後も同じ menu item が見つかることを確認してから logout を実行している
- 影響範囲: `ap-server/frontend/app/pages/users/index.vue`、`ap-server/frontend/app/pages/users/[keycloakSub].vue`、`e2e/tests/ap-frontend-sso-recovery.spec.ts`、users 画面の auth recovery 文言、今後の logout discoverability 確認
- 次の推奨アクション: 次は users 詳細で logout 後に戻った Auth Entry から再ログインし、元の users 文脈へ戻り直す導線まで 1 本の browser シナリオとして確認する

### logout 後の Auth Entry では直前の users 文脈を覚えて再ログインへ戻す

- 背景: `SSO Logout -> /?logged_out=1#auth-entry` までは通っていたが、その状態の `SSO Login` は `route.fullPath` をそのまま使うため、再ログイン後の戻り先が `/` に落ちていた。今回の本命は users 一覧 / 詳細から logout したあと、同じ users 文脈へ戻り直せることだった
- 決定事項: `SSO Logout` 実行前に AP Frontend 側で現在の route を localStorage へ退避し、`logged_out=1` で表示された Auth Entry 上の `SSO Login` だけがその退避先を優先して `global.example.com/login -> /auth/bridge -> /auth/callback` の `next` に使う。再ログイン成功時には退避済み path を消し、通常時の `SSO Login` は引き続き現在画面の route を使う
- 影響範囲: `ap-server/frontend/app/composables/useApSso.ts`、`ap-server/frontend/app/components/AppAuthPanel.vue`、`ap-server/frontend/app/components/dashboard/DashboardHeader.vue`、`e2e/tests/ap-frontend-sso-recovery.spec.ts`、logout 後の再ログイン UX、今後の users 文脈復帰確認
- 次の推奨アクション: 次は users 詳細だけでなく、keyword 付き users 一覧や assignment 操作直前の detail state でも「logout 後の再ログインが同じ route/query に戻るか」を広げて確認する

### users 詳細の「一覧へ戻る」は route object で query を引き継ぐ

- 背景: logout 後に users 詳細へ戻り直す browser シナリオを詰める中で、詳細 URL 自体は `service_scope_id` / `tenant_scope_id` / `sort` 付きで復元できていた一方、「一覧へ戻る」だけが `/users` に落ちていた。`UButton` に `to` と `query` を別 prop で渡しても、実 navigation では query が引き継がれていなかった
- 決定事項: users 詳細の back link は `to="/users"` + `query` 別指定ではなく、`{ path: '/users', query: backQuery }` の route object で渡す。これにより users 詳細から一覧へ戻る時も drill-down query を維持する
- 影響範囲: `ap-server/frontend/app/pages/users/[keycloakSub].vue`、`e2e/tests/ap-frontend-sso-recovery.spec.ts`、users 詳細から一覧へ戻る導線、logout/re-login 後の文脈復帰確認
- 次の推奨アクション: 次は users 詳細で assignment 操作後にも同じ back link が query を保つかを browser で追加確認する
