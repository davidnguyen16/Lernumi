# Lernumi API reference

Tài liệu này mô tả backend đang được frontend Next.js sử dụng. Business API dùng **/api/v1**; API dành cho tool dùng **/api/tool/v1**. Bảng cuối tài liệu liệt kê toàn bộ route API đang đăng ký, controller xử lý và mục đích. Xem [hướng dẫn kết nối](frontend-integration.md) để đọc các helper và luồng giao diện hiện có.

Các ký hiệu như `<institution-uuid>`, `<assessment-uuid>` cần được thay bằng UUID nhận từ API. Không gửi nguyên chuỗi placeholder. Có thể thử các luồng bằng [requests.http](../server/requests.http), giữ cookie giữa các request và tự điền ID từ response. Thông tin chạy Docker và tài khoản demo chỉ nằm trong [server README](../server/README.md). Chi tiết RBAC bổ sung ở [auth-access-api.md](auth-access-api.md).

## 1. URL, đăng nhập và header

Local: frontend http://localhost:3001, backend http://localhost:8000. Dùng localhost cho business API. Host 127.0.0.1:8000 được dùng làm origin riêng của tool trong cấu hình local; gọi business API trên host đó có thể nhận 404 theo ranh giới origin.

Production nên phục vụ app và /api cùng origin qua reverse proxy, ví dụ https://app.example.com; tool dùng hostname khác, ví dụ https://tools.example.net. Giữ SESSION_DOMAIN=null để cookie đăng nhập chỉ thuộc host app. Sanctum stateful domains chỉ chứa app/frontend, không thêm hostname tool. Khác port trên cùng hostname chưa tạo được ranh giới cookie cho tool.

Browser dùng Sanctum cookie session:

1. GET /sanctum/csrf-cookie với credentials: include.
2. Đọc cookie XSRF-TOKEN, URL-decode giá trị rồi gửi header X-XSRF-TOKEN cho POST/PUT/PATCH/DELETE, kể cả login/register.
3. POST /api/v1/auth/login với JSON email/password. Giữ credentials: include trên mọi request.
4. GET /api/v1/auth/session để lấy user, contexts và isAdmin. Sau reload frontend, gọi lại endpoint này.
5. POST /api/v1/auth/logout khi đăng xuất; logout-all thu hồi mọi phiên/token/runtime của tài khoản.

Ví dụ HTTP tương đương với helper frontend hiện có tại `client/src/lib/api/client.ts`:

~~~javascript
await fetch('/sanctum/csrf-cookie', { credentials: 'include' });

const xsrf = () => decodeURIComponent(
  document.cookie.split('; ').find(part => part.startsWith('XSRF-TOKEN='))?.slice(11) ?? ''
);

const response = await fetch('/api/v1/auth/login', {
  method: 'POST',
  credentials: 'include',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-XSRF-TOKEN': xsrf()
  },
  body: JSON.stringify({ email, password })
});
const payload = await response.json();
if (!response.ok) throw payload;
~~~

Frontend hiện proxy `/api/*` và `/sanctum/*` tới Laravel để browser gọi cùng origin. Nếu gọi localhost:8000 trực tiếp từ localhost:3001, thay URL tương ứng, bật credentialed CORS và Sanctum stateful domains đã cấu hình. Các route auth có CSRF/session riêng; business routes nhận session qua Sanctum.

Client ngoài browser có thể dùng Authorization: Bearer `<personal-access-token>` trên business routes. Token này được tạo tại POST /auth/tokens sau khi đăng nhập và xác nhận currentPassword. Đây là token tài khoản; JWT tool là loại khác và chỉ dùng cho runtime API.

**Chọn role context:** các endpoint learning/attempt/group/marking/calendar đọc query/body roleAssignmentId, ví dụ GET /me/offerings?roleAssignmentId=`<assignment-uuid>`. Gửi query này cả trên mutation nếu cần giữ cùng context. Không có header X-Role-Assignment-Id. Khi thiếu context, backend hợp các quyền hiện có của user trong đúng scope. Academic CRUD, account và workspace API kiểm quyền hiện tại của user; không giả định roleAssignmentId là bộ lọc chung cho mọi route. Launch tool nhận roleAssignmentId trong JSON và giữ context đó trong runtime.

## 2. Response và phân trang

Phần lớn resource CRUD trả JSON có data. Tuy nhiên learning và runtime có envelope riêng; frontend cần đọc đúng key thay vì luôn giả định response.data.

| Nhóm | Envelope hiện tại |
| --- | --- |
| Auth login/register/session | {data:{user,contexts,isAdmin}} |
| Academic CRUD, role CRUD, assignment, scope/code, account, tool catalog | {data:object} hoặc {data:[],links,meta} |
| Student offerings | {offerings:[],meta} / {offering:object} |
| Student assessment | {assessment:object} |
| Attempt list/create/detail | {attempts:[],meta} / {attempt:object}; detail thêm assessment,result,sessions,canReplay |
| Groups | {groups:[]} / {group:object} |
| Marking overview/detail | {terms:[]} / {attempt,assessment,student,marks,result,flags,sessions,replay,canViewMarks,canReplay,canMark,canFinalise} |
| Mark/result mutation | {mark:object} / {result:object} |
| Calendar | {events:[]} / {event:object}; {plans:[]} / {plan:object}; aggregate xem phần calendar |
| Workspace show | {data:workspace,currentFolderId,breadcrumbs,documents,tools} |
| Business document create/update | {data:document}; list {documents:[],nextCursor} |
| Runtime | JSON không bọc data; xem từng endpoint bên dưới |
| Document content, avatar, logo, ZIP download | Bytes trực tiếp với Content-Type phù hợp |
| Xóa/đăng xuất thành công | Thường 204, không có JSON body |

Resource fields dùng camelCase. Laravel pagination metadata thường dùng current_page,per_page,last_page,total và có links; access member/audit list dùng currentPage,lastPage,perPage,total. Learning list meta dùng snake_case. Điểm score/outOf của mark được serialize thành decimal string, ví dụ "82.50"; weight của assessment là number.

Academic list mặc định 15 phần tử, nhận page/per_page và alias perPage; sort dùng tên field hoặc dấu trừ, ví dụ sort=-year. Từng controller chỉ áp dụng sort mà resource đó hỗ trợ. Subject nhận sort=subjectCode,name,createdAt và chiều giảm dần. Tool/workspace list nhận per_page, page. Access list nhận perPage, page (mặc định 20). Learning nhận perPage, page (mặc định 15). Cursor document/replay có limit riêng.

## 3. Account, session và onboarding

Đăng ký:

~~~json
{
  "firstName": "An",
  "lastName": "Nguyen",
  "email": "an@example.test",
  "password": "<your-password>",
  "passwordConfirmation": "<same-password>"
}
~~~

Có thể gửi name thay cho firstName/lastName. Password cần ít nhất 10 ký tự, có chữ và số, tối đa 72 byte để tránh bcrypt cắt ngắn; password_confirmation cũng được hỗ trợ. Các field isAdmin/is_admin gửi vào register không cấp quyền admin.

Login body là {email,password,remember?}. Response session chứa user.id/name/firstName/lastName/email/emailVerified/isAdmin/isSystemAdmin/isActive/avatarUrl/createdAt; contexts gồm roleAssignmentId, institution, role, permissions, offeringIds. Offering role chưa có scope vẫn có context nhưng chưa có quyền trong offering nào.

