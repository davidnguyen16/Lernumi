# Authentication and authorization API

Base URL: `/api/v1`. Requests and responses use JSON and camelCase. Validation errors return `422` with Laravel's `message` and `errors`; missing authentication returns `401`; denied permissions return `403`; missing or cross-institution resources return `404`; conflicting state returns `409`. Protected endpoints accept a Sanctum browser session or personal bearer token. Passwords never appear in responses.

## Browser session flow

1. Send `GET /sanctum/csrf-cookie` with credentials enabled.
2. Read and URL-decode the `XSRF-TOKEN` cookie and send it as `X-XSRF-TOKEN` on mutations, including login and registration. Keep `credentials: 'include'` on all API calls. Axios can use `withCredentials` and `withXSRFToken`.
3. `POST /auth/login` accepts `{email,password,remember?}`. `POST /auth/register` accepts `{name?,firstName?,lastName?,email,password,passwordConfirmation}`; supply either `name` or a first/last name. Passwords require at least ten characters, letters and numbers, and may contain at most 72 bytes to prevent bcrypt truncation. `password_confirmation` is also accepted for Laravel clients. Registration never grants system administrator access.
4. Both responses, and `GET /auth/session`, return `{data:{user,contexts,isAdmin}}`. Each context has `id`, `roleAssignmentId`, `institution:{id,name}`, `role:{id,roleCode,roleName,scopeType}`, `permissions` and `offeringIds`. A role context with no offering scopes grants no offering permissions. `user` includes `id,name,firstName,lastName,email,emailVerified,isAdmin,isSystemAdmin,isActive,avatarUrl,createdAt`.
5. `POST /auth/logout` ends the current browser session or bearer token; `POST /auth/logout-all` revokes every session, token and tool runtime session belonging to the account.

The browser's origin must be allowed by the backend CORS and Sanctum settings. Auth routes always start a CSRF-protected web session. Business routes use Sanctum's stateful API middleware; do not send bearer tokens from the browser when using cookie sessions.

## Account endpoints

| Method | Path | Body / result |
| --- | --- | --- |
| GET | `/me` | Current user |
| PATCH | `/me` | Optional `name,firstName,lastName,email,currentPassword`; changing email requires the current password and clears verification |
| PUT | `/auth/password` | `currentPassword,password,passwordConfirmation`; revokes all sessions and tokens, then sign in again |
| POST | `/auth/forgot-password` | `email`; response does not reveal whether the account exists |
| POST | `/auth/reset-password` | `email,token,password,passwordConfirmation`; token is single use, sessions and tokens are revoked |
| POST | `/auth/email/verification-notification` | Sends a signed verification link if needed |
| GET | `/auth/verify-email/{id}/{hash}` | Signed link from email; must be signed in as that account |
| POST | `/me/avatar` | Multipart `avatar`, JPEG/PNG/WebP, at most 5 MiB |
| DELETE | `/me/avatar` | Removes own avatar |
| GET | `/users/{userId}/avatar` | Authenticated image response |
| GET | `/auth/sessions` | Own sessions, IP, user agent, last activity and whether current |
| DELETE | `/auth/sessions/{sessionId}` | Revokes own session and tool runtime sessions launched from it |
| GET | `/auth/tokens` | Own personal token metadata, never token secrets |
| POST | `/auth/tokens` | `name,currentPassword,expiresAt?`; returns token once, default expiry thirty days, maximum one year |
| DELETE | `/auth/tokens/{id}` | Revokes own personal token and bearer-launched runtime sessions |

Password reset links use `FRONTEND_URL/reset-password?token=...&email=...`. Development mail goes to the configured log/array transport; configure a real mail transport for production. Accounts can use ordinary manually granted roles before email verification; automatic email-domain enrollment requires verified email ownership.

## Role definitions and permissions

