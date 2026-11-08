# Tự triển khai Lernumi lên EC2 với Jenkins

Jenkins chạy local hoàn toàn bằng Docker, gồm controller, agent Linux và Docker engine riêng cho job. Xem [cách chạy Jenkins trong Docker](../server/docker/jenkins/README.md). Repo đã có `Jenkinsfile` và `compose.ci.yaml` cho kiểm tra backend/frontend; bạn tự viết phần CD và thực hiện triển khai cloud.

Kiến trúc đã chọn là **EC2 t4g.small ARM64 tại Sydney**, Ubuntu, 2 GiB RAM và 1 GiB swap; Docker chạy Nginx, Laravel PHP-FPM và PostgreSQL 17. Frontend Next.js dùng Vercel, với Functions ở Sydney (`syd1`). Swap hỗ trợ khi thiếu bộ nhớ; nó không tăng RAM thật. [Hướng dẫn nano](ec2-t4g-nano.md) chỉ là phương án tham khảo cho máy nhỏ hơn, không phải cấu hình đang chọn.

`server/Dockerfile.prod` và `server/compose.prod.yaml` đã có. Cấu hình Nginx hiện chuẩn bị HTTP cho ACME cấp chứng chỉ và có cổng loopback để kiểm tra backend; chưa hoàn tất HTTPS public. Frontend chưa triển khai lên Vercel. Hướng CD là Jenkins kiểm tra commit, chuyển source release qua SSH, rồi build image ngay trên EC2; không bắt buộc dùng registry hoặc ECR.

## 1. Kiến trúc cần dựng

```text
GitHub → Jenkins CI trong Docker local → chuyển source release qua SSH
                                      → EC2 t4g.small ARM64, Sydney
                                        build API rồi Nginx, chạy Docker

Browser → app.example.com trên Vercel, Functions Sydney
          /api/* và /sanctum/* rewrite → api.example.com trên EC2
                                       Nginx → Laravel PHP-FPM → PostgreSQL 17
Tool browser → tools.example.com trên cùng EC2 → bootstrap/assets + /api/tool/v1
PostgreSQL và storage/app → Docker volumes bền vững trên EBS
```

Build API và Nginx lần lượt trên EC2 để tạo image ARM64 native và giảm tải bộ nhớ lúc build. Jenkins và frontend không chạy trên EC2. Cấu hình hiện phù hợp để bắt đầu thử nghiệm, vẫn cần đo tải thực tế; sự cố instance sẽ làm cả API và database tạm ngừng. Build ở máy khác rồi push registry là phương án thay thế, khi đó phải chọn đúng `linux/arm64`.

Trên EC2, tạo thư mục phát hành cấu hình và mount dữ liệu cố định. Giữ project name production ổn định, ví dụ `lernumi-prod`; dùng volume/bind mount riêng cho PostgreSQL và `storage/app` của Laravel. Nếu volume nằm trên EBS riêng, cấu hình mount sau reboot và kiểm tra volume đã mount trước khi khởi động database. Dữ liệu upload, avatar, document và tool ZIP/assets cũng phải được sao lưu.