| Thao tác | Body / hành vi |
| --- | --- |
| PATCH /me | name?,firstName?,lastName?,email?,currentPassword?; đổi email phải xác nhận currentPassword, email verification bị reset |
| PUT /auth/password | currentPassword,password,passwordConfirmation; xong phải đăng nhập lại vì mọi session/PAT/runtime bị thu hồi |
| POST /auth/forgot-password | email; luôn trả thông báo chung, tránh lộ email có tài khoản hay không |
| POST /auth/reset-password | email,token,password,passwordConfirmation; reset token dùng một lần |
| POST /auth/email/verification-notification | Gửi lại email nếu cần verify; link ký số gọi /auth/verify-email/{id}/{hash} và phải đăng nhập đúng user |
| POST /me/avatar | Multipart avatar, JPEG/PNG/WebP tối đa 5 MiB; DELETE cùng path xóa ảnh |
| GET /auth/sessions | id,ipAddress,userAgent,lastActivity,current của phiên thuộc chính user |
| DELETE /auth/sessions/{session} | Thu hồi phiên của chính user và runtime xuất phát từ phiên đó |
| POST /auth/tokens | name,currentPassword,expiresAt?; trả token một lần; mặc định 30 ngày, tối đa 1 năm |
| DELETE /auth/tokens/{token} | Xóa PAT của user, thu hồi runtime xuất phát từ PAT |

Password reset email trỏ tới FRONTEND_URL/reset-password?token=...&email=.... Frontend hiện có màn hình `/forgot-password` và `/reset-password` gọi các API tương ứng. Local mail dùng transport cấu hình trong môi trường; production cần mail transport thật.

User mới có thể GET /institution-directory?search=... để tìm institution có role cho phép request. Sau đó GET /institutions/{institution}/requestable-roles, gửi role request, chờ duyệt rồi xin offering scope hoặc redeem scope code. POST /institutions/{institution}/join tự cấp role khớp autoGrantEmailRegex và chỉ chạy khi email đã verify.

Sau khi có role trong institution, GET `/institutions/{institution}/requestable-offerings` trả danh mục offering để chọn khi xin scope, kể cả khi role chưa được cấp offering scope nào. Endpoint yêu cầu `institution.view`, không cấp quyền đọc assessment hay danh sách thành viên. Nhận `page`, `perPage` (1–100, mặc định 20), `search` (tối đa 200 ký tự, tìm trong mã/tên subject). Response là `{data:[],links,meta}`; mỗi item chỉ có `id`, `institutionId`, `subjectId`, `termId`, `subject:{subjectCode,subjectName}`, `term:{year,session}`.

Admin quản lý user qua /admin/users. Field writable: name,firstName,lastName,email,password,isAdmin,isActive. Vô hiệu hóa hoặc đổi password qua admin API thu hồi session, PAT và runtime. Không có endpoint xóa user để giữ lịch sử học tập. Admin cuối cùng đang active không thể bị disable/demote.

## 4. Academic: Institution → Subject → Term → Offering → Assessment

Institution là cơ sở; Subject là môn trong cơ sở. Term có year và session (kỳ số mấy trong năm); Period là các khoảng TEACHING/BREAK/EXAM nằm trong term. Offering là một Subject được mở ở một Term. Assessment luôn thuộc Offering.

Ví dụ body tạo resource:

| Resource / POST path | JSON body |
| --- | --- |
| /admin/institutions | {"name":"Example University","website":"https://example.edu"} |
| /institutions/{i}/subjects | {"subjectCode":"COMP1000","subjectName":"Programming","subjectDescription":"Introduction"} |
| /institutions/{i}/terms | {"year":2027,"session":1,"periods":[{"name":"Teaching weeks","type":"TEACHING","startAt":"2027-02-15T00:00:00Z","endAt":"2027-05-17T00:00:00Z"}]} |
| /institutions/{i}/terms/{term}/periods | {"name":"Mid-term break","type":"BREAK","startAt":"2027-04-05T00:00:00Z","endAt":"2027-04-12T00:00:00Z"} |
| /institutions/{i}/offerings | {"subjectId":"`<subject-uuid>`","termId":"`<term-uuid>`"} |
| /institutions/{i}/offerings/{o}/assessments | Xem JSON dưới |

~~~json
{
  "name": "Assignment 1",
  "description": "Implement the exercises.",
  "weight": 25,
  "hurdle": false,
  "isGroup": false,
  "publishAt": "2027-02-15T00:00:00Z",
  "dueAt": "2027-03-15T12:00:00Z",
  "expectedDue": "Week 4",
  "allowedToolIds": ["<tool-uuid>"]
}
~~~

Tạo thường trả 201 với data và Location; đọc/sửa trả 200; xóa trả 204. Institution create/delete dành cho system admin. Các quyền khác là institution.settings.view/update, subject.*, term.*, offering.*, assessment.* trong scope tương ứng. Offering create cần institution-level offering.create; update/delete có thể ở offering scope.

Các validation/business rule cần phản ánh trên form:

- Institution name tối đa 200, website HTTP/HTTPS tối đa 2048. Subject code được trim/in hoa, 2–20 ký tự A–Z, số hoặc "-", unique trong institution; tên tối đa 200, mô tả tối đa 1000.
- Term year 1000–9999, session 1–99, unique theo institution/year/session. Period name tối đa 120; endAt phải sau startAt. Các khoảng không bị cấm chồng nhau.
- Subject và term của offering phải cùng institution; một subject chỉ được mở một lần trong cùng term.
- Assessment name tối đa 200; weight 0–100, tối đa hai chữ số thập phân; tổng weight trong offering không được vượt 100. description tối đa 100000; expectedDue là chuỗi mô tả tối đa 500.
- dueAt phải từ publishAt trở đi nếu có cả hai. publishAt=null nghĩa là chưa xuất bản; student API chỉ trả assessment đã đến thời điểm publish.
- allowedToolIds chứa UUID tool có thật. Sau khi có group hoặc attempt, không đổi isGroup và danh sách allowedToolIds.
- Parent-child UUID khác institution hoặc offering trả 404. Resource đã được lịch sử tham chiếu thường trả 409 khi xóa.

PATCH chỉ đổi field gửi lên. Với academic PUT, gửi đủ required fields: Institution name; Subject subjectCode/subjectName; Term year/session; Period name/type/startAt/endAt; Offering subjectId/termId; Assessment name/weight. PUT có thể clear các optional field bị bỏ: website, subjectDescription, assessment optional values. PUT term thay toàn bộ periods (bỏ periods thì thành []); PATCH term chỉ thay periods nếu có gửi mảng. PUT assessment thay allowedToolIds (bỏ mảng thì thành []), nên giữ lại tool IDs hiện có khi assessment đã có activity.

POST /institutions/{i}/logo dùng multipart logo (JPEG/PNG/WebP, tối đa 5 MiB); GET trả bytes, DELETE xóa logo. Admin aggregate list /admin/subjects,/admin/terms,/admin/offerings,/admin/assessments hỗ trợ các filter institutionId/subjectId/termId/offeringId phù hợp từng danh sách.

## 5. Role, assignment, scope và yêu cầu cấp quyền

GET /permissions là catalog ổn định cho role editor. Một role định nghĩa tại đúng một institution. Tạo role:

~~~json
{
  "roleCode": "offering:student",
  "roleName": "Student",
  "scopeType": "OFFERING",
  "requestMode": "PUBLIC",
  "autoGrantEmailRegex": null,
  "permissions": ["offering.view", "assessment.view_published", "assessment.attempt", "attempt.view_own", "tool.launch", "tool_session.replay_own"],
  "includedRoleIds": [],
  "requiredRoleIds": []
}
~~~

