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

## 実行前チェック

Ubuntu Server へ入れた直後は、まず browser 実行前提だけ先に確認します。

```bash
pnpm --dir e2e run doctor
pnpm --dir e2e run wait:stack
```

`doctor` は次をまとめて確認します。

- `Node 22+`
- `*.example.com` の名前解決
- `ap.example.com`, `global.example.com/login`, `keycloak` OIDC discovery の疎通
- Playwright 実行時に使う Keycloak 資格情報の参照元

`wait:stack` は Docker stack の起動直後に使う想定で、必要 URL が応答するまで待ちます。

## 実行

```bash
pnpm --dir e2e test
```

SSO 自然復帰だけを先に見たい時はこれで十分です。

```bash
pnpm --dir e2e run test:sso
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
