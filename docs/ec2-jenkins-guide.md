# Tự triển khai Lernumi lên EC2 với Jenkins

Môi trường Jenkins local có thể chạy hoàn toàn bằng Docker, gồm controller, agent Linux và Docker engine riêng cho job. Xem [cách chạy Jenkins trong Docker](../docker/jenkins/README.md). Stack này chuẩn bị công cụ và kết nối agent `docker-local`; bạn vẫn tự viết `Jenkinsfile` và cấu hình các stage bên dưới.

Bạn tự viết pipeline, cấu hình cloud và chạy deployment. Repo hiện có Docker cho phát triển local; chưa có `Jenkinsfile`, image production hay stack production do tài liệu này tạo ra. Với lựa chọn **EC2 t4g.nano**, bắt đầu bằng [hướng dẫn nano ARM64 và dự toán chi phí](ec2-t4g-nano.md): máy chỉ có 0.5 GiB RAM, dùng cho demo ít tải và cần đo thực tế.

Frontend và backend đã được kết nối ở local; chưa có phần nào được triển khai cloud. Jenkins và quá trình build image chạy ở máy khác. Nano chỉ nhận image `linux/arm64` đã build, chạy reverse proxy, Laravel và PostgreSQL 17 cho demo ít tải. Frontend Next có SSR cần host khác hoặc máy nhiều RAM hơn; không đưa cả stack vào ngân sách 0.5 GiB RAM của nano.

## 1. Kiến trúc cần dựng

```text
GitHub → Jenkins controller → build agent → container registry
                                          ↓ image theo commit
                                EC2 Ubuntu ARM64 t4g.nano
Browser → HTTPS reverse proxy → Laravel PHP-FPM + Nginx / FrankenPHP
                             → PostgreSQL 17 trong mạng Docker riêng
                             → dữ liệu bền vững trên EBS
Tool browser → hostname runtime riêng → bootstrap/assets + /api/tool/v1
Frontend Next SSR và Jenkins/build → máy khác
```

Build image đúng `linux/arm64` cho t4g.nano; agent có thể dùng ARM64 native hoặc Buildx trên máy khác. Giá trị FPM/PostgreSQL trong hướng dẫn nano là điểm bắt đầu để đo, không bảo đảm toàn bộ tải Lernumi vừa 0.5 GiB. Sự cố instance sẽ làm cả API và database tạm ngừng.

Trên EC2, tạo thư mục phát hành cấu hình và mount dữ liệu cố định. Giữ project name production ổn định, ví dụ `lernumi-prod`; dùng volume/bind mount riêng cho PostgreSQL và `storage/app` của Laravel. Nếu volume nằm trên EBS riêng, cấu hình mount sau reboot và kiểm tra volume đã mount trước khi khởi động database. Dữ liệu upload, avatar, document và tool ZIP/assets cũng phải được sao lưu.

