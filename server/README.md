
# Lernumi API — Subject

REST API mẫu cho domain **môn học** trên Laravel và PostgreSQL. Một Subject thuộc một Institution và tồn tại qua nhiều kỳ học; lớp mở theo kỳ (`Offering`) sẽ là domain riêng. Mã môn duy nhất trong từng trường, nên hai trường có thể cùng có `COMP1000`.

API này chưa có xác thực/RBAC và chưa nối với frontend. Scoped route binding bảo đảm môn nằm trong trường trên URL; quyền của người gọi cần được bổ sung khi triển khai luồng đăng nhập.

## Chạy bằng Docker

Chạy các lệnh sau tại thư mục gốc project. Chỉ cần Docker Desktop đang chạy; không cần cài PHP hay Composer trên máy.

```sh
docker compose build
docker compose run --rm api composer run setup
docker compose run --rm api php artisan migrate --seed
docker compose up -d
```

API: `http://localhost:8000`. Health check: `GET /up`.

Container chạy `artisan serve --no-reload` để giữ các biến môi trường do Compose cấp. Khi sửa `.env`, chạy `docker compose restart api`.

PostgreSQL được lưu trong named volume, có health check trước khi API kết nối. Từ máy host: `127.0.0.1:5433`, database `lernumi`, username `lernumi`, password `lernumi_dev`. Trong Docker, API kết nối `postgres:5432`; Compose ghi đè giá trị kết nối trong `.env`.

Seed có thể chạy lại, tạo trường demo `11111111-1111-4111-8111-111111111111` và hai môn `COMP1000`, `COMP1010`.

```sh
docker compose exec api composer test
docker compose down
```

Test dùng PostgreSQL database riêng `lernumi_testing`, được tạo khi khởi tạo volume. `docker compose down` dừng dịch vụ và giữ dữ liệu.

## Endpoint

Các path dưới đây nối sau `/api/v1/institutions/{institution}`. `{institution}` và `{subject}` là UUID. Gửi `Accept: application/json`; request có body dùng `Content-Type: application/json`.

| Method | Path | Kết quả |
| --- | --- | --- |
| GET | `/subjects` | Danh sách có phân trang, `200` |
| POST | `/subjects` | Tạo môn, `201` và header `Location` |
| GET | `/subjects/{subject}` | Chi tiết môn, `200` |
| PUT | `/subjects/{subject}` | Thay toàn bộ nội dung có thể sửa, `200` |
| PATCH | `/subjects/{subject}` | Sửa những trường được gửi, `200` |
| DELETE | `/subjects/{subject}` | Xóa môn, `204`, không có body |

Danh sách hỗ trợ `search`, `page`, `per_page`: tìm chuỗi con trong mã/tên môn, không phân biệt hoa thường; `%` và `_` là ký tự thường. `page` là số nguyên dương, `per_page` từ 1 đến 100, mặc định 15. Kết quả sắp theo mã môn rồi ID, gồm `data`, `links`, `meta` theo chuẩn Laravel.

Body tạo môn:

```json
{
  "subjectCode": "COMP2000",
  "subjectName": "Data Structures",
  "subjectDescription": "Lists, trees and graphs."
}
```

- `subjectCode`: bắt buộc, 2–20 ký tự chữ ASCII, số hoặc dấu `-`; tự trim và đổi thành chữ hoa; duy nhất trong trường.
- `subjectName`: bắt buộc, trim, tối đa 200 ký tự và không được rỗng.
- `subjectDescription`: có thể bỏ qua hoặc `null`, tối đa 1.000 ký tự; chuỗi trắng được đổi thành `null`.

`PUT` yêu cầu mã và tên; bỏ qua mô tả sẽ đặt mô tả thành `null`. `PATCH` giữ nguyên những trường không gửi. Trường chứa môn được xác định từ URL, không thể chuyển trường bằng body.

Phản hồi một môn được bọc trong `data`, gồm `id`, `institutionId`, ba trường nội dung ở trên, `createdAt`, `updatedAt`. Lỗi validation trả `422` với `message` và `errors` theo tên trường. UUID không hợp lệ, bản ghi không tồn tại hoặc môn thuộc trường khác trả `404`.

Thử nhanh trong shell Bash:

```sh
curl 'http://localhost:8000/api/v1/institutions/11111111-1111-4111-8111-111111111111/subjects?search=comp&per_page=10' \
  -H 'Accept: application/json'

curl 'http://localhost:8000/api/v1/institutions/11111111-1111-4111-8111-111111111111/subjects' \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{"subjectCode":"COMP2000","subjectName":"Data Structures","subjectDescription":null}'
```

Trên Windows, có thể mở [subjects.http](subjects.http) bằng VS Code REST Client hoặc JetBrains HTTP Client, gửi request tạo rồi dán ID trả về vào biến `subjectId` để thử tiếp.

## Đọc code

Luồng request: **Route → FormRequest → Controller/Eloquent → Resource**.

Các FormRequest được gom theo domain trong `app/Http/Requests/`; request của môn học nằm trong `Subjects/`.

| Thành phần | Vai trò |
| --- | --- |
| [routes/api.php](routes/api.php) | Version API, URL lồng theo trường, scoped binding và UUID constraints |
| [SubjectRequest](app/Http/Requests/Subjects/SubjectRequest.php) | Chuẩn hóa mã, validation chung, ánh xạ JSON camelCase sang cột snake_case |
| [StoreSubjectRequest](app/Http/Requests/Subjects/StoreSubjectRequest.php), [UpdateSubjectRequest](app/Http/Requests/Subjects/UpdateSubjectRequest.php), [IndexSubjectRequest](app/Http/Requests/Subjects/IndexSubjectRequest.php) | Quy tắc riêng cho tạo, sửa và truy vấn danh sách |
| [SubjectController](app/Http/Controllers/SubjectController.php) | CRUD qua quan hệ của trường, lọc/phân trang, HTTP status; chuyển xung đột mã do ghi đồng thời thành lỗi `422` |
| [Subject](app/Models/Subject.php), [Institution](app/Models/Institution.php) | Model Eloquent, UUID và quan hệ dữ liệu |
| [SubjectResource](app/Http/Resources/SubjectResource.php) | Hợp đồng JSON phản hồi |
| [Migration môn học](database/migrations/2026_10_30_000002_create_subjects_table.php) | Khóa ngoại và unique constraint `(institution_id, subject_code)` tại database |
| [tests](tests) | Kiểm thử HTTP trên PostgreSQL, gồm CRUD, validation và ranh giới giữa các trường |

Domain hiện chỉ cần Eloquent và controller nhỏ; chưa thêm service/repository trung gian.
