# FINAL VIVA QUESTIONS AND ANSWERS

**1. What is the architecture of your system?**
*Answer*: The system is built using a custom PHP Model-View-Controller (MVC) architecture. Requests are handled by a central router, processed by Controllers, data is managed by Models using PDO, and rendered via templates. Business logic is abstracted into Services.

**2. How did you implement offline attendance synchronization?**
*Answer*: When a coordinator opens an attendance session, the initial data is loaded. If the connection drops, attendance marks (Present/Absent) are saved into the browser's local storage (or indexedDB). The `SyncCenterController` provides an interface to view queued records and batch-sync them to the server via AJAX when connectivity is restored.

**3. How are scheduling conflicts detected?**
*Answer*: The `SchedulingController` queries the database for existing schedules based on the selected venue and time range. It checks for overlapping start and end times for the specific venue, and also ensures that faculty members are not double-booked.

**4. How does the certificate verification system work?**
*Answer*: When a certificate is generated, a unique QR code is embedded in it. Scanning the QR code links to a public verification route (`CertificateVerificationController`) on our system, which queries the database using the unique certificate hash/ID to confirm its authenticity. We also implemented schema versioning to support different certificate formats over time.

**5. Why did you use FPDI instead of just DOMPDF for certificates?**
*Answer*: DOMPDF is great for generating PDFs from HTML, but our system allows admins to upload existing pre-designed certificate templates (PDFs). FPDI allows us to import these existing PDF pages as templates and write dynamic text (names, ranks) at exact coordinates over them.

**6. How is security handled in your application?**
*Answer*: We use PDO prepared statements for all database queries to prevent SQL injection. Passwords are securely hashed. We also implemented a custom routing system with Middleware that checks the user's role before granting access to sensitive routes.

**7. How are ties resolved in evaluation results?**
*Answer*: As per our schema migrations (e.g., `2026_08_23_tie_resolution.sql`), the result processing engine allows for tie resolution mechanisms, including recording z-score snapshots to normalize judge scoring differences.

**8. What happens if a student registers for two events occurring at the same time?**
*Answer*: The system cross-references the student's existing approved applications against the schedule of the new event and flags a conflict, preventing the registration or warning the coordinator.
