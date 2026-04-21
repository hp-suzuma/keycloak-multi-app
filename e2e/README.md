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

別 server 実機で最初に何を打つか迷わないよう、report 付きの入口としては次を使います。

```bash
pnpm --dir e2e run triage:ubuntu
```

これは最初に `report:ubuntu` で共有用情報を採り、その後 `doctor` の結果に応じて `verify:ubuntu` か `recover:ubuntu` へ進みます。

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

`2026-04-20` 時点では、この selfcheck を実行して `recover:ubuntu -> verify:ubuntu -> test:sso:auto` まで完走し、最後の `ap-frontend-sso-recovery.spec.ts` も `6 passed` を確認しました。

### Ubuntu 実機 recovery は保留し、次は browser 回帰の横展開へ進む

- 背景: `selfcheck:recover-ubuntu` により temp fixture 上の `recover:ubuntu` 分岐はローカルで閉じられたが、別 Ubuntu Server 実機がまだ用意できていないため、`triage:ubuntu` の real `/etc/apt` / shared library 検証は次チャット以降へ持ち越すことになった。いまは SSO recovery の container 回帰基盤と users 文脈の spec が安定しているので、同じ E2E 方針を次の AP Frontend 画面へ広げる方が優先度に合う
- 決定事項: Ubuntu recovery の実機確認は保留とし、次チャットのテーマは「SSO recovery 以外の browser 回帰へ拡張」に切り替える。最初の対象候補は `objects` / `policies` で、既存方針どおり live stack・Playwright 公式コンテナ・文言全文を固定しすぎない assertion を前提に spec 設計を始める
- 影響範囲: `triage:ubuntu` の再開条件、今後の `e2e/tests` 拡張方針、objects / policies 実装時の回帰設計
- 次の推奨アクション: 次チャットでは `ap-server/frontend` の `objects` / `policies` 画面の現状を棚卸しし、users 系と同じ spec に広げるか、新しい browser spec を切るかを決めてから実装へ入る

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
  `global.example.com/login -> ap.example.com/auth/bridge -> auth/callback -> /users?...` の自然復帰、users 一覧 / 詳細の header menu から `SSO Logout` を見つけられること、`SSO Logout -> global.example.com/logout/callback -> /?logged_out=1#auth-entry` の復帰、さらに Auth Entry の `SSO Login` から元の users 詳細 route/query へ戻り直せることを確認する

### users 詳細から logout したあとも Auth Entry の `SSO Login` で同じ文脈へ戻す

- 背景: 直近の browser 実測で `SSO Logout -> Auth Entry` までは確認できていたが、その後の `SSO Login` は root へ戻るだけで、users 詳細の route/query を復元できていなかった。users 管理の本命シナリオとしては、session を閉じたあとでも元の文脈へ戻れることを 1 本で見たい段階だった
- 決定事項: Playwright の `ap-frontend-sso-recovery` に、users 一覧から `tenant-user-b` 詳細へ入り、`SSO Logout` 後に Auth Entry から再ログインして同じ `/users/tenant-user-b?service_scope_id=2&tenant_scope_id=3&sort=-email` へ戻る確認を追加した。再ログイン後は Keycloak credential をもう一度入力し、最後は「一覧へ戻る」を実際に押して `/users?service_scope_id=2&tenant_scope_id=3&sort=-email` へ戻るところまで確認する
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、`ap-server/frontend/app/composables/useApSso.ts`、`ap-server/frontend/app/components/AppAuthPanel.vue`、`ap-server/frontend/app/components/dashboard/DashboardHeader.vue`、今後の logout/re-login 回帰確認
- 次の推奨アクション: 次は users 一覧の keyword 付き条件や detail 画面の assignment 操作前後でも route/query 復帰が保たれるかを追加し、recovery シナリオの coverage を広げる

### keyword 付き一覧からの assignment 操作後でも同じ users 文脈へ戻す

