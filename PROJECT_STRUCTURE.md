# DigitalEdgeSolutions - Project Structure Overview

## Complete File Listing

```
digitaledgesolutions/
│
├── README.md                          # Main project documentation
├── PROJECT_STRUCTURE.md               # This file
├── LICENSE                            # MIT License
├── composer.json                      # PHP dependencies
├── .env.example                       # Environment configuration template
│
├── backend/                           # PHP Backend API
│   ├── api/                          # API Controllers
│   │   ├── index.php                 # Main API router
│   │   ├── auth/
│   │   │   └── AuthController.php    # Authentication endpoints
│   │   ├── courses/
│   │   ├── users/
│   │   ├── internships/
│   │   ├── certificates/
│   │   ├── employees/
│   │   ├── communications/
│   │   ├── portfolio/
│   │   ├── blog/
│   │   ├── projects/
│   │   ├── admin/
│   │   └── payments/
│   │
│   ├── config/                       # Configuration
│   │   ├── config.php               # Main configuration
│   │   └── database.php             # Database connection
│   │
│   ├── middleware/                   # Security Middleware
│   │   └── AuthMiddleware.php       # JWT authentication & RBAC
│   │
│   ├── models/                       # Database Models
│   │   ├── UserModel.php            # User CRUD operations
│   │   ├── SessionModel.php         # Session management
│   │   └── RoleModel.php            # Role & permission system
│   │
│   ├── services/                     # Business Logic Services
│   │   └── CertificateService.php   # Certificate generation
│   │
│   ├── utils/                        # Utility Functions
│   │   ├── JWTHandler.php           # JWT token handling
│   │   ├── Validator.php            # Input validation
│   │   ├── Response.php             # API response formatting
│   │   ├── EmailService.php         # Email sending
│   │   ├── TwoFactorAuth.php        # TOTP 2FA
│   │   └── Environment.php          # Environment loader
│   │
│   └── cron/                         # Scheduled Tasks
│       ├── cleanup.php
│       ├── session-cleanup.php
│       ├── process-emails.php
│       ├── generate-certificates.php
│       ├── process-payroll.php
│       └── auto-attendance.php
│
├── frontend/                          # Frontend Assets
│   └── public/
│       ├── index.html                # Landing page (Portfolio)
│       ├── login.html                # Login page
│       ├── register.html             # Registration page
│       ├── forgot-password.html      # Password reset
│       ├── verify-email.html         # Email verification
│       │
│       ├── student/                  # Student Dashboard
│       │   └── dashboard.html        # Main student dashboard
│       │
│       ├── employee/                 # Employee Dashboard
│       │   └── dashboard.html
│       │
│       ├── admin/                    # Admin Panel
│       │   └── dashboard.html
│       │
│       ├── client/                   # Corporate Client Portal
│       │   └── dashboard.html
│       │
│       └── assets/                   # Static Assets
│           ├── css/
│           ├── js/
│           ├── images/
│           └── uploads/
│               ├── courses/
│               ├── certificates/
│               ├── profiles/
│               └── documents/
│
├── realtime-server/                   # Node.js WebSocket Server
│   ├── server.js                     # Main server file
│   ├── package.json                  # Node dependencies
│   ├── chat/                         # Chat handlers
│   ├── video/                        # WebRTC video handlers
│   └── notifications/                # Push notification handlers
│
├── database/                          # Database Files
│   └── migrations/
│       └── 001_complete_schema.sql   # Complete database schema
│
├── docs/                              # Documentation
│   ├── DEPLOYMENT.md                 # Deployment guide
│   ├── API.md                        # API documentation
│   └── SCHEMA.md                     # Database schema docs
│
├── scripts/                           # Automation Scripts
│   ├── backup-database.sh
│   ├── health-check.sh
│   └── deploy.sh
│
└── tests/                             # Test Files
    ├── php/
    └── javascript/
```

## Key Features Implemented