All institution paths below start with `/institutions/{institutionId}`. Role definitions belong to exactly one institution. `scopeType` is `INSTITUTION` or `OFFERING`; its value cannot be changed after creation. Offering roles receive effective scope through separate scope assignments.

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/permissions` | Stable permission catalog, descriptions and institution-only flags |
| GET | `/institution-directory` | Authenticated, paginated institution discovery for roles that allow requests; returns id/name/website |
| GET, POST | `/institutions/{institutionId}/roles` | Paginated roles / create a role |
| GET, PUT, PATCH, DELETE | `/institutions/{institutionId}/roles/{roleId}` | Read, update or delete a role |
| GET | `/institutions/{institutionId}/requestable-roles` | Roles this user may request |
| POST | `/institutions/{institutionId}/join` | Grant roles whose email regex matches this verified email |

Role body: `{roleCode,roleName,scopeType,requestMode?,autoGrantEmailRegex?,permissions?,includedRoleIds?,requiredRoleIds?}`. Codes use `institution:...` or `offering:...` matching `scopeType`. `requestMode` is `DISABLED` (default), `PUBLIC`, or `CONDITIONAL`; conditional requests require at least one active role from `requiredRoleIds`. Email regex uses PCRE syntax without delimiters, e.g. `@example\.edu$`; matching has bounded computation limits. Role inheritance must stay within one institution and scope type; cycles are rejected. Omitted arrays on PATCH are preserved; an empty array clears them.

`*.manage` bundles expand to their component actions. `offering.manage` grants view/update/delete; creating an offering separately requires institution-level `offering.create`. `assessment.view` implies `assessment.view_published`; historical `assessment.attempt` implies `attempt.start` and `attempt.submit`. `tool_session.replay_all` also implies `tool_session.replay_own` and `attempt.replay`. Official result selection requires the explicit `attempt.result.finalize` permission.

System administrators may manage every scope. Other role administrators may only delegate permissions they themselves hold in that scope. They cannot modify, grant or revoke a more powerful role. An assignment or scope revocation takes effect on the next authorization check. Historical assignments and requests remain in the database; deleting a referenced role returns `409`.

## Role and scope assignments

| Method | Path | Body / purpose |
| --- | --- | --- |
| GET | `/me/role-assignments` | Own role assignments and scopes |
| GET, POST | `/institutions/{institutionId}/role-assignments` | List / grant `{userId,roleId,offeringIds?}` |
| DELETE | `/institutions/{institutionId}/role-assignments/{assignmentId}` | Soft revoke role and every active scope |
| POST | `/institutions/{institutionId}/role-assignments/{assignmentId}/scopes` | Grant `{offeringId}` to an active OFFERING role |
| DELETE | `/institutions/{institutionId}/role-assignments/{assignmentId}/scopes/{scopeId}` | Soft revoke that scope |
| GET | `/institutions/{institutionId}/members?offeringId=...` | Scoped member list, `id,name,email,isActive` |

Assignment lists accept `userId,roleId,offeringId,includeRevoked,perPage,page`. Listing complete assignments requires institution-level `role_assignment.view` because one assignment may span multiple offerings. Adding `offeringId` permits offering-level `role_assignment.view` and returns only assignments applicable there, with all other offering scopes omitted. Member lists can also be narrowed to one offering and authorize `member.view` there. Institution roles cannot receive offering scopes.

## Access requests and one-time offering codes

| Method | Path | Body / purpose |
| --- | --- | --- |
| GET | `/me/role-requests`, `/me/scope-requests` | Own request history |
| GET, POST | `/institutions/{institutionId}/role-requests` | List / request `{roleId,note?}` |
| PATCH | `/institutions/{institutionId}/role-requests/{requestId}` | Review `{decision:'APPROVED'|'REJECTED',note?}` |
| GET, POST | `/institutions/{institutionId}/scope-requests` | List / request `{roleAssignmentId,offeringId,note?}` |
| PATCH | `/institutions/{institutionId}/scope-requests/{requestId}` | Review `{decision:'APPROVED'|'REJECTED',note?}` |
| GET, POST | `/institutions/{institutionId}/scope-codes` | List / create `{offeringId,expiresAt?}`; defaults to seven days |
| DELETE | `/institutions/{institutionId}/scope-codes/{codeId}` | Revoke unused code |
| POST | `/scope-codes/redeem` | `{code,roleAssignmentId}`; grants scope to the current user's active role |

Reviewers need the corresponding approve/reject permission and may only grant role permissions they hold. Non-admin users cannot review their own requests. Each pending request is unique and may be processed only once. Request responses include applicant `user:{id,name,email}` and `role:{id,roleCode,roleName,scopeType}` summaries; scope requests also include offering labels so reviewers need no global user lookup. Approved requests retain the generated assignment ID for auditing. Status values are `PENDING`, `APPROVED`, and `REJECTED`. The request's current `note` can be replaced by the reviewer's note; creation and decision audit events retain the original and review notes separately.

The opaque code secret appears only in its creation response as `data.code`; the database stores only a SHA-256 hash. A code can be redeemed once, must belong to the same institution as the recipient role, and cannot expand a role above its issuer's current authority. Scope-code list and request-review endpoints accept an optional `offeringId`; permission filtering happens before pagination.

## System administration and audit

`GET,POST /admin/users`, `GET,PATCH,PUT /admin/users/{userId}` require a system administrator. Writable fields are `name,firstName,lastName,email,password,isAdmin,isActive`. Disabling an account or resetting its password revokes all its sessions, tokens and tool runtime sessions. The final active system administrator cannot be disabled or demoted. Accounts are disabled rather than deleted to preserve academic history.

`GET /admin/audit` lists global access-management events. `GET /institutions/{institutionId}/audit` requires `audit.view` in that institution. Responses include actor, action, subject ID, metadata and timestamp. Password values and code secrets are excluded. Standard collection requests accept `perPage` (1–100, default 20) and `page`; applicable lists support `search`, `status` and the filters stated above.

For backend code, `AccessService::can/authorize(User,string,Institution|Offering)` evaluates the union of applicable active assignments; `canInContext(User,string,institutionId,offeringId?,roleAssignmentId?)` optionally restricts evaluation to one chosen assignment. `offeringIds` and `institutionIds` return only accessible resource IDs. Context data is a UI aid; every mutation independently checks current server-side authorization.