scopeType là INSTITUTION hoặc OFFERING và không đổi sau khi tạo. Prefix roleCode phải khớp institution:/offering:. requestMode là DISABLED/PUBLIC/CONDITIONAL; CONDITIONAL cần user giữ ít nhất một active role từ requiredRoleIds. autoGrantEmailRegex dùng PCRE không có delimiter, ví dụ @example[.]edu$.

Role inheritance chỉ cho cùng institution/scope type; vòng lặp bị từ chối. *.manage bao gồm các action CRUD tương ứng, ngoại trừ offering.manage không bao gồm offering.create. assessment.attempt bao gồm attempt.start/attempt.submit; assessment.view bao gồm assessment.view_published. Người quản lý chỉ có thể cấp/sửa quyền mà chính họ có trong scope đó; system admin có quyền toàn hệ thống.

| API mutation | Body |
| --- | --- |
| POST /institutions/{i}/role-assignments | {"userId":"`<user-uuid>`","roleId":"`<role-uuid>`","offeringIds":["`<offering-uuid>`"]} |
| DELETE /institutions/{i}/role-assignments/{assignment} | Không cần body; soft revoke role và toàn bộ scope đang active |
| POST /institutions/{i}/role-assignments/{assignment}/scopes | {"offeringId":"`<offering-uuid>`"} |
| DELETE /institutions/{i}/role-assignments/{assignment}/scopes/{scope} | Soft revoke scope đó |
| POST /institutions/{i}/role-requests | {"roleId":"`<role-uuid>`","note":"Request access"} |
| PATCH /institutions/{i}/role-requests/{application} | {"decision":"APPROVED","note":"Approved after review"} hoặc REJECTED |
| POST /institutions/{i}/scope-requests | {"roleAssignmentId":"`<own-active-assignment>`","offeringId":"`<offering-uuid>`","note":"Enroll this term"} |
| PATCH /institutions/{i}/scope-requests/{application} | {"decision":"APPROVED","note":"Enrollment checked"} hoặc REJECTED |
| POST /institutions/{i}/scope-codes | {"offeringId":"`<offering-uuid>`","expiresAt":"2027-03-01T00:00:00Z"} |
| POST /scope-codes/redeem | {"code":"`<one-time-code>`","roleAssignmentId":"`<own-active-assignment>`"} |

Institution role không có offering scope. RoleAssignment là identity và lịch sử cấp/thu hồi; ScopeAssignment gắn identity OFFERING vào offering cụ thể. Không có scope nghĩa là chưa có quyền tại offering. GET /me/role-assignments,/me/role-requests,/me/scope-requests trả dữ liệu chính user.

Assignment list nhận offeringId để manager chỉ xem đúng offering; các scope khác bị loại khỏi JSON. Không gửi offeringId thì cần institution-level role_assignment.view. Members list cũng nhận offeringId để kiểm member.view tại scope đó.

Request statuses: PENDING/APPROVED/REJECTED. Chỉ xử lý một lần; non-admin không tự review request của mình. Response có user:{id,name,email}, role summary; scope request thêm offering labels. Request đã approve chứa approvedAssignmentId hoặc approvedScopeAssignmentId. Creation/review audit events giữ note.

Scope code mặc định hết hạn sau 7 ngày, tối đa một năm. data.code chỉ xuất hiện lúc tạo; database giữ hash, không trả secret trong list. Code dùng một lần; recipient phải có active offering role cùng institution, và code không được mở rộng một role mạnh hơn quyền hiện tại của issuer.

GET /institutions/{i}/audit cần audit.view; /admin/audit cần system admin. includeRevoked=true cho assignment history; status=PENDING để lọc request.

## 6. Student, group, attempt và marking

Luồng student:

1. GET /me/offerings, chọn offering rồi GET /me/offerings/{offering}.
2. GET /me/assessments/{assessment} lấy assessment đã publish và danh sách tools.
3. Với bài nhóm, POST /me/assessments/{assessment}/groups bằng {"name":"Team A","memberEmails":["friend@example.test"]} từ UI, hoặc dùng `memberIds` chứa các user UUID. Student tạo group luôn có chính họ. GET groups trả group của mình; manager có assessment.update xem toàn bộ.
4. POST /me/attempts bằng {"assessmentId":"`<assessment-uuid>`"}; bài nhóm thêm "groupId":"`<group-uuid>`". Response 201 là {attempt:{id,userId,assessmentId,groupId,startAt,submitAt,workspaceId,workspace,group}}.
5. Mở workspace, launch tool, làm bài. POST /me/attempts/{attempt}/submit với {} khi hoàn thành.
6. GET /me/attempts?assessmentId=`<uuid>` để xem các lượt của mình; GET detail trả {attempt,assessment,result,sessions,canReplay}. Bài nhóm được xem là attempt của mọi thành viên.

Group create/update nhận một trong hai field `memberIds` hoặc `memberEmails`, không gửi đồng thời. `memberEmails` được trim và chuyển chữ thường trước khi kiểm tra; tối đa 100 email, không trùng. Mỗi email phải thuộc tài khoản active có đủ quyền tại assessment. Email không tồn tại và email không đủ quyền đều trả cùng lỗi validation tại `memberEmails`: `Every member must have active access to this assessment.` Khi creator không phải assessment manager sửa nhóm bằng `memberEmails`, backend tự giữ creator trong nhóm; `memberEmails: []` giữ lại riêng creator. Với `memberIds`, creator phải được giữ rõ trong danh sách khi người sửa không phải manager. Bỏ cả hai field khi PATCH thì giữ nguyên thành viên.

`sessions` trong own/group attempt detail chỉ chứa các work session của workspace đó khi context đang chọn có `tool_session.replay_own`. Mỗi session có `id`, `toolId`, `toolName`, `toolVersionId`, `startedAt`, `endedAt`, `eventBatchCount`. Khi thiếu quyền này, `canReplay=false` và `sessions=[]`; truyền `roleAssignmentId` nhất quán để kiểm tra đúng role đang dùng. Quyền replay không thay thế yêu cầu sở hữu attempt và quyền xem bài; giao diện dùng session `id` làm `workSessionId` khi launch mode `REPLAY`.

Mỗi người chỉ thuộc một group cho mỗi assessment. Mọi thành viên phải có active assessment access. Creator hoặc assessment manager được đổi group trước khi có attempt; từ attempt đầu tiên trở đi, membership/name/delete group bị khóa 409. Bài cá nhân không nhận groupId. Có thể có nhiều attempt; submit không thể lặp lại. Submit đóng work sessions đang mở và khóa mọi ghi vào assignment workspace.

Teacher dùng GET /institutions/{i}/marking?termId=...&subjectId=...&assessmentId=...&studentId=... để lấy overview theo terms/subjects/students, rồi GET /institutions/{i}/attempts/{attempt}. Endpoint detail trả marks, replay sessions và flags nếu có quyền replay, cùng các cờ canMark/canFinalise/canReplay để điều khiển giao diện.

Marking flow:

1. POST /institutions/{i}/attempts/{attempt}/marks với {} tạo draft của marker (201), hoặc trả draft hiện có (200).
2. PATCH .../marks/{mark} bằng {"score":82.5,"outOf":100,"feedback":"Good explanation."}. Ít nhất một field được gửi; score không vượt outOf, tối đa hai chữ số thập phân.
3. POST .../marks/{mark}/submit với {}. Draft phải có score. Draft chỉ marker đó sửa/submit; draft của marker khác bị ẩn.
4. PUT /institutions/{i}/attempts/{attempt}/result bằng {"markId":"`<submitted-mark-uuid>`","note":"Final result"} để chọn điểm chính thức.
5. DELETE .../result gỡ lựa chọn điểm chính thức; không xóa mark history.