- 背景: 前段で users 詳細そのものへの復帰は確認できたが、まだ `keyword` 付き一覧と assignment 追加/削除を跨いだ detail state では recovery を見ていなかった。users 管理の実運用では絞り込み一覧から詳細へ入り、その場で assignment を触ってから session を失う流れも十分ありえる
- 決定事項: `ap-frontend-sso-recovery` に、`/users?service_scope_id=2&tenant_scope_id=3&keyword=bob&sort=-email` で `tenant-user-b` 詳細へ入り、`Tenant Operator` を追加してから `SSO Logout -> Auth Entry -> SSO Login` を通しても、`/users/tenant-user-b?service_scope_id=2&tenant_scope_id=3&keyword=bob&sort=-email` へ戻るケースを追加した。復帰後は追加済み assignment を確認して削除し、「一覧へ戻る」で `keyword=bob` 付き一覧へ戻るところまで同じ test で確認する
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、users 一覧の `keyword` query 維持、users 詳細の assignment add/remove UI、logout/re-login 回帰 coverage
- 次の推奨アクション: 次は `test:sso` か `test:sso:auto` を実スタックに流し、live 環境でも `Tenant Operator` の追加/削除を含めて同じシナリオが安定するかを確認する

### `test:sso` は live stack で 4 本とも通過した

- 背景: 追加した recovery ケースは `--list` で検出確認までは済んでいたが、実 stack 上の Keycloak login、Auth Entry 復帰、assignment API まで含めた通し確認が残っていた
- 決定事項: `2026-04-20` に `pnpm --dir e2e test:sso` を実行し、`ap-frontend-sso-recovery.spec.ts` の 4 tests がすべて pass することを確認した。最終ケースの `keyword=bob` 一覧 -> `tenant-user-b` 詳細 -> `Tenant Operator` 追加 -> logout/re-login -> assignment 削除 -> 一覧復帰 も live で通過した
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、live browser 回帰の基準、今後の Ubuntu 実機チェックの成功ライン
- 次の推奨アクション: 次は別 tenant や service-only 文脈へ coverage を広げるか、`test:sso:auto` を Ubuntu 実機でも流して library/container fallback を含む運用確認へ進む

### service-only 文脈は `fixme`、安定回帰セットは `4 passed + 1 skipped`

- 背景: service-only 文脈の coverage を足す過程で、`tenant-user-a` 詳細から logout したあとの Auth Entry が live ではなく `MOCK / Guest` へ落ちるケースや、frontend 編集直後の `/auth/callback` dynamic import 500 に当たるケースが見えた。さらに assignment recovery でも Auth Entry に `SSO Login` が出ない瞬間があり、UI の揺れに test が引っ張られていた
- 決定事項: `ap-frontend-sso-recovery.spec.ts` では service-only recovery を `test.fixme(...)` にして意図を残し、`keyword=bob` の assignment recovery は開始時に `Tenant Operator` の残骸を自動 cleanup する idempotent 化を行った。logout 後に Auth Entry の `SSO Login` が見えない時は、test 側で同じ `next` を持つ global login URL へ直接戻る fallback を使い、最終的に `pnpm --dir e2e test:sso` は `4 passed, 1 skipped` で安定させた
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、live stack 上の browser 回帰運用、service-only recovery の今後の扱い
- 次の推奨アクション: 次は frontend 側で `logged_out=1` 復帰後の live mode / `SSO Login` 表示を安定させ、その後 service-only test を `fixme` から通常 test へ戻す。並行して `test:sso:auto` でも同じ `4 passed + 1 skipped` が再現するか確認するとよい

### `SSO Login` を見せるだけでは detail/service-only の `next` は復元しなかった

- 背景: 次工程として Auth Entry の `logged_out=1` 復帰 UI を直し、`service-only` を通常 test に戻せるかを試した。`AppAuthPanel` 側で `SSO Login` / `Logout Complete` を logout 復帰時にも出し、spec も一度は fallback なしの自然導線へ戻して確認した
- 決定事項: live 実測では `SSO Login` 自体は表示できても、re-login 後の `auth/bridge` はなお `next=/` を握っており、detail / assignment / service-only の 3 ケースとも callback 後に root へ戻った。したがって今回の切り分けで本命は Auth Entry の表示条件ではなく、`logoutReturnNext` を `SSO Login` がどう解決しているかになった。spec は fallback 付き・service-only `fixme` の安定版へ戻した
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、Auth Entry recovery の browser 実測、次の frontend 調査観点
- 次の推奨アクション: 次は `useApSso` の `loginReturnPath()` と `readStoredLogoutReturnNext()` を SSR/hydration 観点で直接調べ、必要なら Auth Entry の `SSO Login` を client-only 解決に寄せるのではなく、logout return path 自体の保持方法を見直す