Cài Docker Engine cùng Compose plugin bằng [hướng dẫn Ubuntu chính thức](https://docs.docker.com/engine/install/ubuntu/). Dùng `server/compose.prod.yaml` làm nền cho production; phần CD cần bổ sung tag image theo release và cách giữ image trước để rollback. Tham khảo [Compose trên một server](https://docs.docker.com/compose/how-tos/production/).

## 2. Các file đã có và phần cần bổ sung

| File/cấu hình | Nội dung cần có |
| --- | --- |
| `Jenkinsfile` | Đã có CI: checkout, PHP/TypeScript, PHPUnit, Next build, browser E2E, report và cleanup. Bạn bổ sung CD cho nhánh được tin cậy |
| `server/Dockerfile.prod` và `server/docker/nginx/Dockerfile` | Đã có image PHP-FPM và Nginx; Composer cài từ lockfile, runtime chạy PHP bằng user phù hợp |
| Vercel | Cấu hình frontend, Functions Sydney và environment trước build; không đóng gói frontend để chạy trên EC2 |
| `compose.ci.yaml` | Đã có PostgreSQL/API/browser test cô lập; không publish port host; project/volume riêng từng build |
| `server/compose.prod.yaml` | Đã có API/Nginx/PostgreSQL, persistent storage và healthcheck; cần hoàn thiện image tags theo release và vận hành |
| Reverse proxy/TLS | Nginx hiện có HTTP ACME và listener loopback. Cần cấp chứng chỉ, bật HTTPS API/tool, redirect và gia hạn |
| Script deploy | Bạn tự viết: khóa deployment, chuyển source release, build lần lượt trên EC2, migrate một lần, đổi release và healthcheck |
| Script backup/restore | Backup PostgreSQL và file storage, retention, mã hóa/quyền truy cập, restore thử sang môi trường riêng |

`server/Dockerfile` chạy `artisan serve` cho local/CI. `server/Dockerfile.prod` copy source/vendor vào image và chạy PHP-FPM; Nginx có image riêng. Production chỉ persist dữ liệu, không bind mount source từ máy phát triển. Web root Laravel là `public/`, cần quyền ghi `storage` và `bootstrap/cache`. Xem [Laravel deployment](https://laravel.com/docs/13.x/deployment).

Trong build image, dùng `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader` từ `composer.lock`; chạy `composer check-platform-reqs --no-dev`. Bộ test dùng một image/stage có dev dependencies. Không chạy Composer update khi deploy. Sau khi inject environment của release, tạo config/route cache; tránh bake cache chứa cấu hình hoặc secret production vào image dùng chung. [Laravel configuration caching](https://laravel.com/docs/13.x/configuration#configuration-caching).

Local hiện có `server/docker/php.ini` với `upload_max_filesize=25M`, `post_max_size=30M`. Khi tự cấu hình production, đồng bộ PHP và proxy; ví dụ giữ upload 25M, chọn post/body limit 32 MiB để có chỗ cho multipart overhead. App giới hạn document 20 MiB và ZIP tool 25 MiB. Kiểm tra bằng upload HTTP thật; fake upload trong PHPUnit không kiểm tra giới hạn transport của PHP/Nginx.

## 3. Cấu hình EC2 và network

- Dùng EC2 t4g.small Ubuntu ARM64 tại Sydney, 1 GiB swap, security group và EBS mã hóa; quyết định rõ dữ liệu nào được giữ khi instance bị terminate.
- Cho phép `80/443` tới reverse proxy. Chỉ cho SSH `22` từ IP quản trị hoặc đường quản trị riêng của bạn. Giữ PostgreSQL, PHP-FPM và Docker daemon ở mạng riêng; không publish database ra Internet. [AWS: quy tắc security group](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/security-group-rules-reference.html).
- `app.example.com` trỏ Vercel. `api.example.com` và `tools.example.com` trỏ địa chỉ ổn định của EC2. Browser gọi `/api/*` và `/sanctum/*` trên frontend; Next.js rewrite các request này tới HTTPS API trên EC2.
- Cấu hình TLS và gia hạn chứng chỉ. Kiểm tra redirect HTTP → HTTPS, hostname và forwarded proto đúng trước khi thử cookie; cấu hình Laravel tin cậy đúng reverse proxy và hostname mà bạn dùng.
- Tạo IAM role instance với quyền tối thiểu cho registry/backup nếu dùng dịch vụ AWS. Jenkins cần quyền deploy cụ thể; không cần quyền quản trị toàn bộ AWS.

Với frontend proxy tại Vercel, giữ session cookie host-only: `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, path `/`. Browser nhận cookie qua origin frontend, còn SSR chuyển tiếp session sang API. Không chia sẻ session cookie với hostname tool. Khai báo frontend trong `SANCTUM_STATEFUL_DOMAINS`, đồng bộ CORS và các URL public. Runtime dùng capability Bearer riêng; xem [hướng dẫn frontend](frontend-integration.md).

Giữ browser gọi qua proxy cùng origin frontend. Nếu browser gọi trực tiếp `api.example.com`, frontend tại `app.example.com` không đọc được cookie XSRF host-only của API để tạo header CSRF. Không khắc phục bằng cách mở cookie domain `.example.com`, vì cookie đăng nhập sẽ bị chia sẻ sang runtime và backend từ chối cấu hình đó.

## 4. Environment và secret

Chuẩn bị environment production riêng ngoài Git. Các nhóm biến cần kiểm tra:

| Nhóm | Biến/ý nghĩa |
| --- | --- |
| Laravel | `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` là URL public |
| Khóa | `APP_KEY` cố định; `TOOL_TOKEN_SECRET` ngẫu nhiên riêng, giữ ổn định giữa release |
| Database | `DB_CONNECTION=pgsql`, host là service PostgreSQL, database/user/password production riêng |
| SPA | `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`, `SANCTUM_STATEFUL_DOMAINS`, cookie/session nói trên |
| Next.js | `LERNUMI_API_URL` là địa chỉ Laravel mà tiến trình Next truy cập được; cấu hình trước build và giữ cùng giá trị khi chạy SSR. `FRONTEND_URL` là origin app public, khớp Laravel |
| Session/storage | `SESSION_DRIVER=database`, local storage mount bền vững; `FILESYSTEM_DISK` theo cấu hình thực tế |
| Tool | `TOOL_RUNTIME_ORIGIN` public, `TOOL_TOKEN_TTL=300`; cấu hình route runtime ở hostname riêng |
| Email | SMTP thật cho reset password/verification; địa chỉ gửi và link quay về frontend đã cấu hình |
| Vận hành | Log level phù hợp, log rotation, backup destination, metrics/alerts |

Tạo `APP_KEY` một lần khi dựng môi trường và lưu an toàn; không `key:generate` lại sau mỗi deploy. Không đưa `.env`, private key, cookie, Bearer token hoặc mật khẩu vào artifact/log. `APP_DEBUG=false` là cấu hình production của [Laravel](https://laravel.com/docs/13.x/configuration#debug-mode).

`client/next.config.ts` đọc `LERNUMI_API_URL` khi tạo rewrites cho `/api/*` và `/sanctum/*`. Các rewrite được ghi vào kết quả `next build`, vì vậy phải cấp biến này **trước `pnpm build`**; đổi biến chỉ khi khởi động container không đổi đích proxy đã build. Giữ cùng giá trị cho SSR runtime để browser proxy và server component truy cập cùng backend. Khi đổi backend URL giữa staging/production, build artifact phù hợp từng môi trường, hoặc tự thiết kế một địa chỉ backend ổn định mà các môi trường đều phân giải đúng. Không dùng địa chỉ CI tạm rồi đưa nguyên rewrite đó lên production.

`FRONTEND_URL` trong Next và Laravel phải cùng origin app public, ví dụ `https://app.example.com`; đồng bộ CORS/Sanctum và cấu hình proxy ở mục 3. Trong kiến trúc Vercel này, `LERNUMI_API_URL` là HTTPS API public trên EC2, ví dụ `https://api.example.com`. Hostname tool tiếp tục tách riêng khỏi app và cookie đăng nhập.

Trong Jenkins Credentials, tạo credential deploy SSH; giữ environment production riêng trên EC2 hoặc cấp qua secret file. Chỉ thêm credential registry nếu chọn phương án đó. Bind credential ở đúng stage cần dùng. Chỉ job/nhánh được tin cậy được deploy; build PR/fork chạy test không có credential production. Masking log không ngăn một pipeline độc hại đọc secret, vì vậy cần tách trust boundary này trong cấu hình job/agent. [Jenkins Credentials](https://www.jenkins.io/doc/book/using/using-credentials/), [xử lý credential trong pipeline](https://www.jenkins.io/doc/book/pipeline/jenkinsfile/#handling-credentials).

## 5. CI hiện có và phần CD cần tự viết

Job Jenkins đọc `Jenkinsfile` từ SCM và chạy trên agent `docker-local`; CI hiện có polling. Jenkins local không tự nhận được webhook public. Nếu sau này dùng Multibranch Pipeline/webhook, cấu hình kết nối, secret và quyền repository phù hợp. [Multibranch Pipeline](https://www.jenkins.io/doc/book/pipeline/multibranch/). Bảng dưới phân biệt các kiểm tra đã có với phần phát hành cần bổ sung.

| Stage | Việc thực hiện | Điều kiện đạt |
| --- | --- | --- |
| Checkout | Checkout chính xác commit; lưu SHA và metadata release | Workspace đúng commit |
| Dependencies | Cài Composer theo lockfile; tại `client/` chạy `pnpm install --frozen-lockfile`; kiểm tra PHP/extensions và Node/pnpm | Cài thành công, platform phù hợp |
| Static checks | Lint PHP; tại `client/` chạy `pnpm typecheck`; kiểm tra dependency theo chính sách của bạn | Không lỗi syntax/type/platform |
| API tests | PostgreSQL 17 riêng; chạy toàn bộ PHPUnit với DB `lernumi_testing` | Suite pass; lưu JUnit report |
| Frontend build (CI) | Chạy `pnpm build` với backend test; frontend production được Vercel build riêng với HTTPS API thật | Next build thành công |
| Browser E2E | Khởi động backend test local đã seed và Next trên runner cô lập, rồi chạy `pnpm test:e2e` trong `client/` | Luồng UI/API pass; lưu report phù hợp |
| Transfer (CD cần viết) | Chỉ chuyển source đúng commit đã pass CI của main/tag được tin cậy qua SSH; không gửi env, vendor hoặc dữ liệu runtime | EC2 nhận đúng source release |
| Image (CD cần viết) | Build API rồi Nginx trên EC2 ARM64, gắn tag commit, giữ image trước | Build thành công và ghi lại image ID/release |
| Deploy (CD cần viết) | Chỉ một deployment chạy; kiểm tra env/volume, migrate và thay container | Release được cài, healthcheck đạt |
| Finalize (CD cần viết) | Ghi commit/image ID trước và sau; thông báo kết quả | Có lịch sử để rollback |
| Cleanup CI | Luôn thu log/report và dọn đúng stack CI của build | Không đụng volume production |

Agent cần Git, Docker/Compose, PHP 8.3 hoặc image test có PHP/Composer, PostgreSQL client khi cần, cùng Node.js/pnpm để kiểm tra và build frontend đã kết nối. Dùng phiên bản pnpm khai báo trong `client/package.json`. Browser E2E cần Playwright Chromium (`pnpm exec playwright install --with-deps chromium` trên agent Linux phù hợp) hoặc browser/channel đã cài; xem [frontend README](../client/README.md). Jenkins hỗ trợ agent/container và sidecar để chạy build/test; xem [Docker with Pipeline](https://www.jenkins.io/doc/book/pipeline/docker/).

Mỗi build đặt project name riêng, ví dụ `lernumi-ci-<job>-<build-number>` đã chuẩn hóa. CI Compose không publish `8000`/`5433`, tránh tranh port và tránh trỏ tới database local/production. `tests/TestCase.php` cố tình chỉ chấp nhận PostgreSQL `lernumi_testing`; cấu hình `phpunit.xml` và CI database phải đồng bộ. Đợi `pg_isready` rồi chạy PHPUnit. Không chạy các suite `RefreshDatabase` song song trên cùng một database; muốn parallel test phải tách database và điều chỉnh guard có chủ đích.

Browser E2E dùng tài khoản/demo data trong stack test riêng, không dùng production. Chạy PHPUnit xong rồi mới seed/khởi động backend phục vụ E2E; không để `RefreshDatabase` xóa dữ liệu trong lúc browser đang kiểm thử. Cấu hình hiện tại chỉ chấp nhận URL local, frontend `localhost:3001`, API `localhost:8000` và runtime tool `127.0.0.1:8000`. Bố trí các tiến trình trong cùng network namespace của môi trường test cô lập để các địa chỉ này hoạt động mà không mở port CI ra Internet. Không chạy hai job E2E dùng chung các port/database này trên cùng môi trường. Nếu bạn thay topology CI, phải cập nhật đồng bộ URL, hostnames và cấu hình Playwright rồi kiểm tra lại luồng cookie/runtime.

Lưu ý `server/compose.yaml` local có environment/port/bind mount dành cho phát triển. CI dùng `compose.ci.yaml`; production dùng `server/compose.prod.yaml`. Chỉ đổi `-p` trên file local chưa đủ để thành CI hoặc production. Khi cleanup có thể xóa volume tạm của đúng build CI; không dùng `down -v` trên stack chứa dữ liệu thật.

## 6. Trình tự deploy và rollback

1. Khóa deployment theo môi trường, chặn build cũ deploy sau build mới. Lưu commit và image ID đang chạy; tạo/kiểm tra backup theo chính sách đã chọn.
2. Chuyển source đúng commit đã pass CI qua SSH vào thư mục release trên EC2. Giữ environment production và dữ liệu runtime ngoài gói source; không chép đè chúng bằng cấu hình local.
3. Kiểm tra environment, cấu hình Compose, network và volume đã mount. Build image API rồi Nginx lần lượt trên EC2, gắn tag release và ghi image ID; giữ image trước để rollback. Xác nhận database production đúng trước khi chạy migration.
4. Chạy `php artisan migrate --force` một lần bằng image mới trong mạng production. Giữ khóa deploy trong suốt bước này và dừng pipeline nếu migration lỗi. Laravel cung cấp `--force` để chạy migration production không hỏi tương tác. [Tài liệu migrations](https://laravel.com/docs/13.x/migrations#forcing-migrations-to-run-in-production).
5. Khởi động/thay container bằng image mới, tạo cache với environment thật, restart worker nếu sau này có queue. Frontend được phát hành qua Vercel với Functions Sydney, `LERNUMI_API_URL` là HTTPS API thật trước build và `FRONTEND_URL` đúng origin frontend. Với một instance có thể có downtime ngắn; muốn tránh cần tự bổ sung hai release upstream và chuyển proxy sau khi kiểm tra.
6. Kiểm tra `/up`, `/api/v1/health` và một luồng authenticated smoke test bằng tài khoản kiểm thử phù hợp. Khi còn ở bước bootstrap, dùng `http://localhost:8081/api/v1/health` trên EC2; public API chỉ được mở sau khi cấu hình HTTPS xong. `/up` mặc định chỉ xác nhận ứng dụng boot; một response `200` chưa chứng minh database/storage hoạt động. [Laravel health route](https://laravel.com/docs/13.x/deployment#the-health-route).
7. Kiểm tra storage đọc/ghi, session/CSRF, download file và khởi chạy tool trên hostname riêng. Nếu đạt, lưu release thành công; nếu không, trả proxy/API về image trước và kiểm tra lại.

Để rollback image chạy được sau migration, viết schema theo cách mở rộng trước, chuyển code sau, dọn cột ở release sau nữa. Không tự động `migrate:rollback` trong pipeline khi smoke test fail: migration có thể làm mất dữ liệu và ảnh hưởng code đang chạy. Nếu schema đã thay đổi không tương thích, dùng migration sửa tiếp hoặc kế hoạch restore đã thử nghiệm.

Không tạo admin/demo data lại ở mọi deploy. Với môi trường mới, bạn thực hiện bootstrap tài khoản quản trị theo hướng dẫn backend và giữ credential ngoài pipeline output. Không dùng demo password cho production.

## 7. Backup và việc kiểm tra sau cùng

Lập lịch `pg_dump` định dạng custom cho database cùng bản sao file storage, chuyển backup khỏi instance, đặt retention và kiểm tra restore vào database/máy thử riêng. PostgreSQL mô tả tính nhất quán và cách restore trong [SQL dump](https://www.postgresql.org/docs/17/backup-dump.html). EBS snapshot bổ sung khả năng phục hồi volume; để có snapshot nhất quán cần xử lý các write còn nằm trong cache, theo [AWS EBS snapshots](https://docs.aws.amazon.com/ebs/latest/userguide/ebs-creating-snapshot.html).

Trước khi đưa người dùng thật vào, tự kiểm tra reboot EC2 vẫn mount đúng dữ liệu và khởi động service; redeploy không làm mất DB/file; rollback image đã thử; backup đã restore được; login/CSRF và phân quyền vẫn đúng; TLS còn hạn; cảnh báo dung lượng EBS, API lỗi và database downtime đã hoạt động.
