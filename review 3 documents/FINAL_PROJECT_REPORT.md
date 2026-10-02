# FINAL PROJECT REPORT

## TITLE PAGE
**Project Title**: Academic Symposium & Event Management System (NexusCore)
**Course**: [Insert Course]
**Date**: [Insert Date]

## ABSTRACT
This project, NexusCore, addresses the complex challenges of managing academic symposiums and events by providing a comprehensive, centralized platform. The system handles authentication, symposium management, master events, team registration, scheduling, resource allocation, attendance tracking with offline synchronization capabilities, evaluation and result processing, automated certificate generation, QR-based certificate verification, and feedback collection. It was developed using PHP MVC architecture, MySQL, Bootstrap, and integrates several third-party libraries for PDF and QR code generation.

## CHAPTER 1 — INTRODUCTION
### 1.1 Background
Managing large-scale academic symposiums involves tracking registrations, coordinating schedules, allocating venues, marking attendance, and distributing certificates. Manual methods are error-prone and time-consuming.
### 1.2 Motivation
To streamline operations and reduce administrative overhead through a unified platform.
### 1.3 Problem Statement
Existing processes for managing events rely on disjointed tools and physical paperwork, leading to inefficiencies, scheduling conflicts, and delays in result publication and certificate issuance.
### 1.4 Objectives
- To develop a centralized platform for academic symposium management.
- To automate event scheduling, conflict detection, and resource allocation.
- To provide offline-capable attendance tracking.
- To streamline evaluation, result processing, and certificate generation with QR verification.
### 1.5 Scope
The system covers the entire event lifecycle from symposium creation to certificate distribution and feedback collection.
### 1.6 Target Users
Administrators, Coordinators, Faculty In-Charge (FIC), Judges, and Students.
### 1.7 Methodology
Agile development using a custom PHP MVC framework.
### 1.8 Project Organization
The project is structured into controllers, models, views, and services.

## CHAPTER 2 — EXISTING SYSTEM AND PROBLEM ANALYSIS
### 2.1 Existing Process
Relying on physical forms, spreadsheets, and manual certificate design.
### 2.2 Existing System
Disparate systems for registration, feedback, and attendance. No offline synchronization.
### 2.3 Limitations
Lack of real-time synchronization, high risk of double-booking venues, delays in evaluations.
### 2.4 Problems Identified
Manual verification of certificates, cumbersome offline attendance tracking, fragmented workflows.
### 2.5 Need for Proposed System
A cohesive platform tailored specifically for academic environments.

## CHAPTER 3 — PROPOSED SYSTEM
### 3.1 Proposed Solution
NexusCore: A centralized PHP MVC web application.
### 3.2 Objectives
Centralized management, offline sync, automated conflict resolution, automated certificate distribution.
### 3.3 Functional Requirements
Authentication, role management, event configuration, team registration, scheduling, attendance marking, grading/evaluation, certificate generation, QR verification, feedback collection.
### 3.4 Non-Functional Requirements
Security (role-based access), scalability (handling concurrent users), usability (responsive design), reliability (offline synchronization queue).
### 3.5 User Roles
- Admin: Full system access.
- Coordinator: Manages symposium events and registrations.
- Faculty In-Charge (FIC): Approves schedules and oversees specific events.
- Judge: Evaluates teams and submits scores.
- Student: Registers for events, submits feedback, downloads certificates.
### 3.6 System Modules
1. Authentication & Access Control
2. Symposium & Event Management
3. Registration & Team Management
4. Scheduling & Resource Allocation
5. Attendance & Offline Synchronization
6. Evaluation & Results
7. Certificate Management & Verification
8. Feedback & Notifications
9. Student Portal
10. Chatbot
### 3.7 System Workflow
Admin configures symposiums -> Students register -> Coordinator schedules and allocates resources -> Event executes with attendance tracking -> Judges evaluate -> System generates results and certificates -> Students access portal for feedback and certificates.

