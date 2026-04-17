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

## 実行

```bash
pnpm --dir e2e test
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