### hydration 後に `logoutReturnNext` を再評価できる frontend 実装へ寄せた

- 背景: `useApSso` をコードレベルで追った結果、`logged_out=1` の Auth Entry では SSR 中に `readStoredLogoutReturnNext()` が `null` となり、`globalLoginUrl()` が `next=/` で一度固まる可能性が高いと分かった。`localStorage` 直読みは reactive ではないため、hydration 後に保存済み path があっても `SSO Login` の href が更新されないのが本命だった
- 決定事項: frontend 側では `logoutReturnNext` を `useState` に載せ、client setup と logout store/clear 時に同じ state を同期する実装へ変更した。これにより Auth Entry の `SSO Login` は hydration 後に保存済み path を使って再評価される前提になった。spec はこの時点ではまだ fallback 付き・service-only `fixme` のままとし、まず live 実測で効き目を確認してから戻す
- 影響範囲: `ap-server/frontend/app/composables/useApSso.ts`、`e2e/tests/ap-frontend-sso-recovery.spec.ts` の今後の期待値、service-only recovery を通常 test に戻す判断
- 次の推奨アクション: 次は live stack で `pnpm --dir e2e run test:sso` を再実行し、Auth Entry 上の `SSO Login` が detail / assignment / service-only の各ケースで保存済み `next` を使うかを見て、通るなら fallback 除去と `fixme` 解除へ進む

### Playwright コンテナ再実測では logout case だけ `SSO Login` 可視化待ちで落ちた

- 背景: frontend 側の hydration 修正後、README の推奨どおり container 経路で効き目を確認するため `pnpm --dir e2e run test:sso:container` を再実行した。今回は Ubuntu 直ではなく Playwright 公式コンテナを使い、browser 依存差分を減らして見た
- 決定事項: 結果は `3 passed, 1 failed, 1 skipped` だった。`tests/ap-frontend-sso-recovery.spec.ts:117` の users detail recovery と `:137` の keyword + assignment recovery は pass し、`logoutReturnNext` を使う route/query 復帰自体は改善が見えた。一方 `:94` の logout case は `ensureLiveAuthEntry()` で `Live` click 後も `getByRole('link', { name: 'SSO Login' })` が 10 秒以内に現れず fail したため、残課題は `next` 解決よりも Auth Entry の `Live` / recovery UI 表示安定性に寄っている
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、Playwright container 回帰の期待値、次の frontend 調査観点
- 次の推奨アクション: 次は `tests/ap-frontend-sso-recovery.spec.ts:94` の logout case を先に単体再実行しつつ、`AppAuthPanel` と `useApAuth` 側で `logged_out=1` 復帰時の `SSO Login` 表示条件を追う。service-only `fixme` の解除判断はこの logout case が安定してから進める

### `logged_out=1` の Auth Entry は SSR 時点から正しい `SSO Login` href を持たせる

- 背景: logout case を直すため `SSO Login` を先に見せる変更を入れると、今度は `logged_out=1` の初期 HTML が `next=/` を含んだ古い href を描画し、Playwright が hydration 前に click すると通常 login や detail recovery まで root に落ちるケースが出た
- 決定事項: frontend 側では `AppAuthPanel` が `logged_out=1` 時点で `SSO Login` を表示しつつ、`useApSso` の logout return path を localStorage に加えて cookie にも保持する形へ変更した。これにより `globalLoginUrl()` は SSR 初回描画から cookie を使って正しい `next` を含められる。`SSO Logout` は logout 完了画面では非表示にし、再開導線を `SSO Login` に寄せる
- 影響範囲: `ap-server/frontend/app/components/AppAuthPanel.vue`、`ap-server/frontend/app/composables/useApSso.ts`、`tests/ap-frontend-sso-recovery.spec.ts` の logout/re-login 安定性
- 次の推奨アクション: 次は Playwright container で通常 login / logout / detail recovery が `next=/` に戻らないことを再確認し、そのうえで service-only `fixme` の解除可否を判断する

### Playwright コンテナでは `4 passed + 1 skipped` に復帰した

