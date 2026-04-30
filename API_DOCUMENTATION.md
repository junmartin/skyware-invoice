# Skyware Invoice API Documentation

**Version:** 2.0 (Phase 1 + Phase 2)  
**Last Updated:** April 30, 2026  
**Base URL:** `https://your-domain.com/api`

## Table of Contents

1. [Authentication](#authentication)
2. [Client Management](#client-management)
3. [Invoice Management](#invoice-management-endpoints) (Phase 2)
4. [Response Format](#response-format)
5. [Error Handling](#error-handling)
6. [Rate Limiting](#rate-limiting)
7. [Pagination](#pagination)

---

## Authentication

The API uses **Laravel Sanctum** for token-based authentication. All protected endpoints require a valid Bearer token.

### How to Authenticate

1. Login using credentials to obtain a token
2. Include token in all subsequent requests: `Authorization: Bearer {token}`

---

## Phase 1 API Endpoints

### Authentication Endpoints

#### 1. Login
**POST** `/api/auth/login`

Authenticate user and receive access token.

**Request Body:**
```json
{
  "email": "admin@example.com",
  "password": "password123"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "user": {
      "id": 1,
      "name": "Administrator",
      "email": "admin@example.com",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    },
    "token": "1|abcd1234efgh5678ijkl9012mnop3456qrst7890",
    "token_type": "Bearer"
  }
}
```

**Error Response (401):**
```json
{
  "success": false,
  "message": "Invalid credentials",
  "errors": null
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"password123"}'
```

---

#### 2. Get Current User
**GET** `/api/auth/user`

Retrieve authenticated user information.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "User retrieved",
  "data": {
    "id": 1,
    "name": "Administrator",
    "email": "admin@example.com",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-01-01T00:00:00Z"
  }
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/api/auth/user \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 3. Logout
**POST** `/api/auth/logout`

Revoke current access token and logout.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Logout successful",
  "data": null
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 4. Refresh Token
**POST** `/api/auth/refresh`

Revoke current token and issue a new one.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Token refreshed",
  "data": {
    "user": {
      "id": 1,
      "name": "Administrator",
      "email": "admin@example.com",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    },
    "token": "2|new1234efgh5678ijkl9012mnop3456qrst7890",
    "token_type": "Bearer"
  }
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/auth/refresh \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Client Management Endpoints

#### 5. List All Clients
**GET** `/api/clients`

Retrieve paginated list of clients with optional search and filters.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (optional, integer) - Page number (default: 1)
- `per_page` (optional, integer) - Results per page (default: 20)
- `search` (optional, string) - Search by name or code
- `is_active` (optional, boolean) - Filter by active status

**Success Response (200):**
```json
{
  "success": true,
  "message": "Clients retrieved",
  "data": [
    {
      "id": 1,
      "code": "CLI001",
      "name": "Acme Corporation",
      "email": "contact@acme.com",
      "is_active": true,
      "currency": "IDR",
      "default_due_days": 30,
      "billing_address": "123 Main St, City, Country",
      "plan_name": "Professional",
      "last_billed_at": "2024-04-01T00:00:00Z",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-04-15T00:00:00Z"
    },
    {
      "id": 2,
      "code": "CLI002",
      "name": "Tech Startup Inc",
      "email": "billing@techstartup.com",
      "is_active": true,
      "currency": "IDR",
      "default_due_days": 15,
      "billing_address": "456 Tech Ave, Tech City",
      "plan_name": "Startup",
      "last_billed_at": "2024-04-01T00:00:00Z",
      "created_at": "2024-02-15T00:00:00Z",
      "updated_at": "2024-04-20T00:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 2,
    "last_page": 1,
    "from": 1,
    "to": 2,
    "has_more": false
  }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/clients?page=1&per_page=20&search=acme" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 6. Get Single Client
**GET** `/api/clients/{id}`

Retrieve detailed information for a specific client.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Client ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Client retrieved",
  "data": {
    "id": 1,
    "code": "CLI001",
    "name": "Acme Corporation",
    "email": "contact@acme.com",
    "is_active": true,
    "currency": "IDR",
    "default_due_days": 30,
    "billing_address": "123 Main St, City, Country",
    "plan_name": "Professional",
    "last_billed_at": "2024-04-01T00:00:00Z",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-04-15T00:00:00Z"
  }
}
```

**Error Response (404):**
```json
{
  "success": false,
  "message": "Client not found",
  "errors": null
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/api/clients/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 7. Create New Client
**POST** `/api/clients`

Create a new client in the system.

**Headers Required:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "code": "CLI003",
  "name": "New Company Ltd",
  "email": "billing@newcompany.com",
  "is_active": true,
  "currency": "IDR",
  "default_due_days": 30,
  "billing_address": "789 Business Blvd, Corporate City",
  "plan_name": "Enterprise"
}
```

**Success Response (201):**
```json
{
  "success": true,
  "message": "Client created successfully",
  "data": {
    "id": 3,
    "code": "CLI003",
    "name": "New Company Ltd",
    "email": "billing@newcompany.com",
    "is_active": true,
    "currency": "IDR",
    "default_due_days": 30,
    "billing_address": "789 Business Blvd, Corporate City",
    "plan_name": "Enterprise",
    "last_billed_at": null,
    "created_at": "2024-04-30T10:30:00Z",
    "updated_at": "2024-04-30T10:30:00Z"
  }
}
```

**Validation Error Response (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "code": ["Client code must be unique"],
    "email": ["Email must be a valid email address"]
  }
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/clients \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "code": "CLI003",
    "name": "New Company Ltd",
    "email": "billing@newcompany.com",
    "is_active": true,
    "currency": "IDR",
    "default_due_days": 30,
    "billing_address": "789 Business Blvd, Corporate City",
    "plan_name": "Enterprise"
  }'
```

---

#### 8. Update Client
**PUT** `/api/clients/{id}`

Update an existing client's information.

**Headers Required:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**URL Parameters:**
- `id` (required, integer) - Client ID

**Request Body (all fields optional):**
```json
{
  "name": "Updated Company Name",
  "email": "newemail@company.com",
  "is_active": true,
  "default_due_days": 45,
  "plan_name": "Premium"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Client updated successfully",
  "data": {
    "id": 3,
    "code": "CLI003",
    "name": "Updated Company Name",
    "email": "newemail@company.com",
    "is_active": true,
    "currency": "IDR",
    "default_due_days": 45,
    "billing_address": "789 Business Blvd, Corporate City",
    "plan_name": "Premium",
    "last_billed_at": null,
    "created_at": "2024-04-30T10:30:00Z",
    "updated_at": "2024-04-30T11:00:00Z"
  }
}
```

**cURL Example:**
```bash
curl -X PUT https://your-domain.com/api/clients/3 \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Updated Company Name",
    "default_due_days": 45
  }'
```

---

#### 9. Delete/Deactivate Client
**DELETE** `/api/clients/{id}`

Deactivate a client (soft delete - data is preserved).

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Client ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Client deactivated successfully",
  "data": null
}
```

**cURL Example:**
```bash
curl -X DELETE https://your-domain.com/api/clients/3 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 10. Get Client's Invoices
**GET** `/api/clients/{id}/invoices`

Retrieve all invoices for a specific client.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Client ID

**Query Parameters:**
- `page` (optional, integer) - Page number (default: 1)
- `per_page` (optional, integer) - Results per page (default: 20)
- `status` (optional, string) - Filter by status (sent, paid, void, etc.)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Client invoices retrieved",
  "data": [
    {
      "id": 101,
      "invoice_number": "INV/2024/00001",
      "status": "paid",
      "total_amount": "5000000.00",
      "issue_date": "2024-04-01",
      "due_date": "2024-05-01",
      "paid_at": "2024-04-15T00:00:00Z",
      "email_sent": true,
      "created_at": "2024-03-31T00:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1,
    "from": 1,
    "to": 1,
    "has_more": false
  }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/clients/1/invoices?status=paid" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Response Format

### Success Response
All successful API responses follow this structure:

```json
{
  "success": true,
  "message": "Descriptive message",
  "data": {}
}
```

### Paginated Response
List endpoints return paginated responses:

```json
{
  "success": true,
  "message": "Descriptive message",
  "data": [],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5,
    "from": 1,
    "to": 20,
    "has_more": true
  }
}
```

### Error Response
All error responses follow this structure:

```json
{
  "success": false,
  "message": "Error message",
  "errors": {}
}
```

---

## Error Handling

### HTTP Status Codes

| Code | Meaning |
|------|---------|
| 200 | OK - Request succeeded |
| 201 | Created - Resource created successfully |
| 400 | Bad Request - Invalid request data |
| 401 | Unauthorized - Missing or invalid token |
| 404 | Not Found - Resource not found |
| 422 | Unprocessable Entity - Validation error |
| 500 | Internal Server Error |

### Common Error Responses

**401 Unauthorized:**
```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": null
}
```

**404 Not Found:**
```json
{
  "success": false,
  "message": "Resource not found",
  "errors": null
}
```

**422 Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message"],
    "another_field": ["Error message 1", "Error message 2"]
  }
}
```

---

## Pagination

All list endpoints support pagination:

### Query Parameters
- `page` - Page number (default: 1)
- `per_page` - Results per page (default: 20, max: 100)

### Example Request
```bash
curl -X GET "https://your-domain.com/api/clients?page=2&per_page=50" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Response Includes
```json
{
  "pagination": {
    "current_page": 2,
    "per_page": 50,
    "total": 150,
    "last_page": 3,
    "from": 51,
    "to": 100,
    "has_more": true
  }
}
```

---

## Rate Limiting

Currently no rate limiting is enforced. Rate limiting will be added in future phases.

---

## Phase 2 API Endpoints (Invoice Management)

### Invoice Management Endpoints

#### 11. List All Invoices
**GET** `/api/invoices`

Retrieve paginated list of invoices with filters and sorting.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (optional, integer) - Page number (default: 1)
- `per_page` (optional, integer) - Results per page (default: 20)
- `status` (optional, string) - Filter by status (generating, generated, sent, paid, void, etc.)
- `client_id` (optional, integer) - Filter by client ID
- `from` (optional, date) - Filter invoices from this date (YYYY-MM-DD)
- `to` (optional, date) - Filter invoices to this date (YYYY-MM-DD)
- `show_void` (optional, boolean) - Include void invoices (default: false)
- `sort_dir` (optional, string) - Sort direction: asc or desc (default: desc)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Invoices retrieved",
  "data": [
    {
      "id": 1,
      "invoice_number": "INV/2024/00001",
      "client_id": 1,
      "client": {
        "id": 1,
        "code": "CLI001",
        "name": "Acme Corporation",
        "email": "contact@acme.com",
        "is_active": true,
        "currency": "IDR",
        "default_due_days": 30,
        "billing_address": "123 Main St",
        "plan_name": "Professional",
        "last_billed_at": "2024-04-01T00:00:00Z",
        "created_at": "2024-01-01T00:00:00Z",
        "updated_at": "2024-04-15T00:00:00Z"
      },
      "invoice_type": "adhoc",
      "status": "paid",
      "currency": "IDR",
      "subtotal": "5000000.00",
      "tax_amount": "0.00",
      "total_amount": "5000000.00",
      "issue_date": "2024-04-01",
      "due_date": "2024-05-01",
      "generated_at": "2024-03-31T10:00:00Z",
      "ready_to_send_at": "2024-03-31T10:00:00Z",
      "sent_at": "2024-04-01T08:00:00Z",
      "paid_at": "2024-04-15T14:30:00Z",
      "stamping_required": true,
      "stamping_status": "completed",
      "generated_pdf_path": "invoices/INV_2024_00001.pdf",
      "stamped_pdf_path": "invoices/INV_2024_00001_stamped.pdf",
      "email_sent": true,
      "email_send_mode_snapshot": "auto",
      "last_error": null,
      "created_at": "2024-03-31T00:00:00Z",
      "updated_at": "2024-04-15T00:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 50,
    "last_page": 3,
    "from": 1,
    "to": 20,
    "has_more": true
  }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/invoices?status=paid&client_id=1&per_page=20" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 12. Get Single Invoice
**GET** `/api/invoices/{id}`

Retrieve detailed information for a specific invoice including all relationships.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Invoice ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Invoice retrieved",
  "data": {
    "id": 1,
    "invoice_number": "INV/2024/00001",
    "client_id": 1,
    "client": { ... },
    "details": [
      {
        "id": 1,
        "invoice_id": 1,
        "item_code": "ADHOC",
        "description": "Service Description",
        "quantity": 1,
        "unit_price": "5000000.00",
        "line_total": "5000000.00",
        "position": 1,
        "created_at": "2024-03-31T00:00:00Z",
        "updated_at": "2024-03-31T00:00:00Z"
      }
    ],
    "payment_record": {
      "id": 1,
      "invoice_id": 1,
      "provider": "xendit",
      "provider_reference": "xendit_id_123",
      "external_id": "INV/2024/00001",
      "payment_url": "https://xendit.app/web/...",
      "status": "paid",
      "amount": "5000000.00",
      "paid_at": "2024-04-15T14:30:00Z",
      "created_at": "2024-03-31T00:00:00Z",
      "updated_at": "2024-04-15T00:00:00Z"
    },
    "email_logs": [
      {
        "id": 1,
        "invoice_id": 1,
        "status": "sent",
        "recipient": "contact@acme.com",
        "subject": "Invoice INV/2024/00001",
        "attachment_types": ["pdf"],
        "attempted_at": "2024-04-01T08:00:00Z",
        "sent_at": "2024-04-01T08:00:00Z",
        "error_message": null,
        "created_at": "2024-04-01T08:00:00Z",
        "updated_at": "2024-04-01T08:00:00Z"
      }
    ],
    "status_histories": [
      {
        "id": 1,
        "invoice_id": 1,
        "from_status": "generating",
        "to_status": "generated",
        "note": "Adhoc invoice PDF generated",
        "performer_id": 1,
        "performer_name": "Administrator",
        "created_at": "2024-03-31T10:00:00Z"
      }
    ],
    "created_at": "2024-03-31T00:00:00Z",
    "updated_at": "2024-04-15T00:00:00Z"
  }
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/api/invoices/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 13. Create Adhoc Invoice
**POST** `/api/invoices`

Create a new adhoc invoice outside of billing cycles.

**Headers Required:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "client_id": 1,
  "description": "Service provided in April",
  "amount": 5000000,
  "issue_date": "2024-04-01",
  "due_date": "2024-05-01",
  "add_stamp_duty": true,
  "save_as_draft": false
}
```

**Fields:**
- `client_id` (required, integer) - Client ID
- `description` (required, string) - Item/service description
- `amount` (required, number) - Amount in invoice currency
- `issue_date` (required, date) - Invoice issue date (YYYY-MM-DD)
- `due_date` (required, date) - Invoice due date (YYYY-MM-DD), must be >= issue_date
- `add_stamp_duty` (optional, boolean) - Add IDR 10,000 stamp duty (default: false)
- `save_as_draft` (optional, boolean) - Save as draft without confirming (default: false)

**Success Response (201):**
```json
{
  "success": true,
  "message": "Invoice created successfully",
  "data": {
    "id": 1,
    "invoice_number": "INV/2024/00001",
    "client_id": 1,
    "invoice_type": "adhoc",
    "status": "ready_to_send",
    "total_amount": "5010000.00",
    ...
  }
}
```

**Validation Error Response (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "amount": ["Amount must be greater than 0"],
    "due_date": ["Due date must be after or equal to issue date"]
  }
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/invoices \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "client_id": 1,
    "description": "Service provided in April",
    "amount": 5000000,
    "issue_date": "2024-04-01",
    "due_date": "2024-05-01",
    "add_stamp_duty": true,
    "save_as_draft": false
  }'
```

---

#### 14. Confirm Draft Invoice
**POST** `/api/invoices/{id}/confirm-draft`

Confirm a draft invoice saved with `save_as_draft: true`.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Invoice ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Draft invoice confirmed",
  "data": { ... invoice object ... }
}
```

**Error Responses:**
- 400: Only adhoc invoices can be confirmed
- 400: Only generated draft invoices can be confirmed
- 400: This invoice is already confirmed

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/invoices/1/confirm-draft \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 15. Send Invoice via Email
**POST** `/api/invoices/{id}/send-email`

Send invoice to client via email.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Invoice ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Invoice sent successfully",
  "data": { ... updated invoice object ... }
}
```

**Error Responses:**
- 400: Cannot send void invoice
- 500: Failed to send invoice (with error message)

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/invoices/1/send-email \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 16. Void Invoice
**POST** `/api/invoices/{id}/void`

Mark an invoice as void (cancelled).

**Headers Required:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**URL Parameters:**
- `id` (required, integer) - Invoice ID

**Request Body:**
```json
{
  "void_reason": "Duplicate invoice, customer requested cancellation"
}
```

**Fields:**
- `void_reason` (optional, string) - Reason for voiding the invoice

**Success Response (200):**
```json
{
  "success": true,
  "message": "Invoice marked as void",
  "data": { ... invoice object with status: "void" ... }
}
```

**Error Response (400):**
```json
{
  "success": false,
  "message": "Invoice is already void",
  "errors": null
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/invoices/1/void \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"void_reason": "Duplicate invoice"}'
```

---

#### 17. Mark Invoice as Sent
**POST** `/api/invoices/{id}/mark-sent`

Mark invoice as manually sent (when not sent via API).

**Headers Required:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**URL Parameters:**
- `id` (required, integer) - Invoice ID

**Request Body:**
```json
{
  "manual_sent_at": "2024-04-01T08:00:00Z",
  "manual_note": "Sent via WhatsApp"
}
```

**Fields:**
- `manual_sent_at` (optional, datetime) - When the invoice was sent (ISO 8601 format, default: now)
- `manual_note` (optional, string) - Additional note about sending

**Success Response (200):**
```json
{
  "success": true,
  "message": "Invoice marked as sent",
  "data": { ... invoice object with status: "sent" ... }
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/invoices/1/mark-sent \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "manual_note": "Sent via WhatsApp"
  }'
```

---

#### 18. Mark Invoice as Paid
**POST** `/api/invoices/{id}/mark-as-paid`

Record a manual payment for an invoice.

**Headers Required:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**URL Parameters:**
- `id` (required, integer) - Invoice ID

**Request Body:**
```json
{
  "paid_at": "2024-04-15T14:30:00Z",
  "payment_note": "Bank transfer received"
}
```

**Fields:**
- `paid_at` (optional, datetime) - When the payment was received (ISO 8601 format, default: now)
- `payment_note` (optional, string) - Payment method or additional note

**Success Response (200):**
```json
{
  "success": true,
  "message": "Invoice marked as paid",
  "data": { ... invoice object with status: "paid" ... }
}
```

**Error Responses:**
- 400: Cannot mark void invoice as paid
- 400: Invoice is already marked as paid

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/invoices/1/mark-as-paid \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "payment_note": "Bank transfer received"
  }'
```

---

#### 19. Get Invoice Status History
**GET** `/api/invoices/{id}/status-history`

Retrieve the complete status change history for an invoice.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Invoice ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Invoice status history retrieved",
  "data": [
    {
      "id": 1,
      "invoice_id": 1,
      "from_status": null,
      "to_status": "generating",
      "note": "Adhoc invoice creation started via API",
      "performer_id": 1,
      "performer_name": "Administrator",
      "created_at": "2024-03-31T10:00:00Z"
    },
    {
      "id": 2,
      "invoice_id": 1,
      "from_status": "generating",
      "to_status": "generated",
      "note": "Adhoc invoice PDF generated",
      "performer_id": 1,
      "performer_name": "Administrator",
      "created_at": "2024-03-31T10:05:00Z"
    },
    {
      "id": 3,
      "invoice_id": 1,
      "from_status": "generated",
      "to_status": "ready_to_send",
      "note": "Adhoc invoice ready to send",
      "performer_id": 1,
      "performer_name": "Administrator",
      "created_at": "2024-03-31T10:05:00Z"
    },
    {
      "id": 4,
      "invoice_id": 1,
      "from_status": "ready_to_send",
      "to_status": "sent",
      "note": "Invoice sent to client via email",
      "performer_id": 1,
      "performer_name": "Administrator",
      "created_at": "2024-04-01T08:00:00Z"
    },
    {
      "id": 5,
      "invoice_id": 1,
      "from_status": "sent",
      "to_status": "paid",
      "note": "Marked paid by Xendit webhook",
      "performer_id": null,
      "performer_name": null,
      "created_at": "2024-04-15T14:30:00Z"
    }
  ]
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/api/invoices/1/status-history \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 20. Download Invoice PDF
**GET** `/api/invoices/{id}/pdf`

Download the generated invoice PDF file.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Invoice ID

**Success Response (200):**
- Returns PDF file with Content-Type: application/pdf

**Error Response (404):**
```json
{
  "success": false,
  "message": "PDF not available for this invoice",
  "errors": null
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/api/invoices/1/pdf \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -o invoice_2024_00001.pdf
```

---

#### 21. Download Stamped PDF
**GET** `/api/invoices/{id}/stamped-pdf`

Download the stamped (e-meterai) invoice PDF file.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Invoice ID

**Success Response (200):**
- Returns PDF file with Content-Type: application/pdf

**Error Response (404):**
```json
{
  "success": false,
  "message": "Stamped PDF not available for this invoice",
  "errors": null
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/api/invoices/1/stamped-pdf \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -o invoice_2024_00001_stamped.pdf
```

---

## Upcoming Phases

### Phase 3 (Advanced Operations)
- Billing cycle management
- Payment record management
- Email log retrieval
- Settings management
- Bulk operations

---

## Phase 3 API Endpoints (Advanced Operations)

### Billing Cycle Management Endpoints

#### 22. List All Billing Cycles
**GET** `/api/billing-cycles`

Retrieve paginated list of all billing cycles.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (optional, integer) - Page number (default: 1)
- `per_page` (optional, integer) - Results per page (default: 20)
- `year` (optional, integer) - Filter by year
- `is_generated` (optional, boolean) - Filter by generated status

**Success Response (200):**
```json
{
  "success": true,
  "message": "Billing cycles retrieved",
  "data": [
    {
      "id": 1,
      "year": 2024,
      "month": 4,
      "cycle_name": "April 2024",
      "is_generated": true,
      "generated_at": "2024-04-01T08:00:00Z",
      "invoices_count": 25,
      "total_amount": "125000000.00",
      "paid_amount": "45000000.00",
      "created_at": "2024-03-31T00:00:00Z",
      "updated_at": "2024-04-15T00:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 20,
    "total": 12,
    "last_page": 1,
    "from": 1,
    "to": 12,
    "has_more": false
  }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/billing-cycles?year=2024" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 23. Get Single Billing Cycle
**GET** `/api/billing-cycles/{id}`

Retrieve detailed information for a specific billing cycle.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Billing cycle ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Billing cycle retrieved",
  "data": {
    "id": 1,
    "year": 2024,
    "month": 4,
    "cycle_name": "April 2024",
    "is_generated": true,
    "generated_at": "2024-04-01T08:00:00Z",
    "invoices_count": 25,
    "total_amount": "125000000.00",
    "paid_amount": "45000000.00",
    "created_at": "2024-03-31T00:00:00Z",
    "updated_at": "2024-04-15T00:00:00Z"
  }
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/api/billing-cycles/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 24. Preview Next Billing Cycle Generation (Dry Run)
**GET** `/api/billing-cycles/next/preview-generation`

Preview which invoices would be generated for the next billing cycle without creating any invoice records.

This endpoint is read-only and safe for AI agents to test planning scenarios.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (optional, integer) - Page number (default: 1)
- `per_page` (optional, integer) - Results per page (default: 50, max: 500)
- `q` (optional, string) - Filter clients by code or name

**Success Response (200):**
```json
{
  "success": true,
  "message": "Next cycle generation preview retrieved",
  "data": {
    "cycle": {
      "year": 2026,
      "month": 5,
      "label": "2026-05",
      "cycle_start_date": "2026-05-01",
      "cycle_end_date": "2026-05-31",
      "scheduled_run_at": "2026-05-01T08:00:00+07:00",
      "exists_in_db": true,
      "billing_cycle_id": 12
    },
    "assumptions": {
      "base_amount_per_invoice": 1000000,
      "tax_rate": 0.11,
      "tax_amount_per_invoice": 110000,
      "total_per_invoice": 1110000
    },
    "summary": {
      "would_generate_count": 25,
      "projected_total_amount": 27750000
    },
    "candidates": [
      {
        "client_id": 4,
        "client_code": "CLI004",
        "client_name": "Acme Logistics",
        "currency": "IDR",
        "projected_invoice_number": "INV-CLI004-202605",
        "projected_subtotal": 1000000,
        "projected_tax_amount": 110000,
        "projected_total_amount": 1110000
      }
    ],
    "pagination": {
      "current_page": 1,
      "per_page": 50,
      "total": 25,
      "last_page": 1,
      "from": 1,
      "to": 25,
      "has_more": false
    }
  }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/billing-cycles/next/preview-generation?per_page=200&q=CLI" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 25. Generate Next Billing Cycle
**POST** `/api/billing-cycles/generate-next`

Generate invoices for all active clients for the next billing cycle.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Success Response (201):**
```json
{
  "success": true,
  "message": "Billing cycle generated successfully",
  "data": {
    "cycle": {
      "id": 2,
      "year": 2024,
      "month": 5,
      "cycle_name": "May 2024",
      "is_generated": true,
      "generated_at": "2024-05-01T08:00:00Z",
      "invoices_count": 25,
      "total_amount": "125000000.00",
      "paid_amount": "0.00",
      "created_at": "2024-05-01T00:00:00Z",
      "updated_at": "2024-05-01T00:00:00Z"
    },
    "invoices_created": 25
  }
}
```

**Error Response (500):**
```json
{
  "success": false,
  "message": "Failed to generate billing cycle: Error message",
  "errors": null
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/billing-cycles/generate-next \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 26. Get Billing Cycle's Invoices
**GET** `/api/billing-cycles/{id}/invoices`

Retrieve all invoices for a specific billing cycle.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Billing cycle ID

**Query Parameters:**
- `page` (optional, integer) - Page number (default: 1)
- `per_page` (optional, integer) - Results per page (default: 20)
- `status` (optional, string) - Filter by status
- `client_id` (optional, integer) - Filter by client ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Cycle invoices retrieved",
  "data": [
    {
      "id": 101,
      "invoice_number": "INV/2024/00001",
      "status": "paid",
      "total_amount": "5000000.00",
      ...
    }
  ],
  "pagination": { ... }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/billing-cycles/1/invoices?status=paid" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Payment Record Management Endpoints

#### 27. List All Payment Records
**GET** `/api/payments`

Retrieve paginated list of all payment records.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (optional, integer) - Page number (default: 1)
- `per_page` (optional, integer) - Results per page (default: 20)
- `status` (optional, string) - Filter by status (pending, paid, expired, void)
- `provider` (optional, string) - Filter by provider (xendit)
- `paid` (optional, boolean) - Filter by paid status

**Success Response (200):**
```json
{
  "success": true,
  "message": "Payment records retrieved",
  "data": [
    {
      "id": 1,
      "invoice_id": 1,
      "provider": "xendit",
      "provider_reference": "xendit_id_123",
      "external_id": "INV/2024/00001",
      "payment_url": "https://xendit.app/web/...",
      "status": "paid",
      "amount": "5000000.00",
      "paid_at": "2024-04-15T14:30:00Z",
      "created_at": "2024-03-31T00:00:00Z",
      "updated_at": "2024-04-15T00:00:00Z"
    }
  ],
  "pagination": { ... }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/payments?status=paid&provider=xendit" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 28. Get Single Payment Record
**GET** `/api/payments/{id}`

Retrieve detailed information for a specific payment record.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Payment record ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Payment record retrieved",
  "data": {
    "id": 1,
    "invoice_id": 1,
    "provider": "xendit",
    "provider_reference": "xendit_id_123",
    "external_id": "INV/2024/00001",
    "payment_url": "https://xendit.app/web/...",
    "status": "paid",
    "amount": "5000000.00",
    "paid_at": "2024-04-15T14:30:00Z",
    "created_at": "2024-03-31T00:00:00Z",
    "updated_at": "2024-04-15T00:00:00Z"
  }
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/api/payments/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 29. Sync Payment with Xendit
**POST** `/api/payments/{id}/sync-xendit`

Sync a payment record's status with Xendit to get the latest payment status.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Payment record ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Payment status synced successfully",
  "data": {
    "id": 1,
    "invoice_id": 1,
    "provider": "xendit",
    "status": "paid",
    "paid_at": "2024-04-15T14:30:00Z",
    ...
  }
}
```

**Error Responses:**
- 400: This payment record is not from Xendit provider
- 500: Failed to sync payment

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/payments/1/sync-xendit \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 30. Get Invoice's Payment Records
**GET** `/api/invoices/{invoice_id}/payments`

Retrieve all payment records for a specific invoice.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `invoice_id` (required, integer) - Invoice ID

**Query Parameters:**
- `page` (optional, integer) - Page number (default: 1)
- `per_page` (optional, integer) - Results per page (default: 20)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Invoice payment records retrieved",
  "data": [ ... ],
  "pagination": { ... }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/invoices/1/payments" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Email Log Endpoints

#### 31. List All Email Logs
**GET** `/api/email-logs`

Retrieve paginated list of all email logs.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Query Parameters:**
- `page` (optional, integer) - Page number (default: 1)
- `per_page` (optional, integer) - Results per page (default: 20)
- `status` (optional, string) - Filter by status (sent, failed, bounced)
- `recipient` (optional, string) - Filter by recipient email
- `from` (optional, date) - Filter from this date (YYYY-MM-DD)
- `to` (optional, date) - Filter to this date (YYYY-MM-DD)

**Success Response (200):**
```json
{
  "success": true,
  "message": "Email logs retrieved",
  "data": [
    {
      "id": 1,
      "invoice_id": 1,
      "status": "sent",
      "recipient": "contact@acme.com",
      "subject": "Invoice INV/2024/00001",
      "attachment_types": ["pdf"],
      "attempted_at": "2024-04-01T08:00:00Z",
      "sent_at": "2024-04-01T08:00:05Z",
      "error_message": null,
      "created_at": "2024-04-01T08:00:00Z",
      "updated_at": "2024-04-01T08:00:05Z"
    }
  ],
  "pagination": { ... }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/email-logs?status=sent&from=2024-04-01" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 32. Get Invoice's Email Logs
**GET** `/api/invoices/{id}/email-logs`

Retrieve all email logs for a specific invoice.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `id` (required, integer) - Invoice ID

**Query Parameters:**
- `page` (optional, integer) - Page number (default: 1)
- `per_page` (optional, integer) - Results per page (default: 20)
- `status` (optional, string) - Filter by status

**Success Response (200):**
```json
{
  "success": true,
  "message": "Invoice email logs retrieved",
  "data": [ ... ],
  "pagination": { ... }
}
```

**cURL Example:**
```bash
curl -X GET "https://your-domain.com/api/invoices/1/email-logs" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

### Settings Management Endpoints

#### 33. Get All Settings
**GET** `/api/settings`

Retrieve all application settings.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Settings retrieved",
  "data": [
    {
      "key": "send_mode",
      "value": "auto",
      "description": "Email sending mode: auto or manual",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-04-30T00:00:00Z"
    },
    {
      "key": "company_name",
      "value": "Skyware Systems",
      "description": "Company name for invoices",
      "created_at": "2024-01-01T00:00:00Z",
      "updated_at": "2024-01-01T00:00:00Z"
    }
  ]
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/api/settings \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 34. Get Specific Setting
**GET** `/api/settings/{key}`

Retrieve a specific application setting by key.

**Headers Required:**
```
Authorization: Bearer {token}
```

**URL Parameters:**
- `key` (required, string) - Setting key

**Success Response (200):**
```json
{
  "success": true,
  "message": "Setting retrieved",
  "data": {
    "key": "send_mode",
    "value": "auto",
    "description": "Email sending mode",
    "created_at": "2024-01-01T00:00:00Z",
    "updated_at": "2024-04-30T00:00:00Z"
  }
}
```

**Error Response (404):**
```json
{
  "success": false,
  "message": "Setting not found",
  "errors": null
}
```

**cURL Example:**
```bash
curl -X GET https://your-domain.com/api/settings/send_mode \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 35. Update Settings
**PUT** `/api/settings`

Update one or multiple application settings.

**Headers Required:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "send_mode": "manual",
  "settings": {
    "company_name": "Updated Company Name",
    "invoice_prefix": "NEW_PREFIX"
  }
}
```

**Fields:**
- `send_mode` (optional, string) - Email sending mode: "auto" or "manual"
- `settings` (optional, object) - Other key-value settings to update

**Success Response (200):**
```json
{
  "success": true,
  "message": "Settings updated successfully",
  "data": [
    {
      "key": "send_mode",
      "value": "manual",
      ...
    },
    {
      "key": "company_name",
      "value": "Updated Company Name",
      ...
    }
  ]
}
```

**cURL Example:**
```bash
curl -X PUT https://your-domain.com/api/settings \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "send_mode": "manual"
  }'
```

---

### Bulk Operation Endpoints

#### 36. Send All Ready Invoices
**POST** `/api/bulk/send-all-ready`

Send all invoices that are ready to send to their respective clients.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Bulk send operation completed",
  "data": {
    "sent_count": 15,
    "message": "Successfully sent 15 invoices"
  }
}
```

**Error Response (500):**
```json
{
  "success": false,
  "message": "Bulk send operation failed: Error message",
  "errors": null
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/bulk/send-all-ready \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 37. Sync All Payment Statuses
**POST** `/api/bulk/sync-payments`

Sync payment statuses for all invoices with Xendit to get the latest payment information.

**Headers Required:**
```
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Payment sync completed",
  "data": {
    "total_synced": 45,
    "invoices_updated": 8,
    "message": "Synced 45 payment records, updated 8 invoices"
  }
}
```

**Error Response (500):**
```json
{
  "success": false,
  "message": "Bulk sync operation failed: Error message",
  "errors": null
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/bulk/sync-payments \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

#### 38. Bulk Void Invoices
**POST** `/api/bulk/void-invoices`

Void multiple invoices at once.

**Headers Required:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "invoice_ids": [1, 5, 10, 15],
  "reason": "Duplicate invoices issued"
}
```

**Fields:**
- `invoice_ids` (required, array) - Array of invoice IDs to void
- `reason` (optional, string) - Reason for voiding

**Success Response (200):**
```json
{
  "success": true,
  "message": "Bulk void operation completed",
  "data": {
    "requested_count": 4,
    "voided_count": 4,
    "message": "Voided 4 out of 4 invoices"
  }
}
```

**Validation Error Response (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "invoice_ids": ["At least one invoice ID is required"],
    "invoice_ids.0": ["The selected invoice_ids.0 is invalid."]
  }
}
```