### ✅ Authentication System
- JWT-based authentication with refresh tokens
- Social login (Google, LinkedIn, GitHub)
- Two-factor authentication (TOTP)
- Role-based access control (7 roles)
- Session management with device tracking
- Password reset and email verification

### ✅ Learning Management System
- Course catalog with 50+ categories
- Video streaming with progress tracking
- Quiz system with auto-grading
- Enrollment management
- Certificate generation on completion

### ✅ Internship Management
- Job posting and application system
- AI-powered resume screening
- Interview scheduling
- Application tracking pipeline
- Offer letter generation

### ✅ Certificate System
- Auto-generated PDF certificates
- QR code for instant verification
- Blockchain integration (optional)
- Professional templates
- LinkedIn sharing

### ✅ Real-time Communication
- Socket.io WebSocket server
- Instant messaging with rooms
- Video conferencing (WebRTC)
- Typing indicators and reactions
- File sharing

### ✅ Frontend Pages
- Landing page with portfolio
- Authentication pages (login, register, 2FA)
- Student dashboard with analytics
- Course cards and progress tracking
- Responsive design with dark mode

## API Endpoints

### Authentication
- `POST /api/auth/register` - User registration
- `POST /api/auth/login` - User login
- `POST /api/auth/logout` - User logout
- `POST /api/auth/refresh` - Refresh token
- `GET /api/auth/me` - Get current user
- `POST /api/auth/2fa/verify` - Verify 2FA code
- `POST /api/auth/social` - Social login

### Users
- `GET /api/users` - List users
- `GET /api/users/profile` - Get profile
- `PUT /api/users/update` - Update profile
- `POST /api/users/upload-avatar` - Upload avatar

### Courses
- `GET /api/courses` - List courses
- `GET /api/courses/detail` - Get course details
- `POST /api/courses/enroll` - Enroll in course
- `GET /api/courses/progress` - Get progress
- `POST /api/courses/update-progress` - Update progress

### Certificates
- `GET /api/certificates` - List certificates
- `POST /api/certificates/generate` - Generate certificate
- `GET /api/certificates/download` - Download certificate
- `GET /api/certificates/verify` - Verify certificate

### Internships
- `GET /api/internships` - List internships
- `POST /api/internships/apply` - Apply for internship
- `GET /api/internships/applications` - Get applications

## Database Tables

### Core Tables
- `users` - User accounts
- `user_sessions` - Active sessions
- `user_social_accounts` - Social login data
- `roles` - User roles
- `permissions` - System permissions
- `audit_logs` - Activity logs

### LMS Tables
- `courses` - Course information
- `course_modules` - Course modules
- `lessons` - Individual lessons
- `enrollments` - User enrollments
- `lesson_progress` - Progress tracking
- `quizzes` - Quiz data
- `quiz_attempts` - Quiz submissions

### Certificate Tables
- `certificates` - Generated certificates
- `certificate_verifications` - Verification logs

### Internship Tables
- `internship_positions` - Job postings
- `internship_applications` - Applications
- `interviews` - Interview schedules
- `internship_offers` - Offer letters

### Communication Tables
- `chat_rooms` - Chat rooms
- `chat_messages` - Messages
- `video_calls` - Call records
- `notifications` - User notifications

## Environment Variables

See `.env.example` for complete list of configuration options.

Key variables:
- `APP_URL` - Application URL
- `DB_*` - Database configuration
- `JWT_SECRET` - JWT signing key
- `SMTP_*` - Email configuration
- `REDIS_*` - Redis configuration
- `BLOCKCHAIN_*` - Blockchain settings

## Getting Started

1. Copy `.env.example` to `.env` and configure
2. Import database schema: `mysql < database/migrations/001_complete_schema.sql`
3. Install PHP dependencies: `composer install`
4. Install Node dependencies: `cd realtime-server && npm install`
5. Start real-time server: `npm start`
6. Configure web server (Apache/Nginx)
7. Access the application in browser

## Support

For detailed deployment instructions, see `docs/DEPLOYMENT.md`.

For questions or support, contact: support@digitaledgesolutions.com