- 背景: `logged_out=1` の表示条件と cookie 保持を両方入れたあとは、container 経路で本当に通常 login と logout/re-login が揃って戻るかを通しで見直す必要があった
- 決定事項: `pnpm --dir e2e run test:sso:container` を再実行した結果、`ap-frontend-sso-recovery.spec.ts` は `4 passed, 1 skipped` で通過した。`tests/ap-frontend-sso-recovery.spec.ts:94` の logout case も pass し、`tests/...:87`, `:117`, `:137` でも `auth/bridge?next=/` への逆戻りは再現しなかった
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、Playwright container を使う今後の回帰確認、service-only `fixme` の次判断
- 次の推奨アクション: 次は service-only case を通常 test に戻して同じ container コマンドで流すか、まず fallback を外した状態でも service-only だけ個別に安定するかを確認する

### service-only case も通常 test に戻して `5 passed` を確認した

- 背景: 直前まで service-only recovery だけは `fixme` に残していたため、`logged_out=1` と `next` 保持の修正が本当に最後の穴まで塞いだかを確認する必要があった
- 決定事項: `tests/ap-frontend-sso-recovery.spec.ts:180` の service-only case を通常 `test(...)` へ戻し、再度 `pnpm --dir e2e run test:sso:container` を実行した。結果は `5 passed` で、`/users?service_scope_id=2&keyword=alice&sort=-email` と `/users/tenant-user-a?...` の両方で logout/re-login 後の query 復帰が通った
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、Playwright container 回帰の成功ライン、service-only 文脈の今後の扱い
- 次の推奨アクション: 次は `resumeSsoLogin()` の fallback 導線を本当に残すかを見直し、Auth Entry の自然な `SSO Login` click だけで安定するなら test helper を簡素化する

### `resumeSsoLogin()` は Auth Entry の自然 click だけに簡素化できた

- 背景: service-only まで `5 passed` に戻ったあとも、helper にはまだ `SSO Login` が見えない時の `page.goto(global login URL)` fallback が残っていた。これを残すと、UI が壊れても test が迂回してしまい、Auth Entry recovery 自体の回帰価値が薄くなる
- 決定事項: `tests/ap-frontend-sso-recovery.spec.ts` の `resumeSsoLogin()` から fallback を削除し、`ensureLiveAuthEntry()` のあと `SSO Login` を click して Keycloak login に進む自然導線だけへ寄せた。その状態でも `pnpm --dir e2e run test:sso:container` は `5 passed` を維持した
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、Auth Entry recovery helper、今後の browser 回帰の厳密さ
- 次の推奨アクション: 次は `ensureLiveAuthEntry()` 自体が本当に必要かを確認し、不要なら logout case でも `SSO Login` を直接待つだけの helper に寄せる

### `ensureLiveAuthEntry()` の `Live` click も不要になった

- 背景: frontend 側で `logged_out=1` の Auth Entry が初回描画から `SSO Login` を出せるようになった以上、test helper がまだ `Live` button を押すのは実装より古い前提を持ち込んでいる状態だった
- 決定事項: `tests/ap-frontend-sso-recovery.spec.ts` の `ensureLiveAuthEntry()` から `Live` click fallback を削除し、`SSO Login` link が visible になることだけを待つ helper へ簡素化した。その状態でも `pnpm --dir e2e run test:sso:container` は `5 passed` を維持した
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、Auth Entry recovery helper、logout 復帰時の期待 UI
- 次の推奨アクション: 次は helper 名を実態に合わせて整理するか、helper 自体をやめて各 test で `SSO Login` を直接待つ形へ寄せる

### helper 名は `waitForSsoLoginLink()` に変更した

- 背景: `ensureLiveAuthEntry()` はもう Auth Entry 全体を整える helper ではなく、`SSO Login` link の可視待ちだけをしていたため、名前と実装のズレが残っていた
- 決定事項: `tests/ap-frontend-sso-recovery.spec.ts` の helper 名を `waitForSsoLoginLink()` に変更し、`resumeSsoLogin()` は返ってきた locator を click する形へそろえた。再度 `pnpm --dir e2e run test:sso:container` を実行しても `5 passed` を維持した
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、helper 命名、今後の test 可読性
- 次の推奨アクション: 次はこの helper を残すか、各 test に直接 `expect(...SSO Login...)` を書いて helper 自体をなくすかを決める