Mark status là DRAFT/SUBMITTED/SUPERSEDED. Muốn sửa mark đã submit, tạo draft mới; khi submit revision mới, mark trước chuyển SUPERSEDED. Official result là bước riêng cần attempt.result.finalize, không tự được chọn sau khi submit mark. Chỉ chấm attempt đã nộp; user không được chấm/finalise bài cá nhân hoặc bài nhóm của chính mình. score và outOf trong response là string decimal.

## 7. Calendar và assessment plan

GET /me/calendar trả aggregate {timeZone,canSeeAssessments,terms,offerings,events,plans}, chỉ gồm dữ liệu user có quyền xem. timeZone mặc định Australia/Sydney; periods và assessment timestamps dùng ISO UTC trong response.

POST /me/calendar-events:

~~~json
{
  "title": "Study session",
  "note": "Review recursion",
  "startAt": "2027-03-01T08:00:00+11:00",
  "endAt": "2027-03-01T10:00:00+11:00",
  "offeringId": "<offering-uuid>"
}
~~~

offeringId optional/nullable; nếu có phải xem được offering. Event chỉ thuộc chính user, không thành event chung của lớp. title tối đa 120, note tối đa 2000; startAt/endAt phải có timezone Z hoặc ±HH:MM; event dài hơn 0 và tối đa 24 giờ. GET /me/calendar-events?from=...&to=... lọc event giao khoảng thời gian. PATCH sửa một phần; PUT yêu cầu title/startAt/endAt.

PUT /me/assessment-plans/{assessment} bằng {"startDate":"2027-03-01","endDate":"2027-03-07"} tạo hoặc cập nhật plan riêng cho assessment đã publish; response {plan:{assessmentId,startDate,endDate}}. Đây là civil date YYYY-MM-DD, không chuyển qua UTC. GET /me/assessment-plans trả {plans:[]}; DELETE theo assessment trả 204.

## 8. Workspace, folders và lưu file an toàn

POST /workspaces bằng {"name":"Practice"} tạo PERSONAL workspace. Assignment workspace tự sinh từ attempt; frontend dùng attempt.workspaceId. Chỉ personal workspace được rename/delete. Assignment workspace của reviewer hoặc attempt đã submit là readOnly.

GET /workspaces/{workspace}?folder=`<folder-uuid>` trả workspace, breadcrumbs, documents trong thư mục và tools có active version. Không gửi folder để mở root. GET /workspaces/{workspace}/documents?parentFolderId=root&limit=100&cursor=`<uuid>` trả {documents,nextCursor}; bỏ parentFolderId ở business list để lấy mọi entry.

Tạo entry JSON:

~~~json
{
  "name": "main.py",
  "entryKind": "FILE",
  "parentFolderId": null,
  "content": "print('hello')\n"
}
~~~

Để tạo thư mục, gửi entryKind=FOLDER và name, có thể reuseExisting=true để dùng lại thư mục cùng tên. Multipart file cũng được hỗ trợ; name mặc định theo tên file upload. Tên entry tối đa 255, không chứa separator "/" hoặc "\", control character, "." hoặc "..". File tối đa 20 MiB. Folder parent phải cùng workspace; di chuyển folder vào chính nó/descendant bị từ chối.

PATCH .../documents/{document} bằng {"name":"solution.py","parentFolderId":"`<folder-uuid>`"} để rename/move; parentFolderId:null chuyển về root. DELETE xóa file/folder; folder con được cascade theo metadata. Bulk DELETE .../documents nhận {"documentIds":["`<document-uuid>`"]}, tối đa 500.

**GET .../documents/{document} và GET .../content đều trả bytes, không phải JSON metadata.** Metadata lấy từ list hoặc response create/update. Response content có ETag là contentHash được đặt trong dấu ngoặc kép.

Lưu content bằng PUT với raw body, không bọc JSON:

~~~http
PUT /api/v1/workspaces/<workspace-uuid>/documents/<document-uuid>/content
Accept: application/json
Content-Type: text/plain; charset=utf-8
X-XSRF-TOKEN: <URL-decoded-XSRF-cookie>
If-Match: "<current-content-hash>"

print('updated')
~~~

Thành công trả {data:document} và ETag mới. Thiếu If-Match trả 428; hash cũ trả 412 PRECONDITION_FAILED kèm currentContentHash. Khi đó tải lại file và cho user xử lý xung đột trước khi save lại; không tự ghi đè bỏ qua kiểm tra. Submitted workspace trả 403 dù token cũ còn hạn. Content-addressed blobs được giữ để phục vụ checkpoint/recovery.

## 9. Tool catalog, ZIP package và versions

GET /tools và /tools/{tool} là catalog cho user đã đăng nhập; user thường chỉ xem tool có active version. /admin/tools* dành cho system admin. Public catalog không có nghĩa là anonymous access.

POST /admin/tools nhận {"code":"code-editor","name":"Code Editor","description":"Editor","codeBaseLocation":"https://example.test/repo"} hoặc multipart thêm package,releaseNotes,activate. code là immutable lowercase slug 2–40 ký tự. POST /admin/tools/{tool}/versions upload multipart package ZIP, releaseNotes? và activate? (mặc định true).

ZIP root cần tool.json:

~~~json
{
  "schemaVersion": 1,
  "code": "code-editor",
  "version": "1.0.0",
  "entry": "index.html"
}
~~~

Manifest chỉ nhận bốn field này; code phải khớp tool, version phải là semantic version và chưa tồn tại, entry là HTML có thật trong ZIP. File JS/CSS/assets dùng đường dẫn tương đối. ZIP tối đa 25 MiB, tổng giải nén tối đa 100 MiB, mỗi file 20 MiB, tối đa 2000 entries; path traversal, symlink và encrypted ZIP bị từ chối.

Tool/ToolVersion responses dùng data với fields camelCase; tool có activeVersionId và activeVersion; version có id,toolId,version,entryPath,manifest,sha256,compressedSize,unpackedSize,fileCount,releaseNotes,createdById,createdAt. manifest là JSON opaque, giữ nguyên keys. Admin show/list versions để xem history; POST .../versions/{version}/activate đổi active version; GET .../download trả ZIP. Không có API sửa nội dung hoặc xóa một immutable version; tool đã có version/assessment link không bị xóa qua DELETE tool.

## 10. Launch tool, JWT, ghi events và replay

Launch thực hiện tại platform origin bằng auth session/PAT:

~~~http
POST /api/tool/v1/sessions
Content-Type: application/json
X-XSRF-TOKEN: <URL-decoded-XSRF-cookie>
~~~

Chọn đúng một mode/body:

~~~json
{"mode":"WORK","attemptId":"<attempt-uuid>","toolId":"<tool-uuid>","roleAssignmentId":"<assignment-uuid>"}
~~~

~~~json
{"mode":"PERSONAL","workspaceId":"<personal-workspace-uuid>","toolId":"<tool-uuid>"}
~~~

~~~json
{"mode":"REPLAY","workSessionId":"<recorded-work-session-uuid>","roleAssignmentId":"<assignment-uuid>"}
~~~

