# FINAL PROJECT SPEAKER NOTES

**Slide 1: Title**
"Good morning everyone. I am here to present NexusCore, our complete Academic Symposium and Event Management System built on a custom PHP MVC framework."

**Slide 2: Overview**
"NexusCore is designed to handle the entire lifecycle of college events, from the initial setup by administrators to final certificate generation and student feedback."

**Slide 3: Problem Statement**
"Currently, colleges rely on a mix of Google Forms, Excel sheets, and physical paperwork. This causes scheduling conflicts, lost attendance records, and huge delays in printing certificates."

**Slide 4: Objectives**
"Our main goals were to centralize this process, automate conflict checking when booking venues, provide an offline way to mark attendance in dead zones, and instantly generate verifiable certificates."

**Slide 5: Existing System**
"The existing process is fragmented. There is no single source of truth, meaning students have to check multiple notice boards, and coordinators spend hours cross-referencing spreadsheets."

**Slide 6: Proposed System**
"Our proposed system is a unified platform. Everyone logs into the same system but sees different dashboards based on their role—Admin, Student, Faculty, or Judge."

**Slide 7: Modules**
"Our core modules include Authentication, Event Management, Registration, Scheduling, Attendance with Offline Sync, Evaluation, Certificates, and Feedback. We did NOT include face recognition or AI matching, as we focused on reliable, core academic workflows."

**Slide 8: Flowchart**
"The workflow is simple: Admins create events -> Students register -> Coordinators schedule them -> Attendance is taken during the event -> Judges submit scores -> System publishes results and certificates."

**Slide 9: Architecture**
"We built a custom PHP MVC architecture. This keeps our routing clean, separates business logic into Services, and uses Middleware for secure role checking."

**Slide 10: Tech Stack**
"We used PHP 8.2 and MySQL. For the frontend, we used Bootstrap 5. For generating PDFs and QR codes, we integrated libraries like FPDI, Dompdf, and chillerlan's php-qrcode."

**Slide 11: Database**
"Our database has 39 tables, properly normalized. We heavily utilized foreign key constraints to ensure data integrity, especially when deleting an event cascading down to its attendance records."

**Slide 12: Modules Deep Dive**
"Key features include the Master Event Configuration which standardizes events, and the Offline Sync Center which is crucial for venues with poor internet."

**Slide 13: Implementation Details**
"For offline sync, we cache the attendance lists. Coordinators can mark attendance offline, and the data is stored in the browser. When internet is back, the Sync Center pushes it to the server securely."

**Slide 14: Security**
"We strictly use PDO prepared statements everywhere. Our routing engine checks middleware before allowing access to any controller method."

**Slide 15: Iterations**
"While specific second-review documents weren't available, we significantly iterated on the certificate system to support schema versioning, and we refactored the team management system for better scalability."

**Slide 16: Testing**
"We wrote automated tests using PHPUnit, specifically targeting critical functions like certificate uniqueness and ensuring no two certificates overlap."

**Slide 17: Results**
"The result is a robust system that handles the complete lifecycle without external tools."

**Slide 18: Demo**
*(Briefly show or describe the Certificate Designer and Sync Center).*

**Slide 19: Challenges**
"One major challenge was mapping dynamic text onto PDF templates accurately using FPDI. Another was ensuring the offline sync queue didn't corrupt if the browser crashed."

**Slide 20: Future Work**
"In the future, we recommend adding SSO for university emails and replacing local storage sync with modern Service Worker Background Sync."

**Slide 21: Conclusion**
"NexusCore delivers exactly what was promised: a cohesive, reliable platform for academic event management."
