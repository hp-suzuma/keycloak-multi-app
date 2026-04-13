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
