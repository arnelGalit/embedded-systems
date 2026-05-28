# Plant Monitoring System - Authentication & Admin System

## System Overview

The Plant Monitoring System now includes a complete user authentication and admin management system. All dashboard pages require login to access.

## Files Created

### Core Authentication Files
- **`session_config.php`** - Session management and authentication helpers
- **`login.php`** - Login page for users
- **`user_menu.php`** - User dropdown menu component (appears in top-right of all dashboards)
- **`profile.php`** - User profile editing page with password change functionality
- **`users_manager.php`** - Admin-only page for managing users

### Updated Files
- **`live_dashboard.php`** - Now requires login + includes user menu
- **`dashboard.php`** - Now requires login + includes user menu
- **`insert_sensor.php`** - Updated for new database name

## Default Login Credentials

**Setup your admin password first:**

1. Visit: `http://localhost/plant_monitoring/setup_admin.php`
2. This will set the admin password to: `admin123`

**Default Admin Account:**
- Username: `admin`
- Password: `admin123`

## Database Schema

### Users Table
```sql
CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  first_name VARCHAR(100),
  middle_name VARCHAR(100),
  last_name VARCHAR(100),
  username VARCHAR(100) UNIQUE,
  password VARCHAR(255),
  email VARCHAR(150) UNIQUE,
  contact_number VARCHAR(20),
  profile_picture LONGBLOB,
  role ENUM('admin', 'user'),
  created_at TIMESTAMP,
  updated_at TIMESTAMP
);
```

## Features

### 1. Login Page (`login.php`)
- Simple, secure login interface
- Username and password authentication
- Password hashing with bcrypt
- Redirects to dashboard on successful login

### 2. User Profile (`profile.php`)
- View and edit user profile information
  - First name
  - Middle name (optional)
  - Last name
  - Email address
- Change password with validation modal
  - Password must be at least 8 characters
  - Must contain numbers, letters, and special characters
  - Verification of current password before change
- Save changes or close without saving

### 3. User Dropdown Menu
Located in top-right corner of all dashboards:
- Displays user's name and avatar (initials)
- Dropdown options:
  - Edit Profile
  - View Users (admin only)
  - Logout

### 4. User Management (`users_manager.php`)
**Admin-only page** with the following features:
- View all users in a table format showing:
  - Name
  - Username
  - Email
  - Contact number
  - Role (Admin/User)
  - Creation date
- Create new user button
  - Modal form for user creation
  - Input fields:
    - First name, middle name (optional), last name
    - Username (min 3 characters)
    - Email address
    - Contact number
    - Password (with strength validation)
  - Password validation:
    - Minimum 8 characters
    - Must contain numbers
    - Must contain letters
    - Must contain special characters

## Password Validation Rules

All passwords must meet these requirements:
- **Minimum Length**: 8 characters
- **Contains Number**: At least one digit (0-9)
- **Contains Letter**: At least one letter (a-z, A-Z)
- **Contains Special Character**: At least one special character (!@#$%^&*...)

## Authentication Flow

1. User visits any dashboard page
2. Session is checked via `session_config.php`
3. If not logged in → redirect to `login.php`
4. User enters credentials
5. Credentials verified against `users` table
6. Session variables set with user data
7. User logged in and redirected to dashboard

## Session Variables

When logged in, the following session variables are set:
```php
$_SESSION['user_id']     // User's database ID
$_SESSION['username']    // Username
$_SESSION['first_name']  // First name
$_SESSION['last_name']   // Last name
$_SESSION['email']       // Email address
$_SESSION['role']        // 'admin' or 'user'
```

## Logout

Click the **Logout** button in the user dropdown menu to:
1. Destroy the session
2. Clear all session variables
3. Redirect to login page

## Admin vs Regular User

### Admin Users:
- Can access all pages
- Can create and view other users
- Can access User Management page

### Regular Users:
- Can access dashboards
- Can only edit their own profile
- Cannot access User Management page

## Security Features

1. **Password Hashing**: Bcrypt algorithm (PASSWORD_BCRYPT)
2. **Session Management**: Proper session destruction on logout
3. **SQL Injection Prevention**: Prepared statements with parameterized queries
4. **Input Validation**: Username, email, password format validation
5. **Access Control**: Role-based access to admin pages

## API Notes

The `insert_sensor.php` endpoint can still be accessed by the ESP32 without authentication. This allows the device to send sensor data without requiring login.

To add authentication to the ESP32 API in the future:
- Implement API token/bearer token authentication
- Add X-API-Key header validation
- Store API tokens securely in database

## Troubleshooting

### "Login page appears on dashboard access"
- Ensure session_config.php is properly included
- Check browser cookies are enabled
- Clear browser cache and login again

### "Password validation errors"
- Ensure password has:
  - At least 8 characters
  - At least one number (0-9)
  - At least one letter (a-z or A-Z)
  - At least one special character (!@#$%^&* etc.)

### "Admin account not working"
- Run `http://localhost/plant_monitoring/setup_admin.php` to reset admin password
- Default credentials: admin / admin123

### "User menu not appearing"
- Ensure `user_menu.php` is included in the dashboard files
- Check for PHP errors in browser console

## File Locations

All authentication files should be in:
```
C:\xampp\htdocs\plant_monitoring\
├── login.php
├── session_config.php
├── user_menu.php
├── profile.php
├── users_manager.php
├── setup_admin.php
├── live_dashboard.php
├── dashboard.php
└── insert_sensor.php
```
