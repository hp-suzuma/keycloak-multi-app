# keycloak-multi-app

このリポジトリは、`新しい AP サーバーのプロトタイプ開発` を主目的にした作業土台です。  
Keycloak を使った認証環境は維持しますが、優先順位は `認証の作り込み` より `AP サーバーの画面・API・業務ロジックを前に進めること` に置きます。

## このプロジェクトの進め方

採用する方針は `ハイブリッド方式` です。

- 普段のアプリ開発は簡易ログインで進める
- 認証済みユーザーの受け取り口は最初から本番寄せで設計する
- 節目ごとに Keycloak 経由の通し疎通で確認する

この方針の狙いは次の 2 つです。

- 認証待ちでプロトタイプ開発の速度を落とさない
- 後から本来の認証形式へ載せ替えるコストを最小化する

## 現在の検証環境

このリポジトリには、認証疎通確認用の最小構成がすでにあります。

- `https://global.example.com` : Nuxt の入口画面 + Global BFF
- `https://a.example.com` : Tenant BFF A
- `https://b.example.com` : Tenant BFF B
- `https://keycloak.example.com` : Keycloak
- `https://pgadmin.example.com` : pgAdmin
- `https://ap.example.com` : AP Frontend
- `https://ap-backend-fpm.example.com` : AP Backend API

サービス構成は [docker-compose.yml](/home/wsat/projects/keycloak-multi-app/docker-compose.yml) を参照してください。

## 開発環境の基本方針

今後の実開発や派生プロジェクト開発を見据えて、このリポジトリでは `Ubuntu 直の開発基盤 + Docker のアプリ実行基盤` を基本方針にします。

- Ubuntu 直に置くもの
  `git`, `Node 22`, `corepack`, `pnpm`, `Playwright`, `rg` などの開発ツール
- Docker に残すもの
  `PHP`, `Composer`, `Laravel`, `PostgreSQL`, `Keycloak`, `nginx` などのプロジェクト実行系

この分け方により、ブラウザ自動確認や派生プロジェクトの開発速度は保ちつつ、壊れやすいランタイム依存はコンテナ側へ閉じ込められます。

### Ubuntu Server 側の推奨 Browser 実行環境

Browser 実行環境は `Playwright + Chromium` を第一候補にし、Ubuntu Server へ直接入れます。

- Node は `.nvmrc` の `22` 系を基準にする
- package manager は `pnpm` を推奨する
- Browser E2E は [e2e/README.md](/home/wsat/projects/keycloak-multi-app/e2e/README.md) を起点にする

最小セットアップ例:

```bash
cd /home/wsat/projects/keycloak-multi-app
corepack enable
corepack prepare pnpm@latest --activate
pnpm --dir e2e install
pnpm --dir e2e run install:browsers
pnpm --dir e2e run doctor
pnpm --dir e2e run wait:stack
pnpm --dir e2e run test:sso
```

Ubuntu Server に browser 実行環境を初回導入する時は、`pnpm --dir e2e run bootstrap:ubuntu` を入口にする。
このスクリプトが `nvm -> Node 22 -> corepack/pnpm -> Playwright Chromium` までまとめて整える。
`/etc/hosts` を触れないサーバは `PLAYWRIGHT_HOST_MAP` を使い、Ubuntu 直の shared library が足りない時は Playwright 公式コンテナで browser 実測を継続する。
日常運用では `pnpm --dir e2e run test:sso:auto` を SSO browser 実測の標準入口として扱ってよい。
root 権限が取れるタイミングでは `pnpm --dir e2e run install:ubuntu-libs` を実行し、最終的には Ubuntu 直の `pnpm --dir e2e run test:sso` が通る状態へ寄せる。

### `bff-b` の扱い

`bff-b` は現時点では削除しません。

- 既存の multi-app / route assignment 検証資産としてまだ参照価値がある
- nginx / hosts / Keycloak の検証構成が `b.example.com` を含む前提で組まれている
- まずは「常時必要な主役サービスではない」と整理する段階で十分

