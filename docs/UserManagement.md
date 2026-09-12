# Restaurant ERP — User Management API

This document covers staff user management. Follow `docs/ApiStandards.md` for response format and `docs/Authorization.md` for permission checking.

Frontend role/permission visibility is not a security boundary. Laravel enforces every operation.

---

## Endpoints

| Method | Endpoint | Permission |
| ------ | -------- | ---------- |
| `GET` | `/api/v1/users` | `users.view` |
| `POST` | `/api/v1/users` | `users.create` |
| `GET` | `/api/v1/users/{user}` | `users.view` |
| `PUT` | `/api/v1/users/{user}` | `users.update` |
| `PATCH` | `/api/v1/users/{user}/status` | `users.update` |
| `DELETE` | `/api/v1/users/{user}` | `users.delete` |

All endpoints require Sanctum authentication (`Authorization: Bearer {token}`).

Unauthenticated requests return `401`. Missing permission returns `403`.

---

## List users

```http
GET /api/v1/users?search=john&role=waiter&is_active=1&page=1&per_page=20
```

Query parameters:

| Parameter | Rules |
| --------- | ----- |
| `search` | Optional. Matches name, username, mobile, or email. |
| `role` | Optional role slug (`waiter`, `captain`, `counter`, `admin`). |
| `is_active` | Optional boolean. |
| `per_page` | Optional. Default `20`. Maximum `100`. |
| `page` | Optional page number. |

The list eager-loads `role` and does not include permissions or passwords.

---

## Create user

```http
POST /api/v1/users
```

```json
{
    "name": "John Doe",
    "username": "john",
    "mobile": "9876543210",
    "email": "john@example.com",
    "password": "secure-password",
    "role_id": 2,
    "is_active": true
}
```

`is_active` defaults to `true` when omitted.

Returns `201`.

---

## Validation

### Name

Required. Maximum 255 characters.

### Username

Required. Unique. Used for login.

On update, the current username is excluded from the uniqueness check.

### Mobile

Optional Indian mobile number:

```text
nullable
regex:/^[6-9][0-9]{9}$/
unique when provided
```

Valid: `9876543210`

Invalid: `987654321`, `1987654321`, `98765432101`, `abcdefghij`

Uniqueness matches the existing `users.mobile` unique index and `docs/ProjectRequirements.md`.

### Email

Optional. Valid email when provided. Unique when provided, matching the existing `users.email` unique index.

### Password

Required on create. Optional on update. Minimum 8 characters.

Laravel hashes passwords through the User model `hashed` cast. Plaintext is never stored.

If password is omitted on update, the existing password is unchanged.

### Role

`role_id` is required and must exist in `roles`.

Clients cannot assign permissions directly. Permissions come from:

```text
User → Role → Permissions
```

---

## Show user

```http
GET /api/v1/users/{user}
```

Unknown users return `404`.

Returned fields:

```text
id, name, username, mobile, email, role, is_active, created_at, updated_at
```

`role` is:

```json
{
    "id": 2,
    "name": "Waiter",
    "slug": "waiter"
}
```

---

## Update user

```http
PUT /api/v1/users/{user}
```

Updatable fields: `name`, `username`, `mobile`, `email`, `role_id`, `is_active`, `password`.

---

## Activate / deactivate

```http
PATCH /api/v1/users/{user}/status
```

```json
{
    "is_active": false
}
```

Inactive users cannot log in through the existing authentication foundation.

---

## Deletion behavior

Users are **not hard-deleted**.

Restaurant records may later reference the staff member who created an order, bill, or payment. Physical deletion would break historical integrity.

`DELETE /api/v1/users/{user}` returns `409 Conflict`:

```json
{
    "success": false,
    "message": "Users cannot be permanently deleted. Deactivate the user instead.",
    "errors": {}
}
```

Use the status endpoint to deactivate a user.

Soft deletes were not added because the current schema has no `deleted_at` column and deactivation is the documented strategy for users.

---

## Self-protection

An authenticated user cannot:

* deactivate their own account
* delete their own account

These requests return `409 Conflict` so an administrator cannot lock themselves out.

---

## Security

Never returned by user APIs:

* password
* password hash
* Sanctum tokens

`GET /api/v1/auth/me` continues to include the authenticated user's permission slugs. User management list/show/create/update responses do not include permissions, to avoid N+1 queries.

---

## Admin Blade UI

Admin User Management is a Laravel Blade module. It does **not** call `/api/v1/users`. The controller uses `UserService` — the same service as the API.

| Method | Path | Permission |
| ------ | ---- | ---------- |
| `GET` | `/admin/users` | `users.view` |
| `GET` | `/admin/users/create` | `users.create` |
| `POST` | `/admin/users` | `users.create` |
| `GET` | `/admin/users/{user}/edit` | `users.update` |
| `PUT` | `/admin/users/{user}` | `users.update` |
| `PATCH` | `/admin/users/{user}/status` | `users.update` |
| `DELETE` | `/admin/users/{user}` | `users.delete` |

Form fields match the User model: `name`, `username`, `mobile`, `email`, `password`, `role_id`, `is_active`.

Roles are loaded from the `roles` table. The UI does not hardcode role IDs.

Password is required on create and optional on edit. Leave the edit password blank to keep the current password. There is no separate reset-password endpoint.

`DELETE /admin/users/{user}` never hard-deletes. The page flashes the existing service conflict message so administrators deactivate the account instead.

An administrator cannot deactivate or delete their own account. The list hides those actions for the signed-in user; the service still enforces the same `409` rules.
