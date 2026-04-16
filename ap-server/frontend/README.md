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
NUXT_PUBLIC_AP_API_BASE=http://localhost:8000/api
NUXT_PUBLIC_AP_API_BEARER_TOKEN=<user.manage を持つ Bearer token>
```

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

### dashboard shell は `vue_example/frontend` の構成に寄せて先に共通化する

- 背景: ログイン後ホーム、header / sidebar / footer、ページ共通レイアウトを今の段階で揃えておく要望があり、参照元として `~/projects/vue_example/frontend` の dashboard layout を使う前提が増えた。users 管理だけ単独ページで育てるより、共通 shell を先に置いたほうが resource 追加時の見通しが良かった
- 決定事項: AP frontend では `app/layouts/dashboard.vue` を追加し、header / sidebar / footer を component 分割して共通 shell 化する。sidebar のメニューは `GET /api/me/authorization` で取れる `permissions` と assignment の layer に応じて切り替え、`/` をログイン後ホームとして使う
- 影響範囲: `ap-server/frontend/app/layouts/dashboard.vue`、`app/components/dashboard/*`、`app/utils/dashboard.ts`、`app/pages/index.vue`、暫定 resource pages、今後のメニュー追加判断
- 次の推奨アクション: 次は users 詳細に assignment 追加 / 削除 UI を載せつつ、dashboard shell の page header / toolbar パターンで role / scope 候補フォームをどう収めるかを決める