つまり、日常の AP 開発では主役ではありませんが、今は `削除` より `保持したまま必要時だけ見る` 方針にします。

実装上の役割はおおむね次の通りです。

- `frontend/`
  Nuxt 4 の最小入口画面。現在はログインボタン中心です。
- `laravel-overlay/app/Http/Controllers/GlobalAuthController.php`
  Keycloak ログイン開始と、所属先 BFF への振り分けを担当します。
- `laravel-overlay/app/Http/Controllers/TenantAuthController.php`
  tenant 側の silent login、セッション生成、ログアウトを担当します。
- `laravel-overlay/app/Http/Controllers/BackendServerController.php`
  `sub` と AP サーバー URL の対応を返す最小 backend です。
- `keycloak/realm-myapp.json`
  検証用 realm 定義です。

## 前提整理

このリポジトリで今すぐ完成を目指すもの:

- 新しい AP サーバーのプロトタイプ
- 主要画面、主要 API、最低限のデータ構造
- 認証切替に耐えるアプリ構造

このリポジトリで今は作り込みすぎないもの:

- Token Exchange の本実装
- Keycloak の本番レベル運用
- 認証方式の完全固定
- 多 AP の複雑な接続制御

## 決定事項

新しい AP サーバーは次の構成で進めます。

- フロントエンドは `Nuxt 4 + Nuxt UI`
- バックエンドは `Laravel 13`
- FRONTEND と BACKEND は同一サーバー上で稼働する
- 新しい AP サーバーは既存の `frontend/` や `laravel-overlay/` に混ぜず、別フォルダで管理する

現時点では、既存の `frontend/` と `laravel-overlay/` は `認証疎通確認用の既存環境` として維持します。

## 開発方針

### 1. 認証をアプリ本体に埋め込まない

AP サーバー側の画面や API は、`ログイン方式` を直接知らない状態で作ります。  
見るのは「現在のユーザー情報」だけに寄せます。

想定する最小のユーザー情報例:

- `sub`
- `name`
- `email`
- `roles`
- `tenant`

### 2. 認証情報の受け口を 1 箇所に寄せる

今後の AP サーバーでは、認証済みユーザー取得処理を 1 箇所にまとめます。

例:

- Laravel 側なら middleware / service / request helper
- Nuxt 側なら composable / route middleware

業務コードから直接 `session` や `token` を触る箇所を増やさないのが大事です。

### 3. 開発中は簡易ログインを使う

日々の画面開発・API 開発では、簡易ログインで固定ユーザーを入れて進めます。  
ただし、簡易ログインは本番認証の代用品であって、本体の業務処理に混ぜ込まないようにします。

簡易ログインの条件:

- 開発環境限定で有効
- 固定ユーザーをセッション投入できる
- 後で無効化しやすい
- 本番認証のユーザー形式に合わせる

### 4. Keycloak 通し確認を定期的に入れる

区切りのよいタイミングで、既存の SSO 環境に繋いで確認します。

確認のタイミング例:

- AP サーバーの認証ガードを追加したとき
- ユーザー表示や権限制御を追加したとき
- API 認可の入口を追加したとき
- リリース前の結合確認時

## おすすめの実装ルール

AP サーバープロトタイプでは、次を守ると移行が軽くなります。

- `FakeAuth` と `SsoAuth` を差し替えられる構造にする
- コントローラに認証ロジックを書き込まない
- 画面は `現在のユーザー` を参照するだけにする
- API は `現在のユーザー` と `業務データ` を分けて扱う
- 認証方式ごとの差は adapter 層で吸収する

## 実装イメージ

新しい AP サーバーで持ちたい構造のイメージです。

```text
AP Server
├── UI / API
├── CurrentUser 取得層
│   ├── FakeAuthProvider
│   └── SsoAuthProvider
└── Business Logic
```

見え方としては次のイメージです。

1. 開発中は `FakeAuthProvider` が固定ユーザーを返す
2. SSO 確認時は `SsoAuthProvider` に切り替える
3. UI / API / Business Logic は共通の `CurrentUser` を使う