WORK cần attempt của user/group, chưa submit và tool được assessment cho phép. PERSONAL chỉ mở workspace chính user. REPLAY kiểm own/all attempt + replay permission; toolId không được gửi cùng REPLAY.

Response 201 không bọc data:

~~~json
{
  "token": "<short-lived-tool-jwt>",
  "tokenType": "Bearer",
  "expiresIn": 300,
  "runtimeUrl": "https://tools.example.net/tool-runtime/bootstrap#lernumi_token=<tool-jwt>&lernumi_api=v1",
  "session": {
    "id": "<work-session-uuid>",
    "runtimeSessionId": "<runtime-session-uuid>",
    "mode": "OWNER",
    "readOnly": false,
    "capabilities": ["files:read", "files:write", "events:append", "replay:read"]
  }
}
~~~

JWT mặc định 300 giây, cấu hình TOOL_TOKEN_TTL được clamp 30–3600 giây. Capability cụ thể phụ thuộc mode/quyền; PERSONAL chỉ có files:read/files:write và không ghi assignment event; REPLAY có files:read/replay:read và readOnly=true. Request mode WORK/PERSONAL được biểu diễn bằng OWNER trong runtime session; REPLAY là REVIEWER.

Mở runtimeUrl trong tab/window riêng (không iframe; tool assets có frame-ancestors none). Trusted bootstrap trên host tool nhận token từ fragment, xóa fragment khỏi URL bootstrap, gọi POST /api/tool/v1/runtime-auth bằng bearer rồi chuyển sang immutable asset URL của version được cấp. Cookie asset chỉ thuộc hostname/path của tool; platform login cookie không đi sang host tool.

Trong tool, đọc key **lernumi_token** từ fragment, dùng Authorization: Bearer `<tool-jwt>` cho mọi /api/tool/v1 request; fragment API marker là lernumi_api=v1. Không dùng account PAT thay JWT này. Runtime asset cookie không tự authenticate API vì API yêu cầu bearer. Không đưa JWT vào query string, log hoặc localStorage. Chỉ gọi API cùng tool origin; package CSP chặn truy cập app origin.

POST /token gia hạn runtime còn hợp lệ và trả grant mới cùng shape launch; gọi trước khi token hết hạn. JWT hết hạn/phiên login bị thu hồi/role bị revoke/user bị disable đều cần launch lại từ app sau khi xử lý auth; không thể dùng refresh để hồi sinh runtime đã hết hạn. PATCH /session với {} đóng owner runtime và work session, trả {session:{id,endAt}}. Submit attempt cũng đóng work sessions và cấm save/events.

Version behavior: mỗi WORK launch ghi một work session pin version active lúc bắt đầu. **REPLAY launch chạy version đang active hiện tại của tool**, còn recorded work session vẫn giữ toolVersionId gốc. Replay session list có currentRuntimeCompatible cho biết version khớp; không sửa version trong record lịch sử.

Runtime endpoints quan trọng:

| Endpoint | Capability / response |
| --- | --- |
| GET /context | Context platform/user/role/tool/session/workspace/attempt/assessment/offering; session.capabilities và readOnly là trạng thái server hiện tại |
| GET /documents?parentFolderId=root&cursor=...&limit=100 | files:read; {documents,nextCursor}; runtime list mặc định chỉ root, khác business list bỏ parent là tất cả |
| GET /documents/{id} | files:read; {document:metadata} |
| GET /documents/{id}/content | files:read; bytes + ETag |
| POST /documents | files:write; JSON/multipart như workspace, trả {document:metadata} |
| PATCH /documents/{id} | files:write; name/parentFolderId, trả {document:metadata} |
| PUT /documents/{id}/content | files:write; raw bytes + If-Match; trả {document:metadata}, ETag mới |
| DELETE /documents/{id} | files:write; 204 |
| GET /events/checkpoint | events:append; {checkpoint,nextSeq,closed} |
| POST /events | events:append; append batch theo sequence |
| GET /replay/sessions?cursor=...&limit=50 | replay:read; {sessions,nextCursor}, tối đa 100 |
| GET /replay/sessions/{sessionId}/events?afterSeq=-1&limit=100 | replay:read; {batches,nextAfterSeq}, tối đa 200 batches |

Event batch example:

~~~json
{
  "batchId": "<client-generated-uuid>",
  "seqStart": 0,
  "seqEnd": 1,
  "clientAt": "2027-03-01T01:00:00.000Z",
  "events": [
    {"type":"file.open","documentId":"<document-uuid>"},
    {"type":"editor.change","documentId":"<document-uuid>","text":"hello"}
  ]
}
~~~

Events là opaque JSON do tool định nghĩa; backend không tạo thêm event schema. Chỉ được gửi batchId/seqStart/seqEnd/clientAt/events ở batch root. events có 1–2000 phần tử, số lượng bằng seqEnd-seqStart+1, body tối đa 1 MiB, Content-Type application/json. clientAt có timezone; JSON cần số hữu hạn, không có null character trong string.

Bắt đầu seq=0, mỗi batch phải nối tiếp checkpoint. POST mới trả 201; gửi lại cùng batchId/cùng payload trả 200 với duplicate=true. Cùng batchId nhưng payload khác trả 409 IDEMPOTENCY_CONFLICT. Sequence hở/chồng trả 409 EVENT_SEQUENCE_CONFLICT kèm details.expectedSeqStart. Khi retry, giữ nguyên batchId và payload; nếu mất checkpoint, GET checkpoint rồi đồng bộ, không tự sinh batch ID mới cho retry cùng batch.

Receipt gồm accepted,duplicate,checkpoint,batch:{id,seqStart,seqEnd,receivedAt,payloadHash,previousChainHash,chainHash}. Replay trả batches theo sequence, có hash chain và nextAfterSeq; dùng seqEnd cuối cùng cho afterSeq trang tiếp theo.

## 11. HTTP errors và xử lý phía frontend

| Status | Khi nào / cách xử lý |
| --- | --- |
| 200 | Read/update thành công; idempotent mark draft/event retry có thể 200 |
| 201 | Tạo resource/attempt/group/tool version/runtime/event batch |
| 204 | Delete/logout; không gọi response.json() trên body trống |
| 400 | Một số runtime request sai tổ hợp dữ liệu, ví dụ upload file như folder |
| 401 | Chưa đăng nhập hoặc tool token/phiên hết hạn, bị revoke; quay lại login/launch phù hợp |
| 403 | Có identity nhưng thiếu quyền/scope; user disabled, tự chấm bài, workspace read-only |
| 404 | Không có resource, parent-child sai hoặc dữ liệu riêng không thuộc user; tool asset sai version |
| 409 | Đã submit/đã review/code đã dùng, lịch sử cấm delete, group locked, event sequence/idempotency conflict |
| 412 | If-Match cũ; tải lại file và xử lý xung đột |
| 413 | Event batch quá 1 MiB |
| 415 | Event batch không gửi application/json |
| 419 | CSRF token thiếu/sai; bootstrap cookie và đồng bộ header, kiểm credentials/CORS |
| 422 | Form validation hoặc domain rule (weight >100%, ngày sai, score sai, UUID input sai) |
| 428 | Thiếu If-Match khi overwrite document |
| 429 | Rate limit; xử lý Retry-After và tránh retry liên tục |
| 503 | Database/storage/runtime origin/signing secret chưa sẵn sàng; kiểm vận hành |

Business validation có shape:

~~~json
{"message":"The given data was invalid.","errors":{"subjectName":["The subject name field is required."]}}
~~~

Runtime/conditional-save domain errors có shape:

