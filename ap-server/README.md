# AP Server

このディレクトリは、新しい AP サーバーの実装領域です。

## 方針

- 既存の `frontend/` と `laravel-overlay/` には混ぜない
- フロントエンドは `frontend/` に集約する
- バックエンドは `backend/` に集約する
- 詳細な scaffold は次工程で作成する

## フォルダ構成

```text
ap-server/
├── frontend/
├── backend/
└── docs/
```

## 補足

- `frontend/` は `Nuxt 4 + Nuxt UI` の配置先
- `backend/` は `Laravel 13` の配置先
- `docs/` は AP サーバー固有の設計メモや実装メモの配置先

## フロント作業コンテナ

`ap-server/frontend` は、専用の `ap-frontend` コンテナに入って作業する前提です。

起動:

```bash
docker compose up -d ap-frontend
```

コンテナへ入る:

```bash
docker compose exec ap-frontend sh
```

このコンテナ内で `Nuxt 4 + Nuxt UI` の初期化を進めます。

## バックエンド作業コンテナ

`ap-server/backend` は、専用の `ap-backend` コンテナに入って作業する前提です。

起動:

```bash
docker compose up -d ap-backend
```

コンテナへ入る:

```bash
docker compose exec ap-backend bash
```

このコンテナ内で `Laravel 13` の初期化を進めます。
