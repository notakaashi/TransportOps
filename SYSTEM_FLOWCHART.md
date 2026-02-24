# Transport Operations System - Complete Flowchart Documentation

## 🏗️ System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                DATABASE LAYER                        │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │    users    │  │   reports   │  │   routes    │ │
│  └─────────────┘  └─────────────┘  └─────────────┘ │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │ puv_units   │  │route_stops  │  │route_defs   │ │
│  └─────────────┘  └─────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

## 🔄 User Authentication Flow

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   index.php │───▶│  login.php │───▶│  register.php│
│ (Landing)   │    │  (Auth)    │    │ (Sign Up)   │
└─────────────┘    └─────────────┘    └─────────────┘
        │                   │                   │
        ▼                   ▼                   ▼
┌─────────────────────────────────────────────────────────────┐
│              SESSION MANAGEMENT                     │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐ │
│  │   Admin    │  │   Driver   │  │  Commuter  │ │
│  │   Role     │  │   Role     │  │   Role     │ │
│  └─────────────┘  └─────────────┘  └─────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

## 🎯 Main Application Flow

### Entry Point & Authentication
```
index.php (Public Landing Page)
    ├── Register → register.php → user_dashboard.php
    ├── Login → login.php → Role-based redirect
    │   ├── Admin → admin_dashboard.php
    │   ├── Driver → user_dashboard.php  
    │   └── Commuter → user_dashboard.php
    └── About → about.php
```

### Admin User Flow
```
admin_dashboard.php (Main Hub)
    ├── 📊 admin_reports.php (View/Manage Reports)
    ├── 🗺️ route_status.php (Route Monitoring)
    ├── 🛣️ manage_routes.php (Route Management)
    ├── 🔥 heatmap.php (Crowdsourcing Analytics)
    ├── 👥 user_management.php (User Administration)
    ├── 👤 profile.php (Profile Management)
    └── 🚪 logout.php (Session End)

Admin Features:
├── Report verification system
├── User activation/deactivation  
├── Route creation & management
├── Real-time notifications
├── Analytics dashboard
└── Fleet overview statistics
```

### Regular User Flow (Driver/Commuter)
```
user_dashboard.php (Personal Hub)
    ├── 📝 report.php (Submit Reports)
    ├── 🗺️ reports_map.php (View Reports Map)
    ├── 🛣️ routes.php (View Routes)
    ├── 👤 profile.php (Profile Management)
    └── 🚪 logout.php (Session End)

User Features:
├── Submit crowding reports
├── GPS-based location validation
├── Profile image upload
├── View personal report history
└── Route visualization
```

## 📊 Core System Processes

### Report Submission Flow
```
report.php
    ├── Select Route (from route_definitions)
    ├── Choose Crowd Level (Light/Moderate/Heavy)
    ├── Optional Delay Reason
    ├── GPS Location Capture
    ├── Geofence Validation (within 500m of route)
    ├── Trust Score Calculation
    └── Store in reports table
```

### Route Management Flow
```
manage_routes.php (Admin Only)
    ├── Create New Routes
    ├── Add Route Stops
    ├── Define Stop Order
    ├── Update Route Details
    └── Delete Routes

routes.php (All Users)
    ├── View Individual Routes
    ├── Combined Route View
    ├── Interactive Maps
    └── Stop Information
```

### Profile Management Flow
```
profile.php
    ├── View Profile Information
    ├── Upload Profile Image
    ├── Update Personal Details
    ├── Change Password
    └── Session Update
```

## 🔐 Security & Access Control

```
Authentication Helpers (auth_helper.php)
├── checkUserActive() - Validates active status
├── checkAdminActive() - Admin + active validation
└── Session management

Database Security (db.php)
├── PDO with prepared statements
├── Error handling
├── Connection pooling
└── Local development support
```

## 📱 Responsive Design Patterns

```
Navigation Systems:
├── Desktop: Fixed sidebar navigation
├── Mobile: Collapsible hamburger menu
├── Admin: Dark gradient sidebar
└── User: Light sidebar navigation

UI Components:
├── Tailwind CSS framework
├── Leaflet.js for maps
├── Poppins font branding
└── Responsive grid layouts
```

## 🔄 Data Flow

```
Real-time Data Pipeline:
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│ User Reports│───▶│   Database  │───▶│   Maps     │
│ (GPS +     │    │   Storage  │    │Visualization│
│Crowd Data) │    │            │    │            │
└─────────────┘    └─────────────┘    └─────────────┘
        │                   │                   │
        ▼                   ▼                   ▼
┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│ Analytics  │    │ Heatmaps   │    │ Route      │
│ Dashboard  │    │           │    │ Status     │
└─────────────┘    └─────────────┘    └─────────────┘
```