~~~json
{"error":{"code":"PRECONDITION_FAILED","message":"The document has changed. Reload before saving.","details":{"currentContentHash":"<latest-hash>"}}}
~~~

Runtime FormRequest validation trả error.code=VALIDATION_ERROR và error.fields; authentication middleware có thể dùng UNAUTHENTICATED, còn runtime session validation dùng AUTHENTICATION_REQUIRED. Đọc cả HTTP status và error.code; không phân tích chuỗi message để quyết định business logic. Database conflict được map thành 409/422 cùng error.code RESOURCE_IN_USE/RESOURCE_CONFLICT.

## 12. Toàn bộ API routes

Bảng đồng bộ với các file route API ngày 2026-11-03; có thể kiểm tra lại bằng `php artisan route:list --path=api --json`. GET routes cũng hỗ trợ HEAD; bảng bỏ HEAD để dễ đọc. Tất cả path bên dưới là path đầy đủ. Prefix /admin vẫn có kiểm quyền trong controller, không chỉ dựa vào tên URL.

### Account, session và system users

| Method | Path | Controller | Chức năng |
| --- | --- | --- | --- |
| GET | /api/v1/admin/audit | AdminUserController@audit | Access-management audit theo scope |
| GET | /api/v1/admin/users | AdminUserController@index | Danh sách user (system admin) |
| POST | /api/v1/admin/users | AdminUserController@store | Tạo user (system admin) |
| GET | /api/v1/admin/users/{user} | AdminUserController@show | Đọc user (system admin) |
| PUT / PATCH | /api/v1/admin/users/{user} | AdminUserController@update | Sửa/disable user; giữ admin cuối cùng |
| POST | /api/v1/auth/email/verification-notification | AuthController@resendVerification | Gửi lại email verification |
| POST | /api/v1/auth/forgot-password | AuthController@forgotPassword | Gửi reset email không lộ account existence |
| POST | /api/v1/auth/login | AuthController@login | Đăng nhập bằng cookie session |
| POST | /api/v1/auth/logout | AuthController@logout | Đăng xuất phiên/PAT hiện tại |
| POST | /api/v1/auth/logout-all | AuthController@logoutAll | Thu hồi mọi session/PAT/runtime |
| PUT | /api/v1/auth/password | AuthController@password | Đổi password và thu hồi các phiên |
| POST | /api/v1/auth/register | AuthController@register | Đăng ký user thường và tạo session |
| POST | /api/v1/auth/reset-password | AuthController@resetPassword | Đặt password từ reset token một lần |
| GET | /api/v1/auth/session | AuthController@session | User và active role contexts hiện tại |
| GET | /api/v1/auth/sessions | AuthController@sessions | Danh sách phiên đăng nhập của chính user |
| DELETE | /api/v1/auth/sessions/{session} | AuthController@revokeSession | Thu hồi một phiên của chính user |
| GET | /api/v1/auth/tokens | AuthController@tokens | Danh sách PAT metadata của user |
| POST | /api/v1/auth/tokens | AuthController@createToken | Tạo PAT sau xác nhận password |
| DELETE | /api/v1/auth/tokens/{token} | AuthController@deleteToken | Thu hồi PAT của chính user |
| GET | /api/v1/auth/verify-email/{id}/{hash} | AuthController@verifyEmail | Xác nhận email bằng signed link |
| GET | /api/v1/institutions/{institution}/audit | AdminUserController@audit | Access-management audit theo scope |
| GET | /api/v1/me | AuthController@profile | Profile của user hiện tại |
| PATCH | /api/v1/me | AuthController@updateProfile | Sửa profile; đổi email cần password |
| POST | /api/v1/me/avatar | AuthController@avatar | Upload avatar của chính user |
| DELETE | /api/v1/me/avatar | AuthController@deleteAvatar | Xóa avatar của chính user |
| GET | /api/v1/permissions | AdminUserController@permissions | Catalog permission cho role editor |
| GET | /api/v1/users/{user}/avatar | AuthController@showAvatar | Đọc avatar đã upload |

### Academic, discovery, health và media

| Method | Path | Controller | Chức năng |
| --- | --- | --- | --- |
| GET | /api/v1/admin/assessments | AdminAcademicController@assessments | Danh sách assessments liên institution (system admin) |
| GET | /api/v1/admin/institutions | InstitutionController@index | Institution được quyền xem; admin route yêu cầu system admin |
| POST | /api/v1/admin/institutions | InstitutionController@store | Tạo institution |
| GET | /api/v1/admin/institutions/{institution} | InstitutionController@show | Đọc institution |
| PUT / PATCH | /api/v1/admin/institutions/{institution} | InstitutionController@update | Cập nhật institution |
| DELETE | /api/v1/admin/institutions/{institution} | InstitutionController@destroy | Xóa institution |
| GET | /api/v1/admin/offerings | AdminAcademicController@offerings | Danh sách offerings liên institution (system admin) |
| GET | /api/v1/admin/subjects | AdminAcademicController@subjects | Danh sách subjects liên institution (system admin) |
| GET | /api/v1/admin/terms | AdminAcademicController@terms | Danh sách terms liên institution (system admin) |
| GET | /api/v1/health | HealthController@show | Public database/schema readiness check |
| GET | /api/v1/institution-directory | InstitutionController@directory | Directory institution cho onboarding/request role |
| GET | /api/v1/institutions | InstitutionController@index | Institution được quyền xem; admin route yêu cầu system admin |
| GET | /api/v1/institutions/{institution} | InstitutionController@show | Đọc institution |
| PUT / PATCH | /api/v1/institutions/{institution} | InstitutionController@update | Cập nhật institution |
| GET | /api/v1/institutions/{institution}/logo | MediaController@showLogo | Đọc institution logo |
| POST | /api/v1/institutions/{institution}/logo | MediaController@storeLogo | Upload institution logo với settings.update |
| DELETE | /api/v1/institutions/{institution}/logo | MediaController@destroyLogo | Xóa institution logo với settings.update |
| GET | /api/v1/institutions/{institution}/offerings | OfferingController@index | Danh sách offering |
| POST | /api/v1/institutions/{institution}/offerings | OfferingController@store | Tạo offering |
| GET | /api/v1/institutions/{institution}/offerings/{offering} | OfferingController@show | Đọc offering |
| PUT / PATCH | /api/v1/institutions/{institution}/offerings/{offering} | OfferingController@update | Cập nhật offering |
| DELETE | /api/v1/institutions/{institution}/offerings/{offering} | OfferingController@destroy | Xóa offering |
| GET | /api/v1/institutions/{institution}/offerings/{offering}/assessments | AssessmentController@index | Danh sách assessment |
| POST | /api/v1/institutions/{institution}/offerings/{offering}/assessments | AssessmentController@store | Tạo assessment |
| GET | /api/v1/institutions/{institution}/offerings/{offering}/assessments/{assessment} | AssessmentController@show | Đọc assessment |
| PUT / PATCH | /api/v1/institutions/{institution}/offerings/{offering}/assessments/{assessment} | AssessmentController@update | Cập nhật assessment |
| DELETE | /api/v1/institutions/{institution}/offerings/{offering}/assessments/{assessment} | AssessmentController@destroy | Xóa assessment |
| GET | /api/v1/institutions/{institution}/subjects | SubjectController@index | Danh sách subject |
| POST | /api/v1/institutions/{institution}/subjects | SubjectController@store | Tạo subject |
| GET | /api/v1/institutions/{institution}/subjects/{subject} | SubjectController@show | Đọc subject |
| PUT / PATCH | /api/v1/institutions/{institution}/subjects/{subject} | SubjectController@update | Cập nhật subject |
| DELETE | /api/v1/institutions/{institution}/subjects/{subject} | SubjectController@destroy | Xóa subject |
| GET | /api/v1/institutions/{institution}/terms | StudyTermController@index | Danh sách study term |
| POST | /api/v1/institutions/{institution}/terms | StudyTermController@store | Tạo study term |
| GET | /api/v1/institutions/{institution}/terms/{term} | StudyTermController@show | Đọc study term |
| PUT / PATCH | /api/v1/institutions/{institution}/terms/{term} | StudyTermController@update | Cập nhật study term |
| DELETE | /api/v1/institutions/{institution}/terms/{term} | StudyTermController@destroy | Xóa study term |
| GET | /api/v1/institutions/{institution}/terms/{term}/periods | StudyTermPeriodController@index | Danh sách term period |
| POST | /api/v1/institutions/{institution}/terms/{term}/periods | StudyTermPeriodController@store | Tạo term period |
| GET | /api/v1/institutions/{institution}/terms/{term}/periods/{period} | StudyTermPeriodController@show | Đọc term period |
| PUT / PATCH | /api/v1/institutions/{institution}/terms/{term}/periods/{period} | StudyTermPeriodController@update | Cập nhật term period |
| DELETE | /api/v1/institutions/{institution}/terms/{term}/periods/{period} | StudyTermPeriodController@destroy | Xóa term period |

