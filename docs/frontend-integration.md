# Nối frontend với Lernumi API

Backend Laravel phục vụ nghiệp vụ ở `/api/v1`. Bạn tự nối các màn hình trong `client/`; tài liệu này mô tả hợp đồng để làm lần lượt. Danh sách endpoint đầy đủ nằm trong [API reference](api-reference.md); có thể kiểm tra trực tiếp bằng `docker compose exec -T api php artisan route:list --path=api`.

## 1. Chạy local và chọn cùng hostname

- Frontend: `http://localhost:3000`.
- Backend: `http://localhost:8000`.
- Dùng nhất quán `localhost`; cookie của `localhost` không phải cookie của `127.0.0.1`.
- `CORS_ALLOWED_ORIGINS` chứa origin frontend chính xác, gồm scheme và port. `SANCTUM_STATEFUL_DOMAINS` chứa hostname frontend cùng port, không có scheme: `localhost:3000`.
- Với local HTTP, giữ `SESSION_DOMAIN=null`, `SESSION_SECURE_COOKIE=false`. Dùng session database để quản lý đăng nhập trên nhiều thiết bị.

Bạn có thể tự thêm reverse proxy/Next rewrite cho `/api/*` và `/sanctum/csrf-cookie` để browser gọi cùng origin. Đường dẫn CSRF nằm ngoài `/api/v1`.

Production nên có hai hostname: `app.example.com` phục vụ frontend và proxy `/api/*`, `/sanctum/csrf-cookie` tới Laravel; `tools.example.com` dành riêng cho runtime. Khi đó helper browser dùng origin hiện tại và `SESSION_DOMAIN=null` giữ cookie host-only. Không đặt cookie domain thành `.example.com`.

Nếu tách API thành `api.example.com`, cookie XSRF host-only của API không đọc được qua `document.cookie` trên `app.example.com`. Helper dưới đây áp dụng local cùng hostname khác port, hoặc production có proxy cùng origin. Muốn mô hình ba hostname phải tự bổ sung BFF/proxy hay cơ chế cấp CSRF token phù hợp; chỉ chỉnh CORS chưa đủ.

## 2. Đăng nhập bằng cookie Sanctum

