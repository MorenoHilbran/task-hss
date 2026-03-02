# Task Manager — Technical Assessment

> **Stack:** Laravel 12 · Vue 3 · Inertia.js · MySQL 8  
> **Purpose:** Single-user task board built as a live-coding assessment deliverable.

---

## Table of Contents

1. [Overview](#overview)
2. [Requirements](#requirements)
3. [Getting Started](#getting-started)
4. [Project Structure](#project-structure)
5. [API Reference](#api-reference)
6. [Authentication](#authentication)
7. [Frontend](#frontend)
8. [Technical Decisions](#technical-decisions)
9. [Known Limitations & Production Improvements](#known-limitations--production-improvements)

---

## Overview

Task Manager is a fullstack web application that allows a single authenticated user to **create, read, update, and delete** tasks. Each task has a title and a status of either `pending` or `done`.

The backend exposes a RESTful JSON API built with **Laravel 12**. The frontend is a **Vue 3** SPA rendered server-side via **Inertia.js**, communicating with the API through the browser's native `fetch` API.

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ≥ 8.2 |
| Composer | ≥ 2.x |
| Node.js | ≥ 18.x |
| MySQL | ≥ 8.0 |
| Laravel | 12.x |

---

## Getting Started

### 1. Clone & Install Dependencies

```bash
git clone <repository-url>
cd task-manager

composer install
npm install
```

### 2. Configure Environment

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and set your database credentials:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=task_hss
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Create the Database

Log into MySQL and run:

```sql
CREATE DATABASE task_hss CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 4. Run Migrations

```bash
php artisan migrate
```

This creates the following tables:

| Table | Description |
|---|---|
| `users` | Default Laravel users (not used in this assessment) |
| `sessions` | Database session storage |
| `tasks` | Core task records |

### 5. Start the Development Server

```bash
composer run dev
```

This concurrently runs the Laravel backend (`php artisan serve`) and the Vite frontend dev server. Navigate to [http://localhost:8000/tasks](http://localhost:8000/tasks).

---

## Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Controller.php          # Base controller with jsonResponse() helper
│   │   ├── AuthController.php      # Handles POST /api/login
│   │   └── TaskController.php      # Full CRUD for tasks
│   └── Middleware/
│       └── SimpleTokenAuth.php     # Bearer token validation middleware
├── Models/
│   └── Task.php                    # Eloquent model (fillable: title, status)
database/
└── migrations/
    ├── ..._create_tasks_table.php
    └── ..._update_tasks_status_enum.php
resources/js/
└── pages/
    └── Tasks.vue                   # Full SPA: login + task board
routes/
├── api.php                         # API routes (login + CRUD)
└── web.php                         # Inertia route for /tasks
```

---

## API Reference

All API responses follow this consistent envelope:

```json
{
  "success": true | false,
  "data":    {} | [] | null,
  "message": "Human-readable description"
}
```

### Base URL

```
http://localhost:8000/api
```

---

### Authentication

#### `POST /api/login`

Authenticates the user with hardcoded credentials and returns a Bearer token.

**Request Body**

```json
{
  "email":    "admin@test.com",
  "password": "123456"
}
```

**Response — 200 OK**

```json
{
  "success": true,
  "data": {
    "token": "simple-admin-token-2024",
    "type":  "Bearer"
  },
  "message": "Login berhasil"
}
```

**Response — 401 Unauthorized**

```json
{
  "success": false,
  "data":    null,
  "message": "Email atau password salah"
}
```

**Response — 422 Unprocessable Entity** *(validation failure)*

```json
{
  "message": "The email field is required.",
  "errors": { "email": ["The email field is required."] }
}
```

---

### Tasks

All task endpoints require the `Authorization` header:

```
Authorization: Bearer simple-admin-token-2024
```

If the token is missing or invalid, all protected endpoints return:

```json
{
  "success": false,
  "data":    null,
  "message": "Unauthorized. Token tidak valid atau tidak ditemukan."
}
```

---

#### `GET /api/tasks`

Returns all tasks ordered by creation date (newest first).

**Response — 200 OK**

```json
{
  "success": true,
  "data": [
    {
      "id":         1,
      "title":      "Setup project",
      "status":     "done",
      "created_at": "2026-03-02T10:00:00.000000Z",
      "updated_at": "2026-03-02T10:15:00.000000Z"
    }
  ],
  "message": "Data berhasil diambil"
}
```

---

#### `POST /api/tasks`

Creates a new task with status defaulting to `pending`.

**Request Body**

```json
{
  "title": "Write unit tests"
}
```

**Validation Rules**

| Field | Rules |
|---|---|
| `title` | required · string · max:255 |

**Response — 201 Created**

```json
{
  "success": true,
  "data": {
    "id":         2,
    "title":      "Write unit tests",
    "status":     "pending",
    "created_at": "2026-03-02T10:05:00.000000Z",
    "updated_at": "2026-03-02T10:05:00.000000Z"
  },
  "message": "Task berhasil ditambahkan"
}
```

---

#### `PUT /api/tasks/{id}`

Updates a task's title, status, or both.

**Request Body** *(all fields optional)*

```json
{
  "title":  "Write unit tests (updated)",
  "status": "done"
}
```

**Validation Rules**

| Field | Rules |
|---|---|
| `title` | sometimes · string · max:255 |
| `status` | sometimes · `pending` or `done` |

**Response — 200 OK**

```json
{
  "success": true,
  "data": {
    "id":     2,
    "title":  "Write unit tests (updated)",
    "status": "done",
    ...
  },
  "message": "Task berhasil diupdate"
}
```

**Response — 404 Not Found**

```json
{
  "success": false,
  "data":    null,
  "message": "Task tidak ditemukan"
}
```

---

#### `DELETE /api/tasks/{id}`

Permanently deletes a task by ID.

**Response — 200 OK**

```json
{
  "success": true,
  "data":    null,
  "message": "Task berhasil dihapus"
}
```

---

## Authentication

This project implements **simple static token authentication** designed for a single-user assessment context:

1. The client calls `POST /api/login` with the hardcoded credentials.
2. The server returns the token `simple-admin-token-2024`.
3. The client stores this token in `localStorage` and attaches it to every subsequent request as a `Bearer` token.
4. The `SimpleTokenAuth` middleware validates the token on every protected route.

> **Note:** See [Production Improvements](#known-limitations--production-improvements) for how this would be upgraded in a real application.

---

## Frontend

The frontend is a single Vue 3 component (`resources/js/pages/Tasks.vue`) served by Inertia.js at the `/tasks` route.

### Features

| Feature | Description |
|---|---|
| **Login Screen** | Form with email/password, inline error messages |
| **Task List** | Live-updating list with status badges |
| **Add Task** | Input bar with Enter key support and loading state |
| **Toggle Status** | Click the circle checkbox to switch `pending` ↔ `done` |
| **Edit Task** | Inline edit with Enter to save / Escape to cancel |
| **Delete Task** | Confirmation prompt before deletion |
| **Sidebar Filters** | Filter by All / Pending / Done |
| **Progress Bar** | Visual indicator of completion percentage |

### Design System

| Token | Value |
|---|---|
| Primary | `#16a34a` (green-600) |
| Background | `#f9fafb` (gray-50) |
| Surface | `#ffffff` |
| Border | `#e5e7eb` (gray-200) |
| Text | `#111827` (gray-900) |

---

## Technical Decisions

### Why a Static Token Instead of Sanctum?

The assessment explicitly asks for *token sederhana* (simple token). Sanctum requires a `personal_access_tokens` table and user model integration. A static middleware (`SimpleTokenAuth`) achieves the same security surface for the assessment scope with zero additional dependencies.

### Why Inertia.js Instead of a Separate SPA?

Inertia.js eliminates the need for a separate build pipeline or a dedicated API for page routing. The frontend and backend share one server, one session, and one deployment artifact — ideal for a time-constrained assessment.

### Why `response()->json()` on the Base Controller?

Centralizing the response format in `Controller::jsonResponse()` ensures every endpoint returns the same envelope (`success`, `data`, `message`). If the format ever needs to change, there is exactly one place to update it.

### Why `only()` in the Update Endpoint?

```php
$task->update($request->only(['title', 'status']));
```

Using `only()` instead of `all()` whitelists acceptable fields at the controller level — a defense-in-depth measure against mass-assignment vulnerabilities, even with `$fillable` already defined on the model.

---

## Known Limitations & Production Improvements

| Area | Current (Assessment) | Production Recommendation |
|---|---|---|
| **Authentication** | Hardcoded credentials + static token | Laravel Sanctum with database tokens, bcrypt-hashed passwords, token expiry |
| **Authorization** | Single shared token | Per-user tokens, scoped permissions (Policies/Gates) |
| **Session Driver** | Database sessions | Redis for horizontal scaling |
| **Validation** | Basic field rules | Form Request classes for separation of concerns |
| **Error Handling** | Raw exceptions bubble up | Global exception handler returning consistent JSON errors |
| **Testing** | None | PHPUnit feature tests for every endpoint, Vitest for Vue components |
| **Rate Limiting** | None | `throttle:60,1` on login endpoint to prevent brute force |
| **HTTPS** | HTTP only | TLS termination at reverse proxy (Nginx/Caddy) |
| **Frontend State** | Local `ref()` arrays | Pinia store for global state, optimistic updates |

---

## License

This project was created for the purpose of a technical assessment and is not intended for production use.