**cURL Example:**
```bash
curl -X POST https://your-domain.com/api/bulk/void-invoices \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "invoice_ids": [1, 5, 10],
    "reason": "Duplicate invoices"
  }'
```

---

## API Summary

**Total Endpoints: 38**

| Phase | Category | Endpoints | Total |
|-------|----------|-----------|-------|
| Phase 1 | Authentication | 4 | 4 |
| Phase 1 | Client Management | 6 | 10 |
| Phase 2 | Invoice Management | 11 | 21 |
| Phase 3 | Billing Cycles | 5 | 26 |
| Phase 3 | Payment Records | 4 | 30 |
| Phase 3 | Email Logs | 2 | 32 |
| Phase 3 | Settings | 3 | 35 |
| Phase 3 | Bulk Operations | 3 | 38 |

---

## Notes

- All timestamps are in UTC (ISO 8601 format)
- The web interface continues to work independently
- API and web interface share the same business logic and database
- Both interfaces can be used simultaneously
- Invoice status values: generating, generated, pending_stamping, ready_to_send, sending, sent, paid, failed, void
- Payment status values: pending, paid, expired, void
- Email log status values: sent, failed, bounced
- Bulk operations are performed with individual error handling (failures in one item won't stop others)

---

## Support

For issues or questions about the API, contact your system administrator or refer to the project README.
