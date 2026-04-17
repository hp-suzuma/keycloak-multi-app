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
