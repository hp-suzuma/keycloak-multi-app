# Browser E2E

このディレクトリは、Ubuntu Server へ直接入れた `Node 22 + pnpm + Playwright` で動かすブラウザ確認用です。

## 前提

- PHP / Composer は Docker 側を使う
- Browser 実行だけを Ubuntu 直に置く
- `/etc/hosts` に `*.example.com -> 127.0.0.1` が入っている
- `docker compose up -d keycloak postgres backend bff-global bff-a bff-b frontend ap-frontend ap-backend ap-backend-fpm nginx` 相当が起動済み

## 初回セットアップ

```bash
cd /home/wsat/projects/keycloak-multi-app
corepack enable
corepack prepare pnpm@latest --activate
pnpm --dir e2e install
pnpm --dir e2e run install:browsers
```

Ubuntu Server にこれから browser 実行環境を入れる時は、次でも同じ状態に寄せられます。

```bash
pnpm --dir e2e run bootstrap:ubuntu
```

`bootstrap:ubuntu` は次をまとめて行います。

- `nvm` の導入
- `Node 22` の導入と default 化
- `corepack + pnpm` の有効化
- `e2e` 依存の install
- `Playwright Chromium` と Ubuntu 依存の install
- `e2e/.env.example` からの `.env` 雛形作成

資格情報や URL を明示したい時は [e2e/.env.example](/home/wsat/projects/keycloak-multi-app/e2e/.env.example) を元に `e2e/.env` を調整します。
`sudo` なしで `playwright install --with-deps` が止まるサーバでは、bootstrap は browser 本体だけ入れて続行します。

root 権限が取れるタイミングで Ubuntu 直の Chromium 依存 library を入れる時は、次を使います。

```bash
pnpm --dir e2e run install:ubuntu-libs
```

`doctor` が apt source の `http` を検知した時は、次で `ubuntu.sources` を `https` に寄せられます。

```bash
pnpm --dir e2e run fix:ubuntu-apt-sources
```

## 実行前チェック

Ubuntu Server へ入れた直後は、まず browser 実行前提だけ先に確認します。

```bash
pnpm --dir e2e run doctor
pnpm --dir e2e run wait:stack
```

fresh server の通し確認を 1 コマンドで流したい時は、次を使います。

```bash
pnpm --dir e2e run verify:ubuntu
```

これは `doctor -> wait:stack -> test:sso:auto` を順に実行します。

別 server 実機で詰まった時に共有用の診断情報をまとめて採る時は、次を使います。

```bash
pnpm --dir e2e run report:ubuntu
```

これは `uname/node/pnpm`、apt source、`doctor`、Chromium の不足 library、`wait:stack` を 1 つの出力にまとめます。

`doctor` が apt source の `http` を検知した時に、修正から通し確認までまとめて進めたい時は次を使います。

```bash
pnpm --dir e2e run recover:ubuntu
```

これは `doctor` を先に流し、`apt:ubuntu-sources` 失敗なら `fix:ubuntu-apt-sources -> doctor -> verify:ubuntu` の順に進みます。

別 server がまだ無い段階で recovery 分岐そのものを確認したい時は、次を使います。

```bash
pnpm --dir e2e run selfcheck:recover-ubuntu
```

これは temp の `ubuntu.sources` fixture を `http` で作り、`recover:ubuntu` が `https` へ書き換えて通し確認まで進むかをローカルで検証します。

`doctor` は次をまとめて確認します。

- `Node 22+`
- `*.example.com` の名前解決
- `ap.example.com`, `global.example.com/login`, `keycloak` OIDC discovery の疎通
- Playwright 実行時に使う Keycloak 資格情報の参照元
- `PLAYWRIGHT_HOST_MAP` を使った host mapping の有無
- Ubuntu apt source が `archive.ubuntu.com` / `security.ubuntu.com` を `https` で向いているか

`wait:stack` は Docker stack の起動直後に使う想定で、必要 URL が応答するまで待ちます。

## 実行

```bash
pnpm --dir e2e test
```

SSO 自然復帰だけを先に見たい時はこれで十分です。

```bash
pnpm --dir e2e run test:sso
```

Ubuntu 直の Chromium が shared library 不足で止まるサーバでは、次を標準入口にしてよいです。

```bash
pnpm --dir e2e run test:sso:auto
```

これはまず Ubuntu 直の `test:sso` を試し、`libatk-1.0.so.0` などの browser 起動失敗なら Playwright 公式コンテナへ自動 fallback します。
この Ubuntu Server では shared library 導入後にローカル `pnpm --dir e2e run test:sso` も pass したが、日常運用の入口は引き続き `test:sso:auto` に寄せてよいです。

コンテナ実行だけを明示したい時はこれを使います。

```bash
pnpm --dir e2e run test:sso:container
```

### headless を避けたい時

```bash
pnpm --dir e2e test:headed
```

## 現在のシナリオ

- `tests/ap-frontend-sso-recovery.spec.ts`
  `global.example.com/login -> ap.example.com/auth/bridge -> auth/callback -> /users?...` の自然復帰を確認する

## 補足

- `PLAYWRIGHT_BASE_URL` を変えれば別 host でも流せる
- Keycloak の認証情報は `KEYCLOAK_USERNAME`, `KEYCLOAK_PASSWORD` で上書きできる
- `PLAYWRIGHT_WAIT_TIMEOUT_MS`, `PLAYWRIGHT_WAIT_INTERVAL_MS` で stack 待機時間を調整できる
- `bootstrap:ubuntu` は `curl` と `bash` が入っている Ubuntu Server を前提にしている
- `/etc/hosts` を触れないサーバでは `PLAYWRIGHT_HOST_MAP` で `*.example.com=127.0.0.1` を渡せる
- Ubuntu 直の Chromium が `libatk-1.0.so.0` などの shared library で起動できない時は、`docker run --rm --network host mcr.microsoft.com/playwright:v1.59.1-noble ...` の Playwright 公式コンテナで `test:sso` を流せる
- `test:sso:auto` は library 不足時だけ container fallback し、アプリ側 assertion 失敗では自動再実行しない
- 実機で確認した不足 library は `libatk1.0-0t64`, `libatk-bridge2.0-0t64`, `libcups2t64`, `libasound2t64`, `libgbm1`, `libcairo2`, `libpango-1.0-0`, `libxcomposite1`, `libxdamage1`, `libxfixes3`, `libxrandr2`, `libatspi2.0-0t64`
- apt source が `archive.ubuntu.com` / `security.ubuntu.com` の `http` で詰まる場合は、Ubuntu 側の `ubuntu.sources` を `https` へ変更してから `pnpm --dir e2e run install:ubuntu-libs` を再実行する
- `fix:ubuntu-apt-sources` は `/etc/apt/sources.list.d/ubuntu.sources` を backup したうえで `archive/security` の URI を `https` に置き換える
- `recover:ubuntu` は apt source `http` の修正だけを自動 recovery 対象にし、それ以外の `doctor` failure では停止する
- `selfcheck:recover-ubuntu` は `/etc` を触らず temp fixture だけで recovery 分岐を検証する
- `report:ubuntu` は別 server 実機で詰まった時に、そのまま貼り返せる診断出力をまとめる