Gọi `GET /sanctum/csrf-cookie` trước khi đăng ký/đăng nhập. Với request ghi dữ liệu, gửi cookie và header `X-XSRF-TOKEN` lấy từ cookie `XSRF-TOKEN` sau khi URL decode. Đây là luồng SPA của [Laravel Sanctum](https://laravel.com/docs/13.x/sanctum#csrf-protection).

Ví dụ dưới đây chỉ là mẫu để bạn đưa vào frontend sau khi chọn vị trí đặt helper:

```ts
const origin = 'http://localhost:8000';

function csrfToken(): string {
  const cookie = document.cookie.split('; ').find(v => v.startsWith('XSRF-TOKEN='));
  return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}

async function api(path: string, method = 'GET', input?: unknown) {
  const headers: Record<string, string> = { Accept: 'application/json' };
  if (input !== undefined) headers['Content-Type'] = 'application/json';
  if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
    headers['X-XSRF-TOKEN'] = csrfToken();
  }
  const response = await fetch(`${origin}/api/v1${path}`, {
    method,
    credentials: 'include',
    headers,
    ...(input !== undefined ? { body: JSON.stringify(input) } : {}),
  });
  const body = response.status === 204 ? null : await response.json();
  if (!response.ok) throw { status: response.status, body };
  return body;
}

await fetch(`${origin}/sanctum/csrf-cookie`, {
  credentials: 'include',
  headers: { Accept: 'application/json' },
});

// Các giá trị email/password lấy từ form.
const session = await api('/auth/login', 'POST', { email, password });
const { user, contexts, isAdmin } = session.data;
```

`POST /auth/register` nhận `name`, `email`, `password`, `password_confirmation`; mật khẩu tối thiểu 10 ký tự, có chữ và số. Có thể gửi `firstName`, `lastName`. Đăng ký thành công đăng nhập luôn và trả `201`. Tài khoản mới cần được cấp role/scope để có quyền học trong institution.

Sau reload trang, gọi `GET /auth/session` để khôi phục `{data: {user, contexts, isAdmin}}`. `POST /auth/logout` và `/auth/logout-all` trả `204`. Đổi mật khẩu thành công sẽ kết thúc các phiên; đưa người dùng về đăng nhập.

Với upload, tự tạo `FormData`, dùng `credentials: 'include'` và `X-XSRF-TOKEN`; để browser tự đặt `Content-Type` cùng multipart boundary. Không dùng helper JSON phía trên cho `FormData` hoặc download binary.

Nếu tải dữ liệu trong Next Server Component, chuyển tiếp `Cookie` của request tới Laravel, đặt `Origin` đúng frontend cho stateful API và dùng `cache: 'no-store'` cho dữ liệu cá nhân. Chỉ chuyển tiếp tới backend đã cấu hình; không chia sẻ cache giữa người dùng.

## 3. Đọc response đúng theo endpoint

Đa số ID nghiệp vụ dùng UUID; permission nhận diện bằng code, ID personal access token của Sanctum là số và ID phiên đăng nhập là chuỗi. Field JSON dùng camelCase; ngày giờ thông thường là ISO 8601 có múi giờ. Riêng `lastActivity` trong danh sách phiên là Unix timestamp theo giây. Ngày kế hoạch là ngày dân sự `YYYY-MM-DD`, giữ nguyên chuỗi này thay vì đổi qua timezone của JavaScript.

Resource CRUD thông thường trả `{data: {...}}`; danh sách Laravel có `{data: [...], links: {...}, meta: {...}}`. Các API học tập có payload theo nghiệp vụ:

| API | Payload chính |
| --- | --- |
| `GET /auth/session` | `data.user`, `data.contexts`, `data.isAdmin` |
| `GET /me/offerings` | `offerings[]`, `meta` |
| `GET /me/offerings/{id}` | `offering`, gồm `assessments` đã công bố |
| `GET /me/assessments/{id}` | `assessment`, gồm offering và tools |
| `GET /me/attempts?assessmentId=...` | `attempts[]`, `meta` |
| `POST /me/attempts` | `attempt`, gồm `workspaceId` |
| `GET /me/attempts/{id}` | `attempt`, `assessment`, `result` |
| `GET /me/calendar` | `terms`, `offerings`, `events`, `plans`, `timeZone` |
| `GET /workspaces/{id}` | `data`, `documents`, `tools`, `breadcrumbs`, `currentFolderId` |
| `GET /institutions/{id}/marking` | `terms[]`, bên trong có subjects, students, attempts |
| `GET /institutions/{id}/attempts/{id}` | `attempt`, `marks`, `result`, `sessions`, `replay`, `flags`, quyền thao tác |

Không tự unwrap mọi response thành `body.data`: endpoint học tập và tool có envelope riêng. Giá trị `score`/`outOf` của mark là chuỗi decimal để giữ độ chính xác.

## 4. Nối màn hình theo thứ tự

| Màn hình | HTTP và đường dẫn tương đối với `/api/v1` |
| --- | --- |
| Tài khoản | `GET/PATCH /me`; `PUT /auth/password`; `POST/DELETE /me/avatar`; phiên ở `/auth/sessions` |
| Institution | CRUD admin `/admin/institutions`; danh sách/phạm vi ở `/institutions` |
| Tìm nơi để tham gia | `GET /institution-directory`, rồi `GET /institutions/{institution}/requestable-roles`; `POST /institutions/{institution}/join` theo cấu hình role |
| Subject | CRUD `/institutions/{institution}/subjects` |
| Study term và period | CRUD `/institutions/{institution}/terms`; CRUD `.../terms/{term}/periods` |
| Offering và assessment | CRUD `/institutions/{institution}/offerings`; CRUD `.../offerings/{offering}/assessments` |
| Role và thành viên | `/institutions/{institution}/roles`, `/members`, `/role-assignments`; gán/xóa offering scope ở `.../role-assignments/{assignment}/scopes` |
| Xin quyền | `/institutions/{institution}/role-requests`, `/scope-requests`; người duyệt `PATCH` request; người dùng xem `/me/role-requests`, `/me/scope-requests` |
| Mã tham gia | Tạo/xóa `/institutions/{institution}/scope-codes`; nhập mã `POST /scope-codes/redeem` |
| Bắt đầu bài | `POST /me/attempts` với `{assessmentId}`; bài nhóm thêm `groupId` |
| Nhóm | `GET/POST /me/assessments/{assessment}/groups`; `GET/PATCH/PUT/DELETE .../groups/{group}`; gửi `name`, `memberIds` |
| Nộp bài | `POST /me/attempts/{attempt}/submit`; workspace đã nộp chuyển sang chỉ đọc |
| Workspace | CRUD `/workspaces`; folder/file ở `.../documents`; nội dung ở `.../documents/{document}/content` |
| Lịch cá nhân | `GET/POST /me/calendar-events`; `PATCH/PUT/DELETE /me/calendar-events/{event}` |
| Kế hoạch làm bài | `PUT/DELETE /me/assessment-plans/{assessment}` với `startDate`, `endDate` |
| Chấm bài | `POST .../attempts/{attempt}/marks` mở/tiếp tục draft; `PATCH .../marks/{mark}` lưu; `POST .../marks/{mark}/submit` khóa bản chấm |
| Kết quả chính thức | `PUT .../attempts/{attempt}/result` với `markId`, `note?`; `DELETE` bỏ công bố; cần quyền riêng |
| Quản trị tool | `/admin/tools`; upload ZIP `POST .../{tool}/versions`; kích hoạt `POST .../{tool}/versions/{version}/activate` |

CRUD tương ứng `GET` danh sách/chi tiết, `POST` tạo, `PUT/PATCH` cập nhật, `DELETE` xóa; route cụ thể và field bắt buộc xem API reference/FormRequest tương ứng. Các route chấm bài có prefix `/institutions/{institution}`.

Với context switcher, lấy `roleAssignmentId`, permissions, institution và offeringIds từ `contexts`. Truyền `roleAssignmentId` vào query của API học tập để giới hạn theo role đang chọn. Quyền server vẫn quyết định mọi thao tác. Không cho rằng đổi URL hay ẩn nút đã bảo vệ dữ liệu.

Nhóm thuộc một assessment; một người chỉ ở một nhóm trong assessment đó. Sau khi có attempt, danh sách thành viên được giữ cố định để bảo toàn quyền sở hữu bài. Mọi thành viên dùng chung attempt/workspace và có thể nộp bài khi đủ quyền.

## 5. Tool runtime và đồng bộ file

Tool dùng hợp đồng riêng `/api/tool/v1`, với Bearer capability token, không dùng token đăng nhập SPA. Gọi `POST /api/tool/v1/sessions` bằng phiên SPA để nhận `runtimeUrl`; chuyển browser tới URL đó. Mode `WORK` gửi `attemptId`, `toolId`; `PERSONAL` gửi `workspaceId`, `toolId`; `REPLAY` gửi `workSessionId`. Có thể gửi `roleAssignmentId` để chọn context; kiểm tra API reference cho body cuối cùng.

Runtime nằm trên hostname riêng. Token mặc định sống 300 giây theo `server/config/tools.php`; refresh qua `/api/tool/v1/token` trước khi hết hạn. Dùng nguyên URL bootstrap server trả về, tránh đưa token vào query/log. Personal workspace không có lịch sử ghi hoạt động; replay chỉ đọc.

Khi mở replay, server chọn **phiên bản tool đang active tại lúc mở**, còn work session được ghi vẫn giữ `toolVersionId` gốc. Runtime đã mở tiếp tục dùng phiên bản đã chọn khi refresh token. `currentRuntimeCompatible` trong danh sách replay cho biết phiên bản ghi có trùng phiên bản runtime hiện tại hay không; tool cần xử lý tương thích dữ liệu cũ thay vì giả định hai phiên bản luôn giống nhau.

Khi đọc file, lưu ETag/hash. `PUT .../documents/{document}/content` gửi raw bytes cùng `If-Match: "<hash>"`. Nếu `412`, tải bản hiện tại và để người dùng giải quyết thay đổi; không tự ghi đè. Sau upload/folder/move/delete, tải lại danh sách đang mở. Event batch có `batchId` và sequence; retry giữ nguyên batchId/payload để có tính idempotent.

## 6. Xử lý lỗi trên giao diện

| HTTP | Cách xử lý |
| --- | --- |
| `401` | Đưa về đăng nhập và giữ đường dẫn quay lại hợp lệ |
| `403` | Hiện thiếu quyền; làm mới context khi quyền vừa thay đổi |
| `404` | Resource không tồn tại hoặc không thuộc phạm vi được phép |
| `409` | Đọc message, tải trạng thái mới; ví dụ bài đã nộp, nhóm đã khóa, trùng tên |
| `412` | Xung đột nội dung file; đối chiếu bản hiện tại trước khi lưu lại |
| `419` | Làm mới CSRF/session rồi để người dùng thực hiện lại thao tác ghi |
| `422` | Gắn `errors[field]` vào đúng input; giữ nguyên form |
| `428` | Thiếu `If-Match` khi lưu nội dung file |
| `429` | Báo giới hạn tần suất và đợi theo response |

Laravel validation thường có `{message, errors: {field: [message]}}`; transport tool có thể có `{error: {code, message, details}}`. Helper xử lý cả hai khi hiển thị lỗi. Response `204` không có JSON.