### Roles, assignments, requests và codes

| Method | Path | Controller | Chức năng |
| --- | --- | --- | --- |
| POST | /api/v1/institutions/{institution}/join | RoleController@join | Auto-grant role bằng email đã verify |
| GET | /api/v1/institutions/{institution}/members | AssignmentController@members | Members trong institution hoặc offering cụ thể |
| GET | /api/v1/institutions/{institution}/requestable-offerings | RequestableOfferingController | Danh mục offering giới hạn field cho thành viên xin scope; cần institution.view |
| GET | /api/v1/institutions/{institution}/requestable-roles | RoleController@requestable | Roles user được phép gửi request |
| GET | /api/v1/institutions/{institution}/role-assignments | AssignmentController@index | Role assignments; offeringId giới hạn scope và response |
| POST | /api/v1/institutions/{institution}/role-assignments | AssignmentController@store | Grant role và optional offering scopes |
| DELETE | /api/v1/institutions/{institution}/role-assignments/{assignment} | AssignmentController@destroy | Soft revoke role identity và mọi scope |
| POST | /api/v1/institutions/{institution}/role-assignments/{assignment}/scopes | AssignmentController@storeScope | Grant offering scope cho active offering role |
| DELETE | /api/v1/institutions/{institution}/role-assignments/{assignment}/scopes/{scope} | AssignmentController@destroyScope | Soft revoke một offering scope |
| GET | /api/v1/institutions/{institution}/role-requests | AccessRequestController@roles | Request role trong institution để reviewer xử lý |
| POST | /api/v1/institutions/{institution}/role-requests | AccessRequestController@storeRole | Tạo role request theo eligibility policy |
| PATCH | /api/v1/institutions/{institution}/role-requests/{application} | AccessRequestController@reviewRole | Approve/reject role request một lần |
| GET | /api/v1/institutions/{institution}/roles | RoleController@index | Danh sách role |
| POST | /api/v1/institutions/{institution}/roles | RoleController@store | Tạo role |
| GET | /api/v1/institutions/{institution}/roles/{role} | RoleController@show | Đọc role |
| PUT / PATCH | /api/v1/institutions/{institution}/roles/{role} | RoleController@update | Cập nhật role |
| DELETE | /api/v1/institutions/{institution}/roles/{role} | RoleController@destroy | Xóa role |
| GET | /api/v1/institutions/{institution}/scope-codes | ScopeCodeController@index | Scope-code metadata trong scope được xem |
| POST | /api/v1/institutions/{institution}/scope-codes | ScopeCodeController@store | Sinh code một lần, chỉ trả secret lúc tạo |
| DELETE | /api/v1/institutions/{institution}/scope-codes/{code} | ScopeCodeController@destroy | Thu hồi code chưa dùng |
| GET | /api/v1/institutions/{institution}/scope-requests | AccessRequestController@scopes | Scope requests trong các offering được review |
| POST | /api/v1/institutions/{institution}/scope-requests | AccessRequestController@storeScope | Xin scope cho own active offering role |
| PATCH | /api/v1/institutions/{institution}/scope-requests/{application} | AccessRequestController@reviewScope | Approve/reject scope request một lần |
| GET | /api/v1/me/role-assignments | AssignmentController@mine | Role assignments/scopes của chính user |
| GET | /api/v1/me/role-requests | AccessRequestController@myRoles | Lịch sử request role của chính user |
| GET | /api/v1/me/scope-requests | AccessRequestController@myScopes | Lịch sử scope request của chính user |
| POST | /api/v1/scope-codes/redeem | ScopeCodeController@redeem | Redeem code vào role assignment chính user |

### Student learning, groups và attempts

| Method | Path | Controller | Chức năng |
| --- | --- | --- | --- |
| GET | /api/v1/me/assessments/{assessment} | LearningController@assessment | Published assessment cùng allowed tools |
| GET | /api/v1/me/assessments/{assessment}/groups | GroupController@index | Group của user hoặc mọi group nếu assessment manager |
| POST | /api/v1/me/assessments/{assessment}/groups | GroupController@store | Tạo group với thành viên có quyền assessment |
| GET | /api/v1/me/assessments/{assessment}/groups/{group} | GroupController@show | Đọc group trong đúng assessment |
| PUT / PATCH | /api/v1/me/assessments/{assessment}/groups/{group} | GroupController@update | Sửa group trước attempt đầu tiên |
| DELETE | /api/v1/me/assessments/{assessment}/groups/{group} | GroupController@destroy | Xóa group trước attempt đầu tiên |
| GET | /api/v1/me/attempts | AttemptController@index | Các attempt own/group cho assessmentId |
| POST | /api/v1/me/attempts | AttemptController@store | Bắt đầu attempt và tạo assignment workspace |
| GET | /api/v1/me/attempts/{attempt} | AttemptController@show | Own/group attempt, assessment, official result và own replay sessions theo quyền |
| POST | /api/v1/me/attempts/{attempt}/submit | AttemptController@submit | Nộp bài và đóng mọi work session |
| GET | /api/v1/me/offerings | LearningController@index | Offerings user có quyền xem |
| GET | /api/v1/me/offerings/{offering} | LearningController@show | Offering và published assessments |

### Marking và official result

| Method | Path | Controller | Chức năng |
| --- | --- | --- | --- |
| GET | /api/v1/institutions/{institution}/attempts/{attempt} | MarkingController@show | Attempt, marks, flags và recorded replay sessions |
| POST | /api/v1/institutions/{institution}/attempts/{attempt}/marks | MarkingController@store | Tạo hoặc tiếp tục own draft mark |
| PATCH | /api/v1/institutions/{institution}/attempts/{attempt}/marks/{mark} | MarkingController@update | Sửa own draft score/outOf/feedback |
| POST | /api/v1/institutions/{institution}/attempts/{attempt}/marks/{mark}/submit | MarkingController@submit | Submit draft, supersede revision trước |
| PUT | /api/v1/institutions/{institution}/attempts/{attempt}/result | MarkingController@finalise | Chọn submitted mark làm official result |
| DELETE | /api/v1/institutions/{institution}/attempts/{attempt}/result | MarkingController@clearResult | Gỡ official result, giữ mark history |
| GET | /api/v1/institutions/{institution}/marking | MarkingController@index | Overview terms/subjects/students/attempts cho marker |