## このリポジトリでの進め方

実際の開発は、次の順で進めるのがおすすめです。

1. AP サーバーで必要な画面と API を決める
2. その AP サーバーで必要な `CurrentUser` の項目を決める
3. 簡易ログインで固定ユーザーを返す仕組みを用意する
4. 画面・API・DB を先に作る
5. 認証ガードの入口を追加する
6. 既存 Keycloak 環境へ接続して通し確認する
7. 簡易ログインを無効化しやすい状態を保つ

## 直近の実装対象

このリポジトリで次に着手するなら、優先順位はこの順です。

1. 新しい AP サーバーのフォルダ構成を決める
2. `Nuxt 4 + Nuxt UI` のフロントエンド雛形を作る
3. `Laravel 13` のバックエンド雛形を作る
4. 開発用の簡易ログインを入れる
5. SSO 連携時のユーザー受け取り口をつなぐ

## 新しい AP サーバーの初期フォルダ構成

現時点では、新しい AP サーバーの作業領域を次のように分離します。

```text
ap-server/
├── frontend/
├── backend/
└── docs/
```

- `frontend/` は `Nuxt 4 + Nuxt UI` の雛形作成先
- `backend/` は `Laravel 13` の雛形作成先
- `docs/` は AP サーバー固有メモの置き場

詳細は [ap-server/README.md](/home/wsat/projects/keycloak-multi-app/ap-server/README.md) を参照してください。

## 次のチャットで始めること

次の新しいチャットでは、`新しい AP サーバーの構築` から開始します。

開始時の前提:

- 既存の `frontend/` と `laravel-overlay/` は認証検証用として残す
- 新しい AP サーバーは別フォルダで新規作成する
- 構成は `Nuxt 4 + Nuxt UI + Laravel 13`
- 開発方式は `ハイブリッド方式`

## 既存認証環境の使い方

現在の認証検証環境は、`定期的な結合確認用` として使います。

### 起動

```bash
docker compose build
docker compose up -d
```

### hosts 設定

Ubuntu ホストの `/etc/hosts` に追加します。

```text
127.0.0.1 global.example.com
127.0.0.1 a.example.com
127.0.0.1 b.example.com
127.0.0.1 keycloak.example.com
127.0.0.1 pgadmin.example.com
```

### SSO 確認

1. `https://global.example.com` を開く
2. `Login` を押す
3. Keycloak でログインする
4. `a.example.com` または `b.example.com` に遷移する
5. tenant 側でログイン済みユーザーが返ることを確認する

### ログアウト確認

1. `https://a.example.com/logout` または `https://b.example.com/logout` を開く
2. Keycloak の logout endpoint に遷移することを確認する
3. `https://global.example.com/` に戻ることを確認する

## 参考情報

現在の SSO サンプルの詳細を残したバックアップは [README.backup-2026-04-13.md](/home/wsat/projects/keycloak-multi-app/README.backup-2026-04-13.md) に保存しています。

主な参照先:

- [docker-compose.yml](/home/wsat/projects/keycloak-multi-app/docker-compose.yml)
- [frontend/pages/index.vue](/home/wsat/projects/keycloak-multi-app/frontend/pages/index.vue)
- [GlobalAuthController.php](/home/wsat/projects/keycloak-multi-app/laravel-overlay/app/Http/Controllers/GlobalAuthController.php)
- [TenantAuthController.php](/home/wsat/projects/keycloak-multi-app/laravel-overlay/app/Http/Controllers/TenantAuthController.php)
- [BackendServerController.php](/home/wsat/projects/keycloak-multi-app/laravel-overlay/app/Http/Controllers/BackendServerController.php)

## 補足

- 現在の `frontend/` は Nuxt 3 です
- 現在の Laravel ベースは 11 系です
- この README は `AP サーバープロトタイプ開発の進め方` を主眼に再構成しています
- 認証基盤の詳細な検証手順は必要に応じてバックアップ README を参照してください
- 新しい AP サーバーはこれとは別系統で `Nuxt 4 + Nuxt UI + Laravel 13` で構築します
