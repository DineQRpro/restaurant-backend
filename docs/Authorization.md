# Restaurant ERP — Authorization

## 1. Role vs Permission

**Roles** are named groupings assigned to users (e.g. Admin, Waiter, Captain, Counter).

**Permissions** are fine-grained abilities (e.g. `orders.create`, `bills.settle`).

A role has many permissions. A user belongs to one role. Authorization is always checked against **permissions**, not role names.

---

## 2. Permission Naming Convention

Permissions follow the pattern:

```
{resource}.{action}
```

Examples:

```
orders.create
orders.update
orders.cancel
tables.merge
bills.settle
users.delete
```

---

## 3. Current Roles

| Role    | Slug      |
| ------- | --------- |
| Admin   | `admin`   |
| Waiter  | `waiter`  |
| Captain | `captain` |
| Counter | `counter` |

---

## 4. Current Permission List

### Dashboard

* `dashboard.view`

### Tables

* `tables.view`
* `tables.create`
* `tables.update`
* `tables.delete`
* `tables.merge`
* `tables.transfer`

### Orders

* `orders.view`
* `orders.create`
* `orders.update`
* `orders.cancel`
* `orders.item_status`

### KOT

* `kot.view`
* `kot.create`
* `kot.update`

### Billing

* `bills.view`
* `bills.create`
* `bills.discount`
* `bills.split`
* `bills.settle`

### Menu

* `menu.view`
* `menu.create`
* `menu.update`
* `menu.delete`

### Users

* `users.view`
* `users.create`
* `users.update`
* `users.delete`

### Reports

* `reports.view`

### Receipts

* `receipts.view`
* `receipts.print`

---

## 5. Role → Permission Mapping

### Waiter

`dashboard.view`, `menu.view`, `tables.view`, `tables.update`, `tables.transfer`, `orders.view`, `orders.create`, `orders.update`, `orders.item_status`, `kot.view`, `kot.create`

### Captain

`dashboard.view`, `menu.view`, `tables.view`, `tables.update`, `tables.merge`, `tables.transfer`, `orders.view`, `orders.create`, `orders.update`, `orders.cancel`, `orders.item_status`, `kot.view`, `kot.create`, `kot.update`, `bills.view`

### Counter

`dashboard.view`, `tables.view`, `orders.view`, `orders.update`, `kot.view`, `bills.view`, `bills.create`, `bills.discount`, `bills.split`, `bills.settle`, `receipts.view`, `receipts.print`, `reports.view`

### Admin

All currently defined permissions (assigned explicitly, not via bypass).

---

## 6. How Backend Code Checks Permissions

### Direct check on User model

```php
$user->hasPermission('orders.create');  // returns bool
```

### Via Laravel Gate (registered in AppServiceProvider)

```php
Gate::allows('orders.create');

// In a controller:
$this->authorize('orders.create');
```

### Get all permission slugs

```php
$user->getPermissionSlugs();  // returns ['orders.create', 'tables.view', ...]
```

---

## 7. How Routes Use Permission Middleware

Protect a route with the `permission` middleware:

```php
Route::middleware(['auth:sanctum', 'permission:orders.create'])
    ->post('/orders', [OrderController::class, 'store']);
```

Multiple permissions can be applied by chaining middleware or using multiple route groups.

---

## 8. 401 vs 403

| Code | Meaning | When |
|------|---------|------|
| **401 Unauthorized** | Not authenticated | No valid Sanctum token provided |
| **403 Forbidden** | Authenticated but lacks permission | User is logged in but their role does not have the required permission |

### 401 response (handled by Sanctum)

```json
{
    "message": "Unauthenticated."
}
```

### 403 response (handled by permission middleware)

```json
{
    "success": false,
    "message": "You do not have permission to perform this action.",
    "errors": {}
}
```

---

## 9. Important Rule

> Frontend role/permission checks are only for UI behavior. Laravel backend authorization is the actual security boundary.

React may hide buttons or menu items based on permissions returned from `/api/v1/auth/me`, but the Laravel backend independently enforces authorization on every protected operation.

Never rely on the frontend to prevent unauthorized actions.

---

## 10. Security Rules

* Do not hardcode role checks like `if ($user->role->slug === 'admin')`.
* Do not create global admin bypasses.
* Admin permissions are explicitly assigned through the same role → permission mechanism.
* Do not hardcode role IDs or magic numbers.
* Do not allow users to self-assign permissions.
* Never expose password hashes or Sanctum tokens through user resources.