### `waitForSsoLoginLink()` helper も削除して call site へ直接寄せた

- 背景: `waitForSsoLoginLink()` まで薄くすると、helper の中身は `SSO Login` locator の可視待ちだけになり、かえって test の読み筋を 1 段追いかける必要があった
- 決定事項: `tests/ap-frontend-sso-recovery.spec.ts` から `waitForSsoLoginLink()` を削除し、logout case では `expect(page.getByRole('link', { name: 'SSO Login' }))` を直接書く形へ変更した。`resumeSsoLogin()` も同じ locator をその場で待って click する形に寄せた。再度 `pnpm --dir e2e run test:sso:container` を流しても `5 passed` を維持した
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、Auth Entry recovery spec の直読性、helper 削減方針
- 次の推奨アクション: 次は `resumeSsoLogin()` 自体も inline 化してよいかを見直し、各 scenario に recovery 手順を明示する方が読みやすいならさらに平坦化する

### `resumeSsoLogin()` も inline 化した

- 背景: `waitForSsoLoginLink()` まで消した段階で、残る helper のうち `resumeSsoLogin()` も「`SSO Login` を待って押し、Keycloak login を送信する」だけになっていた。これも各 scenario に直接書けるなら、logout/re-login 手順を test 本文からそのまま追える
- 決定事項: `tests/ap-frontend-sso-recovery.spec.ts` から `resumeSsoLogin()` を削除し、detail / keyword assignment / service-only の各 scenario に `SSO Login` wait -> click -> `submitKeycloakLogin()` を直接書いた。最初の container 再実行では logout case の `Logout Complete` 表示が単発で揺れたが、同条件の再実行では `5 passed` に戻ったため、inline 化は維持しつつ logout complete 表示には軽い flake が残るメモを引き継ぐ
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、scenario 本文の可読性、logout case の flake メモ
- 次の推奨アクション: 次は logout case の `Logout Complete` 表示揺れを frontend/E2E どちらで吸収するかを決める

### logout complete の揺れは frontend と E2E の両方で吸収した

- 背景: inline 化後の logout case では、`SSO Login` は見えているのに `Logout Complete` だけ出ない断面や、実装に存在しない文言へ寄った assertion の脆さが残っていた
- 決定事項: frontend 側では `AppAuthPanel` の `Logout Complete` card を `logged_out=1` だけで表示するようにし、mode の一時揺れに引っ張られないようにした。E2E 側では logout case の最後の assertion を `global SSO logout が完了 ... local token もクリア` に合わせた。再度 `pnpm --dir e2e run test:sso:container` を実行した結果は `5 passed` だった
- 影響範囲: `ap-server/frontend/app/components/AppAuthPanel.vue`、`e2e/tests/ap-frontend-sso-recovery.spec.ts`、logout case の安定性
- 次の推奨アクション: 次は login/callback timeout が単発で再現した件をさらに追うか、現時点では `5 passed` を成功ラインとして区切るかを決める

### callback timeout は再現待ち扱いにし、当面は `5 passed` を成功ラインに据える

