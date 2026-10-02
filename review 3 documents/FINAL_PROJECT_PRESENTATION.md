# FINAL PROJECT PRESENTATION

## SLIDE 1: Project Title
**NexusCore: Academic Symposium & Event Management System**
*Presenters: [Your Name]*

## SLIDE 2: Project Overview
- A centralized PHP MVC web application.
- Automates academic event lifecycles.
- Covers registration, scheduling, attendance, evaluation, and certificates.

## SLIDE 3: Problem Statement
- Manual, paper-based event management.
- High risk of scheduling and venue conflicts.
- Fragmented attendance and feedback mechanisms.
- Time-consuming certificate generation and verification.

## SLIDE 4: Objectives
- Centralize symposium management.
- Automate conflict detection in scheduling.
- Enable offline attendance tracking.
- Streamline verifiable certificate issuance.

## SLIDE 5: Existing System
- Disjointed tools (Excel, physical forms).
- No unified dashboard for students and admins.
- No QR-based verification.

## SLIDE 6: Proposed System
- **NexusCore Platform**: Unified dashboard.
- Role-based access control.
- Automated workflows and PDF processing.

## SLIDE 7: Final Main Module Diagram
*(Display simple rectangular flowchart with main modules: Auth, Symposium Management, Registration, Scheduling, Attendance, Evaluation, Certificates, Feedback)*

## SLIDE 8: Final System Flowchart
*(Display system flow: Admin Setup -> Student Register -> Schedule -> Execute & Track -> Evaluate -> Publish & Certify)*

## SLIDE 9: System Architecture
- Custom PHP MVC Architecture.
- Middleware for authentication.
- Service-oriented business logic (e.g., CertificateRankService).

## SLIDE 10: Technology Stack
- **Frontend**: HTML5, CSS3, Bootstrap 5.3.8, Chart.js 4.5.1
- **Backend**: PHP 8.2 (MVC)
- **Database**: MySQL (PDO)
- **Libraries**: Dompdf, FPDF/FPDI, php-qrcode

## SLIDE 11: Database / ER Overview
- 39 normalized tables.
- Key entities: Users, Students, Symposiums, Events, Applications, Attendance, Certificates.
- Strict foreign key constraints and indexing.

## SLIDE 12: Major Functional Modules
- Master Event Configuration
- Team & Registration Management
- Resource Allocation & Conflict Checking
- Attendance & Offline Sync Center
- Certificate Designer & Generator

## SLIDE 13: Important Implementation / Workflow
- **Offline Sync**: Uses browser local storage and `SyncCenterController` to queue and dispatch attendance.
- **Certificates**: Template designer dynamically maps coordinates using FPDI.

## SLIDE 14: Security and Validation
- PDO prepared statements to prevent SQL injection.
- Session hijacking prevention.
- Route-based middleware for authorization.

## SLIDE 15: Second Review Feedback and Improvements
- *Feedback documentation not found.*
- Verified iterative improvements: Team management refactor, feedback module rebuild, and certificate schema versioning.

## SLIDE 16: Testing
- Unit testing with PHPUnit.
- Dedicated tests for Certificate Uniqueness and Filesystem Atomicity.

## SLIDE 17: Final Results
- Fully functional symposium lifecycle management.
- Reliable offline attendance syncing.
- Verifiable certificates with QR codes generated accurately.

## SLIDE 18: Screenshots / Demonstration
*(Placeholder for UI screenshots: Dashboard, Schedule View, Sync Center, Certificate Designer)*

## SLIDE 19: Challenges and Limitations
- **Challenges**: Managing complex PDF coordinates and offline local storage limits.
- **Limitations**: Deterministic chatbot lacks NLP.

## SLIDE 20: Future Work and Recommendations
- Implementation of Single Sign-On (SSO).
- Upgrading offline sync to Service Worker Background Sync API.
- Advanced predictive analytics.

## SLIDE 21: Conclusion
- NexusCore successfully digitizes and streamlines academic events, reducing manual overhead and ensuring data integrity.

## SLIDE 22: Thank You / Questions
- Open for questions.