### Calendar và plans

| Method | Path | Controller | Chức năng |
| --- | --- | --- | --- |
| GET | /api/v1/me/assessment-plans | CalendarController@plans | Own plans cho published accessible assessments |
| PUT | /api/v1/me/assessment-plans/{assessment} | CalendarController@plan | Upsert date-only assessment plan |
| DELETE | /api/v1/me/assessment-plans/{assessment} | CalendarController@deletePlan | Xóa own assessment plan |
| GET | /api/v1/me/calendar | CalendarController@index | Calendar aggregate theo quyền hiện tại |
| GET | /api/v1/me/calendar-events | CalendarController@events | Own private events, optional from/to |
| POST | /api/v1/me/calendar-events | CalendarController@store | Tạo private calendar event |
| PUT / PATCH | /api/v1/me/calendar-events/{event} | CalendarController@update | Sửa private calendar event |
| DELETE | /api/v1/me/calendar-events/{event} | CalendarController@destroy | Xóa private calendar event |

### Workspace và documents

| Method | Path | Controller | Chức năng |
| --- | --- | --- | --- |
| GET | /api/v1/workspaces | WorkspaceController@index | Own personal/assignment workspaces |
| POST | /api/v1/workspaces | WorkspaceController@store | Tạo personal workspace |
| GET | /api/v1/workspaces/{workspace} | WorkspaceController@show | Workspace, folder breadcrumbs, documents và tools |
| PUT / PATCH | /api/v1/workspaces/{workspace} | WorkspaceController@update | Rename own personal workspace |
| DELETE | /api/v1/workspaces/{workspace} | WorkspaceController@destroy | Xóa own personal workspace |
| GET | /api/v1/workspaces/{workspace}/documents | WorkspaceController@indexDocuments | Document metadata list với cursor |
| POST | /api/v1/workspaces/{workspace}/documents | WorkspaceController@storeDocument | Tạo file/folder hoặc upload bytes |
| DELETE | /api/v1/workspaces/{workspace}/documents | WorkspaceController@destroyDocuments | Bulk delete entries trong workspace |
| GET | /api/v1/workspaces/{workspace}/documents/{document} | WorkspaceController@showDocument | Đọc file bytes + ETag |
| PATCH | /api/v1/workspaces/{workspace}/documents/{document} | WorkspaceController@updateDocument | Rename/move entry trong cùng workspace |
| DELETE | /api/v1/workspaces/{workspace}/documents/{document} | WorkspaceController@destroyDocument | Xóa entry và descendants |
| GET | /api/v1/workspaces/{workspace}/documents/{document}/content | WorkspaceController@showDocument | Đọc file bytes + ETag |
| PUT | /api/v1/workspaces/{workspace}/documents/{document}/content | WorkspaceController@updateContent | Overwrite raw bytes với If-Match |

### Tool catalog và version administration

| Method | Path | Controller | Chức năng |
| --- | --- | --- | --- |
| GET | /api/v1/admin/tools | ToolController@index | Catalog active tools; admin route chỉ system admin |
| POST | /api/v1/admin/tools | ToolController@store | Tạo tool và optional ZIP version |
| GET | /api/v1/admin/tools/{tool} | ToolController@show | Đọc tool; admin có version history |
| PUT / PATCH | /api/v1/admin/tools/{tool} | ToolController@update | Sửa metadata, giữ immutable code |
| DELETE | /api/v1/admin/tools/{tool} | ToolController@destroy | Xóa tool chưa có version/assessment references |
| GET | /api/v1/admin/tools/{tool}/versions | ToolController@versions | Danh sách immutable versions (system admin) |
| POST | /api/v1/admin/tools/{tool}/versions | ToolController@upload | Upload ZIP thành immutable version |
| POST | /api/v1/admin/tools/{tool}/versions/{version}/activate | ToolController@activate | Chọn active version của đúng tool |
| GET | /api/v1/admin/tools/{tool}/versions/{version}/download | ToolController@download | Download ZIP archive (system admin) |
| GET | /api/v1/tools | ToolController@index | Catalog active tools; admin route chỉ system admin |
| GET | /api/v1/tools/{tool} | ToolController@show | Đọc tool; admin có version history |

### Tool runtime protocol

| Method | Path | Controller | Chức năng |
| --- | --- | --- | --- |
| GET | /api/tool/v1/context | ToolRuntimeController@context | Context user/role/tool/workspace/assessment hiện tại |
| GET | /api/tool/v1/documents | ToolRuntimeController@documents | Folder document metadata và nextCursor |
| POST | /api/tool/v1/documents | ToolRuntimeController@storeDocument | Tạo file/folder qua files:write |
| GET | /api/tool/v1/documents/{documentId} | ToolRuntimeController@showDocument | Metadata một document trong authorized workspace |
| PATCH | /api/tool/v1/documents/{documentId} | ToolRuntimeController@updateDocument | Rename/move document qua files:write |
| DELETE | /api/tool/v1/documents/{documentId} | ToolRuntimeController@deleteDocument | Xóa document qua files:write |
| GET | /api/tool/v1/documents/{documentId}/content | ToolRuntimeController@content | Đọc document bytes + ETag |
| PUT | /api/tool/v1/documents/{documentId}/content | ToolRuntimeController@overwrite | Overwrite raw bytes với If-Match |
| POST | /api/tool/v1/events | ToolRuntimeController@append | Append idempotent contiguous event batch |
| GET | /api/tool/v1/events/checkpoint | ToolRuntimeController@checkpoint | Checkpoint sequence của work session |
| GET | /api/tool/v1/replay/sessions | ToolRuntimeController@replaySessions | Cursor list recorded sessions của cùng workspace/tool |
| GET | /api/tool/v1/replay/sessions/{sessionId}/events | ToolRuntimeController@replayEvents | Cursor sequence list replay event batches |
| POST | /api/tool/v1/runtime-auth | ToolRuntimeController@runtimeAuth | Đặt scoped asset cookie trên tool origin |
| PATCH | /api/tool/v1/session | ToolRuntimeController@close | Đóng owner runtime/work session |
| POST | /api/tool/v1/sessions | ToolRuntimeController@launch | Launch WORK/PERSONAL/REPLAY từ platform auth |
| POST | /api/tool/v1/token | ToolRuntimeController@token | Gia hạn runtime JWT còn hợp lệ |

### Supporting routes ngoài prefix API

| Method | Path | Controller / handler | Chức năng |
| --- | --- | --- | --- |
| GET | /sanctum/csrf-cookie | Sanctum CsrfCookieController | Bootstrap XSRF/session cookie trước browser mutation |
| GET | /up | Laravel health route | App liveness, không thay cho database readiness |
| GET | /tool-runtime/bootstrap | ToolAssetController@bootstrap | Trusted launcher; chỉ trên hostname tool |
| GET | /tools/{toolCode}/{version}/{assetPath?} | ToolAssetController@show | Protected immutable package assets; version-bound runtime cookie/bearer |

Khi route thay đổi, kiểm tra lại bằng docker compose exec -T api php artisan route:list --path=api --json. Bảng trên là snapshot của code trong repository; permission catalog đầy đủ lấy bằng GET /api/v1/permissions.