- 背景: login/callback timeout は一度だけ `auth/callback` で止まる形で見えたが、その後の Playwright container 再実行では `5 passed` が続き、artifact も手元に残らなかった。再現性が薄い段階で spec や callback へ観測コードを増やすと、今の成功ラインを崩す可能性があった
- 決定事項: `2026-04-20` 時点では callback timeout は追加対応なしで保留し、Playwright 公式コンテナの `pnpm --dir e2e run test:sso:container` が `5 passed` で通る状態を成功ラインとして維持する。再発した時だけ `auth/callback` と `useApSso.completeBridgeSession()` の観測追加を検討する
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts` の現状維持、callback flake の今後の扱い
- 次の推奨アクション: 次はこのテーマを一区切りとして別作業へ進むか、callback timeout 再発時の調査用メモを別途足すかを決める

### callback timeout の再発調査は `?sso_debug=1` の browser trace を先に見る

- 背景: callback timeout は browser 上では `auth/callback` 停滞としてしか見えにくく、Playwright artifact が残らない再実行成功ケースでは「Keycloak token exchange と AP API refresh のどちらで詰まったか」が会話ログだけでは引き継ぎにくかった
- 決定事項: 今後 callback timeout が再発した時の最初の切り分けは、通常 spec を増やすより先に `?sso_debug=1` を一度有効化した tab で同じ SSO 導線を踏み、callback error 画面の `Latest Stage` を確認する。より詳しく見る時は browser の `sessionStorage["ap-sso-debug-trace"]` を開く。debug flag は同じ tab の users/detail/logout/re-login にも持ち回るので、trace の `callback:token-exchange:*` と `callback-page:refresh-current-user:*` を見れば timeout 箇所を browser-only で切り分けられる。不要になったら `?sso_debug=0` で解除する
- 影響範囲: AP Frontend callback flake の再調査手順、`e2e/tests/ap-frontend-sso-recovery.spec.ts` をすぐ改変しない判断基準、users/detail 文脈の debug 再現性
- 次の推奨アクション: 次回再発時は callback error 画面の `Latest Stage` か trace の最終 stage を README に追記し、その結果が token exchange 側なら callback 観測強化、refresh 側なら API 待機や auth recovery 文言整理へ進む

### callback stall が再発したので E2E から debug trace を引けるようにした

- 背景: callback summary UI を追加した直後の Playwright 公式コンテナ再実行で、`tests/ap-frontend-sso-recovery.spec.ts:63` の最初の login case だけが再び `auth/callback` 停滞で timeout した。ただし通常回帰では `sso_debug` を付けていないため、artifact だけでは stage を特定しにくかった
- 決定事項: `e2e/tests/ap-frontend-sso-recovery.spec.ts` に `PLAYWRIGHT_SSO_DEBUG=1` を追加で渡せる入口を設け、SSO login URL に `sso_debug=1` を載せられるようにした。さらに login helper の URL wait は、`/auth/callback` 上で timeout した時に `sessionStorage["ap-sso-debug-trace"]` を読み出して error に添える形へ変更した。加えて再発時の入口コマンドを `pnpm --dir e2e run test:sso:debug` に固定し、env を手で前置しなくても trace 付き再実行へ入れるようにした。通常回帰の失敗直後に自動で debug rerun まで進めたい時は `pnpm --dir e2e run test:sso:triage` を使い、triage script 自体が `Callback latest stage` まで要約して `e2e/test-results/callback-triage-summary.txt` にも保存する
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、Playwright container での callback stall 切り分け、今後の一時 debug 実行手順
- 次の推奨アクション: 次回 callback stall が再発したら、まず `pnpm --dir e2e run test:sso:triage` を流して standard failure と debug rerun をまとめて取り、`e2e/test-results/callback-triage-summary.txt` の `Callback latest stage` を README に追記する

### auth recovery 文言整理の回帰は triage standard pass を成功ラインに据える

- 背景: `Auth Entry` と users 画面の recovery 文言を共通 util へ寄せたあと、Playwright 公式コンテナで `pnpm --dir e2e run test:sso:container` を流したところ、最初の login case だけが 60 秒 timeout で落ちた。失敗時の stack trace は `waitForSsoArrival()` から `readCallbackDebugTrace()` を呼んだ瞬間に page close 済みだったことを示しており、前チャットから引き継いでいる callback/login flake の再発に近い断面だった
- 決定事項: この失敗を文言変更の退行と断定せず、README で定めた入口どおり `pnpm --dir e2e run test:sso:triage` を続けて実行した。triage の standard rerun では `tests/ap-frontend-sso-recovery.spec.ts` が `5 passed` となり、debug rerun は不要だったため、今回の auth recovery 文言整理は保持する。現時点の container 回帰は「単発 failure が出ても triage standard rerun が `5 passed` なら既知 flake 扱い」の運用で引き継ぐ
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、`waitForSsoArrival()` の timeout 時挙動、auth recovery 文言変更時の container 検証フロー
- 次の推奨アクション: 次回同じ timeout が再発したら、`Callback latest stage` が取れたかに加えて `readCallbackDebugTrace()` が page close 済みでも落ちずに要約へ進めるよう、helper 側の例外吸収を検討する

### users recovery CTA は wording ではなく役割だけを軽く固定する

- 背景: auth recovery 文言は今後も細かく調整する余地がある一方、users 画面では `SSO Login` と `Auth Entry Debug` の 2 本立てが崩れていないことだけは回帰で拾いたかった。全文一致に寄せると wording 整理のたびに spec が無駄に壊れやすくなる
- 決定事項: `tests/ap-frontend-sso-recovery.spec.ts` に、users 一覧の live + token なし状態を localStorage で作り、`Re-auth Flow` 内に `SSO Login` と `Auth Entry Debug` が見えることだけを確認する軽い assertion を追加した。`Auth Entry Debug` は `/#auth-entry` を向くことまで見て、文言本文は固定しない。users 詳細は token なし初回読み込みで detail API error へ落ちやすいため、今回は recovery banner 自体の固定対象にしない。Playwright 公式コンテナで `pnpm --dir e2e run test:sso:container` を再実行した結果は `6 passed` だった
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、users recovery CTA の回帰基準、今後の wording 調整時の test 保守性
- 次の推奨アクション: 次は callback stall 再発時の failure 要約を壊さないため、`readCallbackDebugTrace()` が page close 済みでも `waitForSsoArrival()` が素直に元の timeout を添えて返せるよう helper を見直す