## 🎯 Key Features Matrix

| Feature | Admin | Driver | Commuter | Public |
|----------|--------|---------|----------|---------|
| View Routes | ✅ | ✅ | ✅ | ❌ |
| Submit Reports | ✅ | ✅ | ✅ | ❌ |
| Manage Routes | ✅ | ❌ | ❌ | ❌ |
| User Management | ✅ | ❌ | ❌ | ❌ |
| Analytics Dashboard | ✅ | ❌ | ❌ | ❌ |
| Profile Management | ✅ | ✅ | ✅ | ❌ |
| View Reports Map | ✅ | ✅ | ✅ | ❌ |
| Heatmap View | ✅ | ❌ | ❌ | ❌ |

## 📁 File Structure & Purpose

### Core Application Files
```
├── index.php              # Public landing page
├── login.php              # User authentication
├── register.php           # New user registration
├── logout.php             # Session termination
├── about.php              # Information page
└── profile.php            # User profile management
```

### Dashboard Files
```
├── admin_dashboard.php     # Admin main dashboard
├── user_dashboard.php     # User main dashboard
└── admin_notifications.php # Real-time notifications
```

### Reporting System
```
├── report.php             # Submit new reports
├── admin_reports.php      # View/manage all reports
├── reports_map.php        # Visualize reports on map
└── verify_report.php      # Report verification system
```

### Route Management
```
├── routes.php             # View routes (all users)
├── manage_routes.php      # Create/edit routes (admin)
├── route_status.php       # Route monitoring
└── api_routes_with_stops.php # API endpoint
```

### Analytics & Visualization
```
├── heatmap.php            # Crowdsourcing heatmap
└── tracking.php           # Advanced tracking features
```

### User Management
```
├── user_management.php    # Admin user management
├── add_user.php          # Add new users
└── create_admin.php       # Create admin accounts
```

### System Configuration
```
├── db.php                # Database connection
├── auth_helper.php        # Authentication helpers
└── js/osrm-helpers.js   # Map routing utilities
```

## 🔄 Complete User Journey

### New User Registration
```
1. index.php → register.php
2. Fill registration form
3. Account created as "Commuter"
4. Redirect to login.php
5. Login → user_dashboard.php
6. Complete profile → profile.php
7. Start using system
```

### Daily Report Submission
```
1. Login → user_dashboard.php
2. Click "Submit Report" → report.php
3. Select route from dropdown
4. Choose crowding level
5. Set location (GPS or map click)
6. Submit report
7. View in personal dashboard
```

### Admin Daily Operations
```
1. Login → admin_dashboard.php
2. Review new reports → admin_reports.php
3. Verify reports for authenticity
4. Monitor route status → route_status.php
5. Analyze patterns → heatmap.php
6. Manage users → user_management.php
7. Update routes → manage_routes.php
```

## 🚀 Technical Implementation Details

### Database Schema
```sql
users
├── id (PK)
├── name
├── email
├── password (hashed)
├── role (Admin/Driver/Commuter)
├── is_active
├── profile_image
└── created_at

reports
├── id (PK)
├── user_id (FK)
├── route_definition_id (FK)
├── puv_id (FK)
├── crowd_level
├── delay_reason
├── latitude
├── longitude
├── geofence_validated
├── trust_score
├── is_verified
├── peer_verifications
└── timestamp

route_definitions
├── id (PK)
├── name
└── created_at

route_stops
├── id (PK)
├── route_definition_id (FK)
├── stop_name
├── latitude
├── longitude
└── stop_order
```

### Session Management
```php
$_SESSION Variables:
├── user_id          # User identifier
├── user_name        # Display name
├── user_email       # Email address
├── role            # User role
└── profile_image    # Profile picture filename
```

### Security Measures
```
✅ Password hashing with PASSWORD_DEFAULT
✅ Prepared statements for SQL injection prevention
✅ Session-based authentication
✅ Role-based access control
✅ Input validation and sanitization
✅ Geofence validation for reports
✅ File upload restrictions
```

## 📊 System Performance Features

### Real-time Updates
```
├── Live report notifications
├── Dynamic map updates
├── Real-time crowd level tracking
└── Automatic dashboard refresh
```

### Mobile Responsiveness
```
├── Collapsible navigation menus
├── Touch-friendly interfaces
├── Optimized map interactions
└── Responsive grid layouts
```

### Data Validation
```
├── GPS coordinate validation
├── Route proximity checking
├── Email format verification
├── Password strength requirements
└── File type/size restrictions
```

---

*This flowchart represents the complete architecture and user flows of the Transport Operations System as implemented in the current codebase.*
