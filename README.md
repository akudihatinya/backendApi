# Healthcare Monitoring System - Backend API

A Laravel-based RESTful API for healthcare monitoring, focusing on managing patients with hypertension (HT) and diabetes mellitus (DM) conditions.

## Overview

This system provides a comprehensive API for Puskesmas (Community Health Centers) to manage their patients' examination data and for the Health Department (Dinas) to monitor overall statistical data across all health centers.

## Key Features

- Authentication with JWT tokens (stored in localStorage)
- Role-based access control (Admin and Puskesmas)
- Patient management
- Hypertension and Diabetes examination tracking
- Statistical reporting and analytics
- Data export functionality

## API Structure

The API is organized into the following domains:

1. **Authentication** - Login, logout, refresh token, user info
2. **Admin** - User management, yearly targets setting
3. **Puskesmas** - Patient and examination management
4. **Statistics** - Data aggregation and reporting

## API Authentication

All protected endpoints require authentication using Bearer token. 

```
Authorization: Bearer {your_access_token}
```

Authentication flow:

1. **Login**: Send credentials to `/api/login`, receive access and refresh tokens
2. **Use token**: Include access token in Authorization header
3. **Refresh token**: When token expires, use `/api/refresh` with refresh token to get a new access token

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/login` | Login with username and password |
| POST | `/api/refresh` | Refresh access token using refresh token |
| POST | `/api/logout` | Logout and invalidate tokens |
| GET | `/api/user` | Get authenticated user info |
| POST | `/api/change-password` | Change user password |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/admin/users` | Get all users |
| POST | `/api/admin/users` | Create new user |
| GET | `/api/admin/users/{id}` | Get user by ID |
| PUT | `/api/admin/users/{id}` | Update user |
| DELETE | `/api/admin/users/{id}` | Delete user |
| POST | `/api/admin/users/{id}/reset-password` | Reset user password |
| GET | `/api/admin/yearly-targets` | Get yearly targets |
| POST | `/api/admin/yearly-targets` | Create yearly target |
| PUT | `/api/admin/yearly-targets/{id}` | Update yearly target |
| DELETE | `/api/admin/yearly-targets/{id}` | Delete yearly target |

### Puskesmas Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/puskesmas/patients` | Get all patients |
| POST | `/api/puskesmas/patients` | Create new patient |
| GET | `/api/puskesmas/patients/{id}` | Get patient by ID |
| PUT | `/api/puskesmas/patients/{id}` | Update patient |
| DELETE | `/api/puskesmas/patients/{id}` | Delete patient |
| POST | `/api/puskesmas/patients/{id}/examination-year` | Add examination year |
| PUT | `/api/puskesmas/patients/{id}/examination-year` | Remove examination year |
| GET | `/api/puskesmas/ht-examinations` | Get HT examinations |
| POST | `/api/puskesmas/ht-examinations` | Create HT examination |
| GET | `/api/puskesmas/ht-examinations/{id}` | Get HT examination |
| PUT | `/api/puskesmas/ht-examinations/{id}` | Update HT examination |
| DELETE | `/api/puskesmas/ht-examinations/{id}` | Delete HT examination |
| GET | `/api/puskesmas/dm-examinations` | Get DM examinations |
| POST | `/api/puskesmas/dm-examinations` | Create DM examination |
| GET | `/api/puskesmas/dm-examinations/{id}` | Get DM examination for patient |
| PUT | `/api/puskesmas/dm-examinations/{id}` | Update DM examination |
| DELETE | `/api/puskesmas/dm-examinations/{id}` | Delete DM examination |

### Statistics Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/statistics` | Get all statistics |
| GET | `/api/statistics/dashboard-statistics` | Get dashboard stats |
| GET | `/api/statistics/ht` | Get HT statistics |
| GET | `/api/statistics/dm` | Get DM statistics |
| GET | `/api/statistics/export` | Export statistics (Excel/PDF) |
| GET | `/api/statistics/monitoring` | Get monitoring report |

## Common API Parameters

- `per_page`: Number of items per page (default varies by endpoint)
- `page`: Page number for pagination
- `year`: Filter by year
- `month`: Filter by month
- `disease_type`: Filter by disease type ('ht', 'dm', 'both', 'all')
- `search`: Search term for filtering

## Local Development Setup

1. Clone the repository
2. Install dependencies:
   ```
   composer install
   ```
3. Set up environment variables:
   ```
   cp .env.example .env
   php artisan key:generate
   ```
4. Configure database connection in `.env`
5. Run migrations:
   ```
   php artisan migrate
   ```
6. Seed the database:
   ```
   php artisan db:seed
   ```
7. Start the development server:
   ```
   php artisan serve
   ```

## Frontend Integration

This API is designed to work with a separate frontend application. To integrate:

1. Configure CORS in `config/cors.php` with your frontend URL
2. Store the JWT tokens in localStorage in your frontend app
3. Include the token in all API requests to protected endpoints
4. Handle token refresh when needed

## Deployment Notes

1. Update `.env` file with production settings
2. Set up proper database credentials
3. Configure CORS to allow only your frontend application
4. Set up proper SSL for secure communication
5. Implement proper monitoring and logging