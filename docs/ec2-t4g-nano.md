# Lernumi trên EC2 t4g.nano: cấu hình demo tiết kiệm

Bạn tự tạo EC2, viết Dockerfile/Compose production, Jenkinsfile và chạy deploy. Tài liệu này hướng dẫn các quyết định và bước kiểm tra; chưa có tài nguyên AWS nào được tạo hoặc cấu hình production nào được áp dụng.

Mục tiêu là **backend Laravel + PostgreSQL cho demo ít tải**. Nano có thể là điểm thử ban đầu, nhưng 0.5 GiB RAM rất chật khi ghép OS, Docker, PHP và PostgreSQL. Chưa có benchmark Lernumi trên t4g.nano để cam kết số người dùng, thời gian đáp ứng hay upload tối đa.

## 1. Phần cứng và kiến trúc

| Thuộc tính AWS | t4g.nano |
| --- | --- |
| CPU | AWS Graviton2, Arm 64-bit |
| vCPU | 2 |
| RAM | 0.5 GiB |
| CPU baseline | 5% mỗi vCPU |
| CPU credits nhận được | 6 credits/giờ; tích lũy tối đa 144 credits |

Thông số được kiểm tra từ [AWS T4g](https://aws.amazon.com/ec2/instance-types/t4/) và [bảng CPU credits](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/burstable-credits-baseline-concepts.html#burstable-performance-instances-credits-table).

Chọn Ubuntu LTS **ARM64**, image container **`linux/arm64`**. Instance chạy PostgreSQL 17, một pool PHP-FPM nhỏ và Nginx/reverse proxy TLS. Jenkins controller/agent build, Composer install, PHPUnit và build frontend/tool chạy trên máy khác. Next SSR cũng cần host khác; không giả định frontend Next hiện tại có thể tự đổi thành static export. Jenkins công bố khuyến nghị 4 GB+ RAM cho nhóm nhỏ, nên không ghép Jenkins vào ngân sách RAM nano. [Jenkins requirements](https://www.jenkins.io/doc/book/installing/linux/#prerequisites).

Giữ một EC2 ở public subnet với Internet Gateway và một public IPv4 dùng chung cho hai hostname platform/tool. PostgreSQL nằm trong mạng Docker, không publish port database. Cấu trúc tối giản này không cần ALB, NAT Gateway hay RDS; backup vẫn cần nơi lưu tách khỏi instance. Frontend đã kết nối với backend ở local nhưng chưa được triển khai cloud; khi triển khai, giữ proxy API cùng origin như [hướng dẫn tích hợp](frontend-integration.md), đồng thời để tiến trình Next SSR ở host khác hoặc chọn instance nhiều RAM hơn. Dự toán dưới đây chỉ dành cho backend + PostgreSQL trên nano, chưa gồm chi phí host frontend.

## 2. Chi phí đã kiểm tra ngày 03/11/2026

Ví dụ Linux On-Demand, Ubuntu tiêu chuẩn không kèm phí bản quyền bổ sung, 730 giờ/tháng. Đơn vị **USD**, chưa thuế/quy đổi AUD và chưa áp dụng Free Tier/credit/giảm giá.

| Khoản | `us-east-1` N. Virginia | `ap-southeast-2` Sydney |
| --- | --- | --- |
| t4g.nano/giờ | $0.0042 | $0.0053 |
| Instance × 730 giờ | $3.066 | $3.869 |
| Một public IPv4 × 730 giờ | $3.65 | $3.65 |
| gp3 storage/GB-tháng | $0.08 | $0.096 |
| Ví dụ tổng 8 GB gp3 | $0.64 | $0.768 |
| Ví dụ tổng 12 GB gp3 | $0.96 | $1.152 |
| **Tạm tính instance + 1 IPv4 + 8 GB gp3** | **≈ $7.36/tháng** | **≈ $8.29/tháng** |
| **Tạm tính instance + 1 IPv4 + 12 GB gp3** | **≈ $7.68/tháng** | **≈ $8.67/tháng** |

Instance rates lấy trực tiếp từ JSON chính thức của AWS: [N. Virginia](https://b0.p.awsstatic.com/pricing/2.0/meteredUnitMaps/ec2/USD/current/ec2-ondemand-without-sec-sel/US%20East%20%28N.%20Virginia%29/Linux/index.json), [Sydney](https://b0.p.awsstatic.com/pricing/2.0/meteredUnitMaps/ec2/USD/current/ec2-ondemand-without-sec-sel/Asia%20Pacific%20%28Sydney%29/Linux/index.json). Dữ liệu đọc được có publication date `2026-10-29T17:56:34Z`. gp3 rates đối chiếu JSON [AWS EBS](https://b0.p.awsstatic.com/pricing/2.0/meteredUnitMaps/ec2/USD/current/ebs.json); cách tính xem [EBS pricing](https://aws.amazon.com/ebs/pricing/).

8/12 GB là ví dụ tổng dung lượng provisioned, bao gồm root và data nếu bạn tách volume; bạn tự chọn theo AMI, image và dữ liệu. Ví dụ Sydney 12 GB: `730 × 0.0053 + 730 × 0.005 + 12 × 0.096 = $8.671`. EBS tính trên dung lượng đã cấp, kể cả phần chưa dùng. Hai hostname dùng chung một IPv4 không cần thêm IP. AWS hiện tính $0.005/IP-giờ cho public IPv4 đang dùng và idle. [VPC pricing](https://aws.amazon.com/vpc/pricing/#Public_IPv4_Address).

Tổng trên chưa gồm CPU surplus, snapshot/backup, data transfer, registry, DNS/domain, log/monitoring hoặc frontend/Jenkins ở máy khác. Giữ gp3 ở mức IOPS/throughput cơ bản nếu chưa cần nâng. Trước khi bấm tạo, kiểm tra lại region trong [AWS Pricing Calculator](https://calculator.aws/) và đặt cảnh báo chi phí. Đây là dự toán, không phải mức trần hóa đơn.

Stop instance giúp ngừng compute thông thường, nhưng EBS/snapshot và Elastic IP giữ lại vẫn có thể tính tiền. Public IPv4 tự cấp có thể đổi khi start lại; Elastic IP giữ địa chỉ nhưng có phí cả khi idle. Xem [EC2 stop/start](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/Stop_Start.html) và bảng IPv4 ở trên.

## 3. Chọn Standard hoặc Unlimited một cách chủ động

T4g mặc định dùng Unlimited. Nếu CPU chạy cao kéo dài vượt mức được bù bằng credits, T4g Linux có phí surplus **$0.04/vCPU-giờ**, áp dụng qua cơ chế tính credits của AWS. Với mục tiêu giữ chi phí dễ đoán, có thể chọn **Standard**: khi hết credits, CPU giảm về baseline nên API chậm hơn. [Unlimited mode](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/burstable-performance-instances-unlimited-mode.html), [CPU credit pricing](https://aws.amazon.com/ec2/pricing/on-demand/#T2/T3/T4g_Unlimited_Mode_Pricing).

Hai vCPU là khả năng burst, không phải quyền dùng liên tục hai core đầy tải với giá nano cơ bản. Theo dõi `CPUUtilization`, `CPUCreditBalance`; nếu dùng Unlimited, xem thêm `CPUSurplusCreditBalance` và `CPUSurplusCreditsCharged`.

Trong EC2 Console, kiểm tra **Credit specification** lúc launch và sau khi tạo. Nếu bạn tự dùng AWS CLI, có thể kiểm tra bằng lệnh sau sau khi thay ID/region; xem [AWS credit configuration](https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/burstable-performance-instances-how-to.html).

```bash
aws ec2 describe-instance-credit-specifications \
  --region ap-southeast-2 \
  --instance-ids i-REPLACE_WITH_YOUR_INSTANCE_ID
```

Bạn tự chọn Standard trong Console nếu đó là chính sách chi phí mong muốn. Tài liệu không thay đổi chế độ instance giúp bạn.

## 4. Điểm bắt đầu để giảm RAM

Các giá trị dưới đây là **đề xuất ban đầu cho một demo**, không phải cấu hình đã benchmark hay mặc định của nhà cung cấp. Bạn tự đưa chúng vào file production mình viết và đo trước khi tăng tải.

### PHP-FPM/Nginx

| Cấu hình | Khởi điểm |
| --- | --- |
| `pm` | `ondemand` |
| `pm.max_children` | `1` |
| `pm.process_idle_timeout` | `10s` |
| `pm.max_requests` | `200` |
| PHP `memory_limit` | thử `128M`, đo RSS thực tế |
| OPcache memory | thử `32M`, theo dõi đầy cache |

`ondemand` chỉ tạo child khi có request và thu hồi child idle; `pm.max_children=1` giới hạn một request PHP xử lý tại một thời điểm. Request đồng thời sẽ xếp hàng. [PHP-FPM configuration](https://www.php.net/manual/en/install.fpm.configuration.php).

Đây là giới hạn bộ nhớ PHP của một request, không phải toàn bộ RAM container. Local hiện dùng `memory_limit=256M`; không sao chép nguyên cấu hình local rồi giả định vừa nano. Cần chừa RAM cho FPM master, OPcache, PostgreSQL, Nginx, Docker và OS. Bật `APP_DEBUG=false`, dùng vendor/cache production đã chuẩn bị và giới hạn log rotation.

Các mức upload 20 MiB/document, 25 MiB/ZIP của API không phải cam kết nano xử lý được trong lúc có tải khác. Cần thử thực tế, đặc biệt giải nén tool, replay lớn và migration. Khi cần thực hiện tác vụ quản trị tốn RAM, bạn có thể tạm nâng instance cùng kiến trúc ARM64 rồi quay lại nano sau khi kiểm tra. Không tăng số FPM child chỉ vì thấy instance có hai vCPU.

### PostgreSQL 17

| Cấu hình | Khởi điểm |
| --- | --- |
| `max_connections` | `10` |
| `shared_buffers` | `32MB` |
| `work_mem` | `1MB` |
| `maintenance_work_mem` | `16MB` |
| `autovacuum_work_mem` | `16MB` |
| `autovacuum_max_workers` | `1` |
| `max_parallel_workers_per_gather` | `0` |
| `max_parallel_maintenance_workers` | `0` |

Tài liệu PostgreSQL giải thích `work_mem` có thể được dùng nhiều lần trong một query và nhiều connection; tăng connections cũng tăng tài nguyên cấp phát. Đây là lý do giảm concurrency trước khi tăng memory trên máy nhỏ. [Memory settings](https://www.postgresql.org/docs/17/runtime-config-resource.html), [connections](https://www.postgresql.org/docs/17/runtime-config-connection.html), [autovacuum](https://www.postgresql.org/docs/17/runtime-config-autovacuum.html).

Giữ autovacuum hoạt động, cùng các bảo đảm ghi bền vững `fsync`, `full_page_writes`, `synchronous_commit`; xem [PostgreSQL WAL settings](https://www.postgresql.org/docs/17/runtime-config-wal.html). Dành chỗ cho connection quản trị/migration; với default ba superuser-reserved slots, `max_connections=10` không có nghĩa là mười connection ứng dụng thường. Dùng database sessions của Laravel và cache nhẹ theo ứng dụng; giai đoạn này không cần thêm Redis hoặc worker daemon nếu backend đang chạy queue `sync`.

### Swap và giới hạn container

Swap khoảng 1 GiB trên EBS có thể là lớp đệm tùy chọn khi RAM tăng đột ngột. Nếu bạn cấu hình swapfile, kiểm tra file/swap hiện có trước, dùng quyền `0600`, và kiểm tra cấu hình mount/reboot. Swap không biến nano thành máy RAM lớn: khi `vmstat` có swap-in/out liên tục và latency tăng, cần giảm tải hoặc tăng RAM.

Khi tự đặt memory/swap limits của Compose, chừa phần cho OS và Docker, rồi kiểm tra giới hạn thực bằng `docker inspect`. Docker mô tả rõ cách `memory`/`memory-swap` tương tác và nguy cơ OOM trong [resource constraints](https://docs.docker.com/engine/containers/resource_constraints/). Không chọn vài con số container cộng lại sát 512 MiB rồi coi đó là đủ cho cả host.

## 5. Các bước bạn tự làm

Trước hết chạy backend theo [README local](../server/README.md). Mặc định API chính là `http://localhost:8000`, `/api/v1/health` kiểm tra database. `http://127.0.0.1:8000` được dành cho hostname runtime tool trong cấu hình local; gọi auth/API nghiệp vụ ở hostname này có thể nhận `404` do ranh giới runtime. Nếu đổi port public, đồng bộ URL browser, `APP_URL`, `FRONTEND_URL`, `TOOL_RUNTIME_ORIGIN`, CORS và Sanctum với port/hostname thực tế; port nội bộ Docker có thể vẫn là 8000.

1. **Chuẩn bị ở local/build agent:** hoàn thành Dockerfile production và Compose production theo [hướng dẫn Jenkins/EC2](ec2-jenkins-guide.md). Build/test ở máy đủ RAM; tạo image `linux/arm64`, gồm source + vendor. Docker hỗ trợ chọn platform qua Buildx; nên ưu tiên ARM native nếu build cần compile nhiều. [Docker multi-platform](https://docs.docker.com/build/building/multi-platform/).

   Ví dụ lệnh này dành cho **máy build**, sau khi bạn đã tự viết Dockerfile và cấu hình registry:

   ```bash
   docker buildx build --platform linux/arm64 \
     -f server/Dockerfile.production \
     -t YOUR_REGISTRY/lernumi-api:YOUR_COMMIT_SHA \
     --push server
   docker buildx imagetools inspect YOUR_REGISTRY/lernumi-api:YOUR_COMMIT_SHA
   ```

   Kiểm tra cả base image PHP/PostgreSQL/Nginx đã chọn hỗ trợ ARM64; không mang thư mục binary/dependency build cho amd64 sang ARM64.

2. **Tự launch EC2:** Ubuntu LTS ARM64, `t4g.nano`, region đã so giá, credit mode đã chọn, gp3 và security group. Mở 80/443; giới hạn SSH theo IP của bạn; PostgreSQL không có inbound public. Chưa cần ALB/NAT/RDS cho cấu hình một instance này.

3. **Kiểm tra host sau khi SSH:**

   ```bash
   uname -m
   free -m
   df -h
   lsblk -f
   swapon --show
   ```

   Kiến trúc mong đợi là `aarch64`; RAM khả dụng thực tế sẽ ít hơn số RAM quảng cáo. Cài Docker/Compose theo [Docker Ubuntu](https://docs.docker.com/engine/install/ubuntu/); Jenkins agent không được cài lên nano.

4. **Chuẩn bị storage:** mount EBS và giữ tên project/volume ổn định. Persist PostgreSQL data directory và Laravel `storage/app`; kiểm tra mount trước khi khởi động để không vô tình tạo database rỗng trên thư mục khác. Chọn rõ chính sách giữ volume khi terminate và backup ngoài instance. Có thể dùng root EBS cho demo tối giản nếu bạn hiểu vòng đời của nó; volume dữ liệu tách riêng giúp thay máy dễ hơn nhưng cũng cần cộng đủ dung lượng vào giá.

5. **Tự đặt secrets/config:** tạo `APP_KEY` một lần, giữ `TOOL_TOKEN_SECRET` ổn định, database password riêng, `APP_ENV=production`, `APP_DEBUG=false`, sessions database. TLS và `SESSION_DOMAIN=null` theo hướng dẫn chung; hostname tool tách khỏi platform cookie. Không dùng credential demo cho server public.

6. **Pull và migrate:** dùng Compose production bạn đã viết, image theo commit/digest. Đợi database healthy, backup theo kế hoạch rồi chạy migration một lần trong khóa deployment. Trên nano, có thể tạm dừng traffic/PHP trong lúc migration để chừa RAM. Các lệnh sau chỉ là ví dụ nếu service của bạn tên `api`, `postgres`:

   ```bash
   docker compose -p lernumi-prod -f compose.production.yaml pull
   docker compose -p lernumi-prod -f compose.production.yaml up -d postgres
   docker compose -p lernumi-prod -f compose.production.yaml run --rm api php artisan migrate --force
   docker compose -p lernumi-prod -f compose.production.yaml up -d
   ```

   Chạy từng bước, kiểm tra exit status rồi mới tiếp tục. Phải có file production và secrets đúng trước; repo local không tự biến thành production bằng các lệnh này. Không chạy cả hai bản PHP release cùng lúc trên nano để giả lập blue/green khi chưa đo đủ RAM.

7. **Smoke test và đo:** `/up`, `/api/v1/health`, đăng nhập + CSRF, tạo/xóa workspace tạm, upload/download file nhỏ rồi tăng dần, thử tool và reboot kiểm tra dữ liệu. Theo dõi trong lúc dùng:

   ```bash
   docker stats --no-stream
   free -m
   vmstat 1
   ```

   Kiểm tra restart count/OOM trong Docker và kernel log; xem CPU credits ở CloudWatch. Health `200` lúc idle chưa chứng minh đủ RAM khi upload, migrate hoặc nhiều request.

8. **Backup và vận hành:** lập lịch dump PostgreSQL, sao lưu file storage và restore thử ở môi trường khác; theo dõi dung lượng log, image cũ, WAL và snapshot retention. Với EBS snapshot cần bảo đảm tính nhất quán của write đang đệm, theo [AWS snapshot guidance](https://docs.aws.amazon.com/ebs/latest/userguide/ebs-creating-snapshot.html). Chỉ dọn image/volume đã xác định rõ; giữ nguyên volume dữ liệu khi redeploy.

Nếu xuất hiện OOM/restart, swap kéo dài, credits cạn thường xuyên hoặc request xếp hàng quá lâu, lựa chọn tiếp theo là tăng RAM/tách workload, rồi đo lại. Giữ nano là cấu hình demo có kiểm chứng, không gán cho nó khả năng phục vụ production nhiều người dùng chỉ dựa trên giá instance.