### callback trace helper は page close 済みでも要約を返す

- 背景: callback/login flake が再発した時、`waitForSsoArrival()` の catch 節で `readCallbackDebugTrace()` 自体が `Target page, context or browser has been closed` を投げると、元の URL wait timeout よりも helper 側の例外が目立ち、triage で見たい情報がかえって読みづらくなっていた
- 決定事項: `tests/ap-frontend-sso-recovery.spec.ts` の `readCallbackDebugTrace()` は page close 済みなら例外ではなく `unavailable: page already closed` を返し、`page.evaluate()` 失敗時も `unavailable: ...` の文字列へ吸収するようにした。`waitForSsoArrival()` も current URL を安全に扱い、callback timeout か page close の断面では `Current URL` と `Callback trace` を添えつつ元の wait error を `cause` に残す。Playwright 公式コンテナで `pnpm --dir e2e run test:sso:container` を再実行した結果は `6 passed` だった
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、callback stall 再発時のエラー可読性、`test:sso:triage` 実行後の failure 読み解き
- 次の推奨アクション: 次は callback stall が実際に再発した時に、新しい helper 要約と `callback-triage-summary.txt` の内容が重複しすぎていないかを見て、必要なら helper 側の文面をさらに圧縮する

### callback helper の wording は triage summary 前提で短く保つ

- 背景: `callback-triage-summary.txt` は `Callback latest stage` や trace 件数を別途要約してくれるため、helper 側まで `SSO debug enabled` などの補助情報を増やすと、再発時ログが冗長になりやすかった。helper には callback timeout の断面を最短で再確認できる情報だけ残せば十分だった
- 決定事項: `tests/ap-frontend-sso-recovery.spec.ts` の timeout 文面を `SSO callback timeout while waiting for ...` と `Callback URL: ...` へ短縮し、`SSO debug enabled` 行は削除した。`Callback trace:` の prefix だけは triage script の抽出条件なので維持する。Playwright 公式コンテナで `pnpm --dir e2e run test:sso:container` を再実行した結果は `6 passed` だった
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、callback stall 再発時のログ量、helper と triage summary の役割分担
- 次の推奨アクション: 次は callback stall が実際に再発した時に `test:sso:triage` を流し、新しい helper 文面と `callback-triage-summary.txt` の並びが読みやすいかを実例で確認する

### callback triage の wording は selfcheck で先に確認できる

- 背景: 実際の callback stall 再発を待たないと helper 文面と triage summary の並びを確認できないままだと、改善の手応えをその場で残しにくかった。sample log でも `Callback trace:` の抽出と `Callback latest stage` の要約が噛み合うなら、実再発時の見え方もかなり先回りして確認できる
- 決定事項: `scripts/selfcheck-callback-triage.sh` と `pnpm --dir e2e run selfcheck:callback-triage` を追加し、圧縮済み helper 文面を含む sample timeout log を `print-callback-trace-summary.mjs` に流して自己確認できるようにした。selfcheck では trace line 抽出、`Callback trace count: 3`、`Callback latest stage: callback:token-exchange:error`、`Callback latest at: ...` を検証し、実行結果は pass した
- 影響範囲: `e2e/scripts/selfcheck-callback-triage.sh`、`e2e/package.json`、callback triage wording の自己確認手順、実再発前の検証導線
- 次の推奨アクション: 次は callback stall が実際に再発した時に `pnpm --dir e2e run test:sso:triage` を流し、実ログでも selfcheck と同じ読み味になっているかを確認する

