# Restaurant ERP — Admin Web UI

The Admin (and later Counter / Kitchen) experience is a Laravel Blade application. It does **not** call `/api/v1`. Controllers use existing services and Eloquent models directly.

Waiter and Captain continue to use the React SPA.

---

## Routes

| Method | Path | Auth |
| ------ | ---- | ---- |
| `GET` | `/admin/login` | Guest |
| `POST` | `/admin/login` | Guest |
| `POST` | `/admin/logout` | Session |
| `GET` | `/admin/dashboard` | Session + role slug `admin` + `dashboard.view` |
| `GET` | `/admin/menu/categories` | Session + `admin` + `menu.view` |
| `GET` | `/admin/menu/categories/create` | Session + `admin` + `menu.create` |
| `POST` | `/admin/menu/categories` | Session + `admin` + `menu.create` |
| `GET` | `/admin/menu/categories/{category}/edit` | Session + `admin` + `menu.update` |
| `PUT` | `/admin/menu/categories/{category}` | Session + `admin` + `menu.update` |
| `PATCH` | `/admin/menu/categories/{category}/status` | Session + `admin` + `menu.update` |
| `DELETE` | `/admin/menu/categories/{category}` | Session + `admin` + `menu.delete` |
| `GET` | `/admin/menu/items` | Session + `admin` + `menu.view` |
| `GET` | `/admin/menu/items/create` | Session + `admin` + `menu.create` |
| `POST` | `/admin/menu/items` | Session + `admin` + `menu.create` |
| `GET` | `/admin/menu/items/{item}/edit` | Session + `admin` + `menu.update` |
| `PUT` | `/admin/menu/items/{item}` | Session + `admin` + `menu.update` |
| `PATCH` | `/admin/menu/items/{item}/status` | Session + `admin` + `menu.update` |
| `DELETE` | `/admin/menu/items/{item}` | Session + `admin` + `menu.delete` |
| `GET` | `/admin/users` | Session + `admin` + `users.view` |
| `GET` | `/admin/users/create` | Session + `admin` + `users.create` |
| `POST` | `/admin/users` | Session + `admin` + `users.create` |
| `GET` | `/admin/users/{user}/edit` | Session + `admin` + `users.update` |
| `PUT` | `/admin/users/{user}` | Session + `admin` + `users.update` |
| `PATCH` | `/admin/users/{user}/status` | Session + `admin` + `users.update` |
| `DELETE` | `/admin/users/{user}` | Session + `admin` + `users.delete` |
| `GET` | `/admin/tables` | Session + `admin` + `tables.view` |
| `GET` | `/admin/tables/create` | Session + `admin` + `tables.create` |
| `POST` | `/admin/tables` | Session + `admin` + `tables.create` |
| `GET` | `/admin/tables/{table}/edit` | Session + `admin` + `tables.update` |
| `PUT` | `/admin/tables/{table}` | Session + `admin` + `tables.update` |
| `PATCH` | `/admin/tables/{table}/status` | Session + `admin` + `tables.update` |
| `DELETE` | `/admin/tables/{table}` | Session + `admin` + `tables.delete` |

Session authentication uses the default `web` guard. It does not create Sanctum tokens.

---

## Login rules

Credentials are the same as the API: `username`, `password`, and `is_active`.

After a successful password check:

| Role slug | Result |
| --------- | ------ |
| `admin` | Session login → `/admin/dashboard` |
| `waiter`, `captain` | Rejected. Use the React staff application. |
| `counter`, `kitchen` | Rejected until those Blade dashboards exist. |

Invalid credentials use the generic message `Invalid username or password.`

---

## Authorization

`/admin/dashboard` requires:

1. Authenticated web session
2. Role **slug** `admin` (application-area routing, not a role ID check)
3. Permission `dashboard.view`

Sidebar items are shown only when the user has the matching permission. Pages that are not implemented yet are visible as **Soon** and are not linked.

**Menu → Categories**, **Menu → Items**, **Users**, and **Tables** are implemented.

The Blade UI uses `MenuCategoryService`, `MenuItemService`, `UserService`, and `TableService` directly. It does not call `/api/v1`.

Table Management edits the table **master** (`number`, `description`, `capacity`, `is_active`). Occupied / reserved status is not stored on that row. See `docs/TableManagement.md`.

Form fields match the Laravel model (`name`, `description`, `image`, `sort_order`, `is_active`). The screens use Core PHP **Cuisine** labels. Web create/edit accept an optional image file and store the path on `menu_categories.image`. Deleting a category that still has items flashes the existing service conflict message instead of removing the row.

---

## Dashboard data

Reproduced from the Core PHP Admin dashboard:

| Section | Source |
| ------- | ------ |
| Total Users | `users` count |
| Sold Out Dishes | `menu_items` where `is_available = false` |
| Menu category / item counts | `menu_categories` / `menu_items` |
| Total Amount Received | Placeholder (Billing not implemented) |
| Total Orders / Pending Orders | Placeholder (Orders not implemented) |
| Highly / Less Ordered | Placeholder (Orders not implemented) |
| Track Orders table | Placeholder |
| Table status board | Placeholder (Tables not implemented) |

No order, table, KOT, or billing queries were added in this module.

---

## React login note

The React SPA still calls `POST /api/v1/auth/login`. After a successful API response it **rejects** Admin, Counter, and Kitchen accounts in the React app so they are never treated as Waiter.

Admin staff should use:

```text
http://restaurant-backend.ddev.site/admin/login
```

Laravel does not redirect administrators to the React Waiter dashboard.

---

## UI

Tailwind CSS (already in the Laravel Vite stack) is used with Varsity Kitchen colors (`#04346a`) and Nunito Sans. Login uses the existing restaurant background and logo.

The sidebar collapses off-canvas on small screens.