## CHAPTER 4 — SYSTEM DESIGN
### 4.1 System Architecture
Custom PHP MVC Architecture.
### 4.2 Main Module Diagram
*(Refer to separate diagrams document)*
### 4.3 Submodule Diagram
*(Refer to separate diagrams document)*
### 4.4 System Flowchart
*(Refer to separate diagrams document)*
### 4.5 Database Design
Relational database with 39 tables, using InnoDB engine with strict foreign key constraints.
### 4.6 ER Diagram
*(Refer to separate diagrams document)*

## CHAPTER 5 — TECHNOLOGIES AND TOOLS
### 5.1 Programming Languages
PHP 8.2, JavaScript, HTML5, CSS3.
### 5.2 Framework / Architecture
Custom PHP MVC Framework.
### 5.3 Database
MySQL (using PDO).
### 5.4 Libraries
Bootstrap 5.3.8, Chart.js 4.5.1, PHPMailer, Dompdf, FPDF/FPDI, chillerlan/php-qrcode.
### 5.5 Development Tools
Composer, Docker (Docker Compose).
### 5.6 Deployment Environment
Apache Web Server, PHP 8.2 Environment.

## CHAPTER 6 — FINAL IMPLEMENTATION
### 6.1 Authentication
Role-based login with separate student and admin gateways.
### 6.2 User / Student Management
Management of user roles, departments, and student records.
### 6.3 Symposium Management
Lifecycle management of academic symposiums.
### 6.4 Event Management
Master event configuration and specific symposium events setup.
### 6.5 Registration and Team Management
Individual and team registrations with manager designation.
### 6.6 Scheduling
Automated scheduling with visual timeline.
### 6.7 Resource Allocation
Venue mapping and conflict checking.
### 6.8 Attendance
Session-based attendance tracking (Present, Absent, Late).
### 6.9 Offline Synchronization
Local storage-based offline marking with a sync queue and synchronization center (`SyncCenterController`).
### 6.10 Evaluation and Results
Scoring interface for judges, result processing including tie resolution and z-score snapshots.
### 6.11 Certificate Management
Template designer, dynamic field mapping, and batch generation.
### 6.12 Certificate Verification
QR-based public validation with schema versioning.
### 6.13 Feedback
Dynamic feedback collection with post-event locking.
### 6.14 Notifications
Email queue processing and system alerts.
### 6.15 Reports
Result viewers and comprehensive analytics.
### 6.16 Student Portal
Dashboard for students to track registrations, view results, and download certificates.
### 6.17 Chatbot
Deterministic chatbot interface for user assistance.
### 6.18 Security and Validation
Input sanitization, PDO prepared statements, route-level authorization middleware.

## CHAPTER 7 — SECOND REVIEW FEEDBACK AND IMPROVEMENTS
*Note: Specific second review feedback document was not found in the available project files. However, based on project history and schema migrations, significant refactoring was done to certificate types, feedback mechanisms, and team management after initial reviews.*

## CHAPTER 8 — TESTING AND VALIDATION
Test cases exist for:
- Certificate Filesystem Atomicity
- Certificate Rank Services
- Certificate Uniqueness
- Certificate Verification Schema Versioning
- PHPUnit configuration implies unit and integration testing.

## CHAPTER 9 — FINAL RESULTS
The system successfully coordinates the entire event lifecycle. Attendance offline sync queue processes data reliably when connectivity is restored. PDF certificates are accurately generated. (Quantitative result not verified from the available evidence).

## CHAPTER 10 — CHALLENGES AND LIMITATIONS
### Challenges
Implementing robust offline synchronization across different devices.
Complex template mapping for PDF certificates using FPDI.
### Current Limitations
Offline synchronization depends heavily on browser local storage reliability.
The chatbot is deterministic, lacking NLP capabilities.

## CHAPTER 11 — FINAL CONCLUSION
The NexusCore project successfully delivers a comprehensive Academic Symposium Management System. It addresses the core problems of disjointed academic event management by providing a centralized platform for registration, scheduling, offline-capable attendance, evaluation, and verifiable certificate distribution.

## CHAPTER 12 — FUTURE WORK AND RECOMMENDATIONS
- **Scalability**: Migrate offline queue to a more robust Service Worker background sync.
- **Integration**: Integrate Single Sign-On (SSO) with existing university identity providers.
- **Reporting**: Advanced predictive analytics on event participation trends.