### `test:sso:triage` の現状確認では standard run が `6 passed` で止まる

- 背景: selfcheck だけでは triage runner 本体の現状態までは分からないため、実際に `pnpm --dir e2e run test:sso:triage` を流した時に standard pass でどう終わるかを一度見ておきたかった。callback stall が未再発の間は、debug rerun まで進まないこと自体が正常動作になる
- 決定事項: `pnpm --dir e2e run test:sso:triage` を実行し、Playwright 公式コンテナの standard run は `6 passed`、runner は `[e2e] standard run passed. No debug rerun needed.` で終了することを確認した。現時点では triage の入口や summary 導線に追加修正は不要で、実再発時だけ同じ入口から debug rerun へ進めばよい
- 影響範囲: `e2e/scripts/run-sso-triage.sh` の期待挙動、callback stall 未再発時の運用、次回 triage 開始条件
- 次の推奨アクション: 次は callback stall の実再発を待ち、再発した時点で `pnpm --dir e2e run test:sso:triage` を流して helper 文面と `callback-triage-summary.txt` の実ログ上の噛み合いを確認する

## 補足

### objects / policies の browser 回帰は users spec から分離する

- 背景: `objects` / `policies` は `object.read` ベースの Operations 導線として今後育てていく一方、既存の `ap-frontend-sso-recovery.spec.ts` は users 一覧・詳細・assignment 文脈の query recovery まで持っており、そこへ同居させると failure 時の切り分けが読みにくくなりやすかった
- 決定事項: browser 回帰は users 系と Operations 系で spec を分ける。`e2e/tests/ap-frontend-sso-recovery.spec.ts` は users query / detail recovery 専用として維持し、`e2e/tests/ap-frontend-operations-sso-recovery.spec.ts` を追加して `objects` / `policies` の導線表示と logout / re-login 後に同じ page へ戻ることを確認する。実行入口は `pnpm --dir e2e run test:sso` で両方、個別確認は `test:sso:users` と `test:sso:operations` を使う
- 影響範囲: `e2e/tests/ap-frontend-sso-recovery.spec.ts`、`e2e/tests/ap-frontend-operations-sso-recovery.spec.ts`、`e2e/package.json` の SSO 実行スクリプト、今後の AP Frontend browser 回帰の責務分離
- 次の推奨アクション: 次は Operations spec を土台に、`objects` / `policies` に一覧取得や scope filter が入ったタイミングで placeholder assertion を API 接続後の一覧 assertion へ置き換えていく

### objects は placeholder ではなく最小一覧を回帰対象にする

- 背景: Operations spec を分離した時点では `objects` も placeholder card を見ていたが、実装の先行対象を `objects` に決めたことで、browser 回帰も「画面が開く」だけでなく一覧骨格が出ることまで見たくなった
- 決定事項: `objects` は `GET /api/objects` へつながる最小一覧として `Filter & Sort` と `Objects List` の表示を確認する前提へ更新し、`policies` は引き続き placeholder page として扱う。`test:sso:operations` は objects 側だけ新しい一覧骨格に合わせ、live/mock どちらでも壊れにくい静的見出しを assertion の基準にする
- 影響範囲: `e2e/tests/ap-frontend-operations-sso-recovery.spec.ts` の objects assertion、今後の Operations 画面実装時の回帰追加単位
- 次の推奨アクション: 次は `policies` 一覧が入った段階で同じ spec に最小一覧 assertion を足し、objects / policies の両方を placeholder 依存なしで確認できるようにする

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
- `triage:ubuntu` は別 server 実機での最初の入口で、`report -> doctor -> verify/recover` をまとめる
- この repo の現時点の区切りとしては、今使っている Ubuntu Server で `test:sso:auto` とローカル `test:sso` が通っていれば browser 実行環境は十分。別 server 実機向けの `triage / recover / report` は必要になった時だけ使えばよい