Cài Docker Engine cùng Compose plugin bằng [hướng dẫn Ubuntu chính thức](https://docs.docker.com/engine/install/ubuntu/). Tự tạo Compose production với image cố định, restart policy, healthcheck, resource/log limits và dữ liệu persist; tham khảo [Compose trên một server](https://docs.docker.com/compose/how-tos/production/).

## 2. Các file bạn cần viết

| File/cấu hình | Nội dung cần có |
| --- | --- |
| `Jenkinsfile` | Checkout, kiểm tra PHP/TypeScript, PostgreSQL test, browser E2E, build frontend và image, push, deploy nhánh được tin cậy, healthcheck, báo kết quả và cleanup CI |
| Dockerfile production | PHP 8.3 cùng extensions, source + vendor từ lockfile; web server production; user và quyền ghi phù hợp |
| Build/image frontend | Cài pnpm theo `client/package.json`, kiểm tra/build Next.js và đóng gói SSR; chạy trên host khác hoặc instance đủ RAM |
| Compose CI | PostgreSQL test tạm và API test; không publish port host; project/volume riêng từng build |
| Compose production | API/web proxy/PostgreSQL 17, image tags/digests, mạng nội bộ, persistent storage, env và restart/healthcheck |
| Reverse proxy/TLS | Vhost platform, vhost tool riêng, HTTPS, forwarded headers, upload limits, route `/api`, `/sanctum/csrf-cookie`, `/up` |
| Script deploy | Khóa deployment, pull image, kiểm tra cấu hình, migrate một lần, đổi release, kiểm tra sức khỏe, xử lý thất bại |
| Script backup/restore | Backup PostgreSQL và file storage, retention, mã hóa/quyền truy cập, restore thử sang môi trường riêng |

`server/Dockerfile` hiện chạy `artisan serve` và Compose bind mount source để phát triển. Với image production, copy source/vendor vào image và chỉ persist dữ liệu. Với nano, ưu tiên PHP-FPM + Nginx với một FPM child theo hướng dẫn riêng; FrankenPHP là lựa chọn khác cần đo bộ nhớ trước. Web root Laravel là `public/`, cần quyền ghi `storage` và `bootstrap/cache`. Xem [Laravel deployment](https://laravel.com/docs/13.x/deployment).

Trong build image, dùng `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader` từ `composer.lock`; chạy `composer check-platform-reqs --no-dev`. Bộ test dùng một image/stage có dev dependencies. Không chạy Composer update khi deploy. Sau khi inject environment của release, tạo config/route cache; tránh bake cache chứa cấu hình hoặc secret production vào image dùng chung. [Laravel configuration caching](https://laravel.com/docs/13.x/configuration#configuration-caching).

Local hiện có `server/docker/php.ini` với `upload_max_filesize=25M`, `post_max_size=30M`. Khi tự cấu hình production, đồng bộ PHP và proxy; ví dụ giữ upload 25M, chọn post/body limit 32 MiB để có chỗ cho multipart overhead. App giới hạn document 20 MiB và ZIP tool 25 MiB. Kiểm tra bằng upload HTTP thật; fake upload trong PHPUnit không kiểm tra giới hạn transport của PHP/Nginx.

## 3. Cấu hình EC2 và network

- Tạo EC2 Ubuntu LTS, security group và EBS mã hóa; quyết định rõ dữ liệu nào được giữ khi instance bị terminate.
- Cho phép `80/443` tới reverse proxy. Chỉ cho SSH `22` từ IP quản trị hoặc đường quản trị riêng của bạn. Giữ PostgreSQL, PHP-FPM và Docker daemon ở mạng riêng; không publish database ra Internet. [AWS: quy tắc security group](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/security-group-rules-reference.html).
- Dùng tên miền trỏ tới địa chỉ ổn định của instance. Chọn hai hostname: `app.example.com` phục vụ frontend cùng proxy Laravel tại `/api/*`, `/sanctum/csrf-cookie`; `tools.example.com` phục vụ runtime riêng.
- Cấu hình TLS và gia hạn chứng chỉ. Kiểm tra redirect HTTP → HTTPS, hostname và forwarded proto đúng trước khi thử cookie; cấu hình Laravel tin cậy đúng reverse proxy và hostname mà bạn dùng.
- Tạo IAM role instance với quyền tối thiểu cho registry/backup nếu dùng dịch vụ AWS. Jenkins cần quyền deploy cụ thể; không cần quyền quản trị toàn bộ AWS.

Nếu cùng reverse proxy phục vụ frontend và Laravel dưới `app.example.com`, giữ session cookie host-only: `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, path `/`. Không chia sẻ session cookie với hostname tool. Chỉ cho frontend platform vào `SANCTUM_STATEFUL_DOMAINS`; URL API và các origin CORS phải đúng hostname public. Runtime dùng capability Bearer riêng; xem [hướng dẫn frontend](frontend-integration.md).

Giữ mô hình cùng origin này khi bắt đầu. Nếu chuyển Laravel sang `api.example.com`, frontend tại `app.example.com` không đọc được cookie XSRF host-only của API để tạo header CSRF. Khi đó cần BFF/proxy hoặc luồng CSRF token bổ sung; không giải quyết bằng cách mở cookie domain `.example.com`, vì cookie đăng nhập sẽ bị chia sẻ sang runtime và backend từ chối cấu hình đó.

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

`FRONTEND_URL` trong Next và Laravel phải cùng origin app public, ví dụ `https://app.example.com`; đồng bộ CORS/Sanctum và cấu hình proxy ở mục 3. URL này khác địa chỉ nội bộ dùng làm `LERNUMI_API_URL`. Hostname tool tiếp tục tách riêng khỏi app và cookie đăng nhập.

Trong Jenkins Credentials, tạo ID riêng cho registry, deploy SSH nếu dùng SSH, và secret file/environment. Bind credential ở đúng stage cần dùng. Chỉ job/nhánh được tin cậy được push/deploy; build PR/fork chạy test không có credential production. Masking log không ngăn một pipeline độc hại đọc secret, vì vậy cần tách trust boundary này trong cấu hình job/agent. [Jenkins Credentials](https://www.jenkins.io/doc/book/using/using-credentials/), [xử lý credential trong pipeline](https://www.jenkins.io/doc/book/pipeline/jenkinsfile/#handling-credentials).

## 5. Các stage Jenkins cần tự viết

Tạo Multibranch Pipeline trỏ repository, chọn branch discovery và cài webhook GitHub phù hợp plugin bạn dùng. Jenkins đọc `Jenkinsfile` từ source control; cấu hình webhook secret và quyền repository trong Jenkins/GitHub. [Multibranch Pipeline](https://www.jenkins.io/doc/book/pipeline/multibranch/).

| Stage | Việc thực hiện | Điều kiện đạt |
| --- | --- | --- |
| Checkout | Checkout chính xác commit; lưu SHA và metadata release | Workspace đúng commit |
| Dependencies | Cài Composer theo lockfile; tại `client/` chạy `pnpm install --frozen-lockfile`; kiểm tra PHP/extensions và Node/pnpm | Cài thành công, platform phù hợp |
| Static checks | Lint PHP; tại `client/` chạy `pnpm typecheck`; kiểm tra dependency theo chính sách của bạn | Không lỗi syntax/type/platform |
| API tests | PostgreSQL 17 riêng; chạy toàn bộ PHPUnit với DB `lernumi_testing` | Suite pass; lưu JUnit report |
| Frontend build | Cấp `LERNUMI_API_URL` trước khi chạy `pnpm build` trong `client/`; giữ artifact đúng môi trường | Next build thành công |
| Browser E2E | Khởi động backend test local đã seed và Next trên runner cô lập, rồi chạy `pnpm test:e2e` trong `client/` | Luồng UI/API pass; lưu report phù hợp |
| Image | Build image API đúng CPU và artifact/image SSR frontend, gắn tag commit, smoke test ở staging | Cả hai phần khởi động được với cấu hình môi trường |
| Publish | Chỉ trusted main/tag được push registry | Lưu digest/commit để truy vết |
| Deploy | Chỉ một deployment chạy; pull đúng digest và migrate | Release được cài, healthcheck đạt |
| Finalize | Ghi release/digest trước và sau; thông báo kết quả | Có lịch sử để rollback |
| Cleanup CI | Luôn thu log/report và dọn đúng stack CI của build | Không đụng volume production |

Agent cần Git, Docker/Compose, PHP 8.3 hoặc image test có PHP/Composer, PostgreSQL client khi cần, cùng Node.js/pnpm để kiểm tra và build frontend đã kết nối. Dùng phiên bản pnpm khai báo trong `client/package.json`. Browser E2E cần Playwright Chromium (`pnpm exec playwright install --with-deps chromium` trên agent Linux phù hợp) hoặc browser/channel đã cài; xem [frontend README](../client/README.md). Jenkins hỗ trợ agent/container và sidecar để chạy build/test; xem [Docker with Pipeline](https://www.jenkins.io/doc/book/pipeline/docker/).

Mỗi build đặt project name riêng, ví dụ `lernumi-ci-<job>-<build-number>` đã chuẩn hóa. CI Compose không publish `8000`/`5433`, tránh tranh port và tránh trỏ tới database local/production. `tests/TestCase.php` cố tình chỉ chấp nhận PostgreSQL `lernumi_testing`; cấu hình `phpunit.xml` và CI database phải đồng bộ. Đợi `pg_isready` rồi chạy PHPUnit. Không chạy các suite `RefreshDatabase` song song trên cùng một database; muốn parallel test phải tách database và điều chỉnh guard có chủ đích.

Browser E2E dùng tài khoản/demo data trong stack test riêng, không dùng production. Chạy PHPUnit xong rồi mới seed/khởi động backend phục vụ E2E; không để `RefreshDatabase` xóa dữ liệu trong lúc browser đang kiểm thử. Cấu hình hiện tại chỉ chấp nhận URL local, frontend `localhost:3001`, API `localhost:8000` và runtime tool `127.0.0.1:8000`. Bố trí các tiến trình trong cùng network namespace của môi trường test cô lập để các địa chỉ này hoạt động mà không mở port CI ra Internet. Không chạy hai job E2E dùng chung các port/database này trên cùng môi trường. Nếu bạn thay topology CI, phải cập nhật đồng bộ URL, hostnames và cấu hình Playwright rồi kiểm tra lại luồng cookie/runtime.

Lưu ý `compose.yaml` local có environment/port/bind mount dành cho phát triển. Chỉ đổi `-p` trên file này chưa đủ để thành CI hoặc production. Bạn cần viết file CI riêng như bảng trên. Khi cleanup có thể xóa volume tạm của đúng build CI; không dùng `down -v` trên stack chứa dữ liệu thật.

## 6. Trình tự deploy và rollback

1. Khóa deployment theo môi trường, chặn build cũ deploy sau build mới. Lưu digest đang chạy và digest sắp triển khai; tạo/kiểm tra backup theo chính sách đã chọn.
2. Pull đúng image đã pass CI. Không build lại từ source trên EC2; image API được thử ở staging rồi đưa lên production. Artifact frontend phải được build với backend URL phù hợp như mục 4 và thử lại với cấu hình đích trước khi phát hành.
3. Inject environment, kiểm tra network và volume đã mount. Xác nhận database production đúng trước khi chạy migration.
4. Chạy `php artisan migrate --force` một lần bằng image mới trong mạng production. Giữ khóa deploy trong suốt bước này và dừng pipeline nếu migration lỗi. Laravel cung cấp `--force` để chạy migration production không hỏi tương tác. [Tài liệu migrations](https://laravel.com/docs/13.x/migrations#forcing-migrations-to-run-in-production).
5. Khởi động/thay API container bằng image mới, tạo cache với environment thật, restart worker nếu sau này có queue. Phát hành artifact/image frontend lên host SSR riêng hoặc instance đủ RAM, cấp đúng environment runtime và cấu hình proxy cùng origin. Với một instance có thể có downtime ngắn; muốn tránh cần tự bổ sung hai release upstream và chuyển proxy sau khi kiểm tra.
6. Kiểm tra `/up`, truy vấn database và một luồng authenticated smoke test bằng tài khoản kiểm thử phù hợp. `/up` mặc định chỉ xác nhận ứng dụng boot; một response `200` chưa chứng minh database/storage hoạt động. [Laravel health route](https://laravel.com/docs/13.x/deployment#the-health-route).
7. Kiểm tra storage đọc/ghi, session/CSRF, download file và khởi chạy tool trên hostname riêng. Nếu đạt, lưu release thành công; nếu không, trả proxy/API về image trước và kiểm tra lại.

Để rollback image chạy được sau migration, viết schema theo cách mở rộng trước, chuyển code sau, dọn cột ở release sau nữa. Không tự động `migrate:rollback` trong pipeline khi smoke test fail: migration có thể làm mất dữ liệu và ảnh hưởng code đang chạy. Nếu schema đã thay đổi không tương thích, dùng migration sửa tiếp hoặc kế hoạch restore đã thử nghiệm.

Không tạo admin/demo data lại ở mọi deploy. Với môi trường mới, bạn thực hiện bootstrap tài khoản quản trị theo hướng dẫn backend và giữ credential ngoài pipeline output. Không dùng demo password cho production.

## 7. Backup và việc kiểm tra sau cùng

Lập lịch `pg_dump` định dạng custom cho database cùng bản sao file storage, chuyển backup khỏi instance, đặt retention và kiểm tra restore vào database/máy thử riêng. PostgreSQL mô tả tính nhất quán và cách restore trong [SQL dump](https://www.postgresql.org/docs/17/backup-dump.html). EBS snapshot bổ sung khả năng phục hồi volume; để có snapshot nhất quán cần xử lý các write còn nằm trong cache, theo [AWS EBS snapshots](https://docs.aws.amazon.com/ebs/latest/userguide/ebs-creating-snapshot.html).

Trước khi đưa người dùng thật vào, tự kiểm tra reboot EC2 vẫn mount đúng dữ liệu và khởi động service; redeploy không làm mất DB/file; rollback image đã thử; backup đã restore được; login/CSRF và phân quyền vẫn đúng; TLS còn hạn; cảnh báo dung lượng EBS, API lỗi và database downtime đã hoạt động.
