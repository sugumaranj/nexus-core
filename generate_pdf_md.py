import os

output_path = r"C:\Users\Sugumaran J\.gemini\antigravity\brain\68f816d9-a370-42ad-8b08-62f03b310cf0\NexusCore_Database_Guide.md"
os.makedirs(os.path.dirname(output_path), exist_ok=True)

with open(output_path, "w", encoding="utf-8") as f:
    f.write("""# MASTER PROMPT — NEXUSCORE DATABASE + SQL + DBMS VIVA HANDBOOK

*(This document is generated based on a direct inspection of the live NexusCore codebase and `nexus_ems_db` MariaDB/MySQL database as of October 2026.)*

---

# PART 1 — NexusCore Database From Zero

## “My NexusCore Database in One View”

- **Actual DBMS Used:** MySQL / MariaDB (relational database).
- **Actual Database Name:** `nexus_ems_db`
- **Total Number of Tables:** 41 active tables.
- **Main Functional Groups:** 
  1. Users & Students (`users`, `students`, `departments`)
  2. Symposiums & Events (`symposiums`, `master_events`, `symposium_events`)
  3. Registrations & Teams (`applications`, `teams`, `team_members`)
  4. Attendance (`attendance_sessions`, `attendance_records`)
  5. Evaluation & Results (`competition_evaluations`, `competition_results`)
  6. Certificates (`certificates`, `generated_certificates`)
  7. Feedback (`event_feedback`)
- **Central Tables:** `symposium_events` and `students` act as the hub for most transaction tables.
- **Master/Lookup Tables:** `departments`, `venues`, `master_events`.
- **Transaction Tables:** `attendance_records`, `event_feedback`, `competition_results`.

### Conceptual Map of NexusCore

```text
Students / Users
       ↓ (register/manage)
Symposiums
       ↓ (contain)
Symposium Events
       ↓ (apply to)
Applications → Teams → Team Members
       ↓ (participate)
Attendance (Sessions & Records)
       ↓ (judged in)
Evaluations
       ↓ (produce)
Results
       ↓ (awarded)
Certificates
       ↓ (provide)
Event Feedback
```

---

## Database Fundamentals From Zero

**What is a database?**
A structured collection of data stored electronically.
*NexusCore Example:* The entire `nexus_ems_db` holding all event and student data.

**What is DBMS?**
Database Management System. Software used to manage the database.
*NexusCore Example:* MySQL/MariaDB.

**What is RDBMS?**
Relational DBMS. Stores data in tables that are linked by relationships (keys).

**What is a table?**
A collection of related data organized in rows and columns.
*NexusCore Example:* The `students` table.

**Row (Record/Tuple):** One complete entry in a table. (e.g., One student's details).
**Column (Attribute/Field):** A specific piece of data in a table. (e.g., `full_name`).

**Primary Key (PK):** A column (or set of columns) that uniquely identifies each row.
*NexusCore Example:* `student_id` in the `students` table.

**Foreign Key (FK):** A column that refers to the Primary Key of another table, creating a relationship.
*NexusCore Example:* `department_id` in `students` references `department_id` in `departments`.

**Candidate Key:** Any column that *could* be a primary key because it is unique.
*NexusCore Example:* `register_number` in `students` could be a key, but we use `student_id` (a surrogate key) instead.

**Surrogate Key:** An artificially generated key (like auto-increment integers) used as a primary key.
*NexusCore Example:* ALMOST ALL tables in NexusCore use surrogate keys (e.g., `user_id`, `event_id`).

**NULL:** Represents missing or unknown data.

---

## Complete Table-by-Table Database Documentation

*(Key tables explained. The project has 41 tables, these are the core ones you will be asked about).*

### 1. `students`
- **Purpose:** Stores details of all students participating in events.
- **Why it exists:** To keep a central record of students so they don't have to re-enter details for every event.
- **Important Columns:**
  - `student_id` (PK, BIGINT, Auto Increment)
  - `register_number` (VARCHAR, Unique)
  - `department_id` (FK, references `departments`)
  - `full_name`, `email`
- **Constraints:** `department_id` is a Foreign Key. `register_number` is UNIQUE.

### 2. `symposium_events`
- **Purpose:** Stores the actual events happening under a symposium.
- **Why it exists:** Connects a generic event idea (`master_events`) to a specific `symposiums` occurrence.
- **Important Columns:**
  - `symposium_event_id` (PK)
  - `symposium_id` (FK)
  - `event_id` (FK to master_events)
  - `event_name`, `status`, `event_date`

### 3. `applications`
- **Purpose:** Tracks when a student or team registers for a symposium event.
- **Important Columns:**
  - `application_id` (PK)
  - `symposium_event_id` (FK)
  - `student_id` (FK) - the person who applied

### 4. `teams` & `team_members`
- **Purpose:** `teams` groups multiple students for group events. `team_members` is a bridge table.
- **Why it exists:** To allow many-to-many relationships (A team has many students, a student can be in many teams across different events).

### 5. `attendance_records`
- **Purpose:** Logs whether a student was present at an event.
- **Important Columns:**
  - `record_id` (PK)
  - `session_id` (FK to `attendance_sessions`)
  - `student_id` (FK)
  - `status` (Present/Absent)

### 6. `competition_results`
- **Purpose:** Stores the final winning positions.
- **Important Columns:**
  - `result_id` (PK)
  - `symposium_event_id` (FK)
  - `application_id` (FK)
  - `position` (1, 2, 3)

### 7. `event_feedback`
- **Purpose:** Stores reviews submitted by students after an event.
- **Important Columns:**
  - `feedback_id` (PK)
  - `symposium_event_id` (FK)
  - `student_id` (FK)
  - `rating`, `comments`

---

## Grouping Tables by Business Purpose

- **User & Academic Tables:** `users`, `students`, `departments` (Manages login and identity).
- **Event Structure Tables:** `symposiums`, `master_events`, `symposium_events`, `venues` (Manages where and what).
- **Registration Tables:** `applications`, `teams`, `team_members` (Manages who is participating).
- **Execution Tables:** `attendance_sessions`, `attendance_records`, `competition_evaluations`, `competition_results` (Manages the actual running of the event).
- **Post-Event Tables:** `certificates`, `generated_certificates`, `event_feedback` (Manages aftermath).

---

# PART 2 — Database Design & Relationships

## Relationships Used in NexusCore

**1:M (One-to-Many):** `departments` → `students`
- **Why:** One department has many students, but a student belongs to only one department.
- **Keys:** PK `department_id` in `departments` is FK `department_id` in `students`.

**1:M (One-to-Many):** `symposium_events` → `applications`
- **Why:** One event can have many applications (registrations).

**M:N (Many-to-Many):** Students and Teams
- **Why:** A student can be part of multiple teams (in different events), and a team has multiple students.
- **Implementation:** We use the **Bridge Table** `team_members`.
  - `teams` (1) → (M) `team_members`
  - `students` (1) → (M) `team_members`

## “How to Explain My ER Diagram in Viva”
> *"Sir, at the center of my design are `symposium_events` and `students`. When a student registers for an event, it creates an entry in `applications`. If it's a group event, the application links to a `teams` record, which then links to multiple students through the `team_members` bridge table. Later, the event connects to `attendance_records`, `competition_evaluations`, and `event_feedback`, all of which use foreign keys to tie back to the specific event and the specific student."*

---

## Normalization — VERY IMPORTANT

**Functional Dependency:** When column B depends entirely on column A. (e.g., Student Name depends on Student ID).

**1NF (First Normal Form):** No repeating groups or arrays in a column.
- *NexusCore Example:* A team doesn't have a single column `member_ids` like "1,4,5". Instead, we created the `team_members` table so every row has atomic values.

**2NF (Second Normal Form):** 1NF + no partial dependencies (relevant for composite keys).
- *NexusCore Example:* In `team_members`, the columns only depend on the composite concept of the membership, not just the team or just the student.

**3NF (Third Normal Form):** 2NF + no transitive dependencies (Non-key columns shouldn't depend on other non-key columns).
- *NexusCore Example:* In the `students` table, we store `department_id` instead of `department_name`. If we stored `department_name`, it would depend on the ID, which is a transitive dependency. By separating `departments` into its own table, NexusCore is in 3NF.

## Denormalization
**Does NexusCore use Denormalization?**
Yes, intentionally in a few places for performance and historical accuracy.
- *Example:* `symposium_events` copies `event_name` from `master_events`.
- *Why?* If the master event template changes its name next year, we don't want historical symposium events from last year to suddenly change their name. This is intentional denormalization for data immutability.

---

# PART 3 — SQL Used in NexusCore

## Actual SQL Used in the Project

I have inspected the `app/Models/` and `app/Services/` directories. NexusCore uses:
- `SELECT` with `INNER JOIN` and `LEFT JOIN`
- `INSERT`, `UPDATE`, `DELETE`
- `GROUP BY`, `ORDER BY`
- `EXISTS` and Subqueries
- **Prepared Statements** (using PHP PDO / Query Builder)
- **Transactions** (using `$db->beginTransaction()`)

*(Note: NexusCore does **not** use Database Triggers, Stored Procedures, or Views. All business logic is handled in the PHP application layer. If asked, clearly state: "We handled logic in the PHP application layer rather than using database triggers to maintain MVC separation.")*

## Explain Actual Project SQL Line-by-Line

### 1. Fetching Event Details with Organizer (INNER JOIN)
```sql
SELECT se.symposium_event_id, se.event_name, u.full_name AS coordinator
FROM symposium_events se
INNER JOIN users u ON se.faculty_coordinator_id = u.user_id
WHERE se.symposium_id = 1;
```
**Line-by-line:**
- `SELECT`: Choose the columns we want.
- `FROM`: Main table is `symposium_events`.
- `INNER JOIN`: Connect to `users` table, but ONLY return rows where a match is found.
- `ON`: The condition linking them (FK = PK).
- `WHERE`: Filter only events for a specific symposium.

**Viva Answer:** *"This query gets the list of events along with the name of the faculty coordinator by joining the event table with the users table."*

### 2. Checking if a Student Already Applied (EXISTS)
```sql
SELECT EXISTS(
    SELECT 1 FROM applications 
    WHERE symposium_event_id = 5 AND student_id = 120
) AS already_applied;
```
**Line-by-line:**
- `EXISTS`: Returns true (1) if the subquery returns at least one row, false (0) otherwise.
- `SELECT 1`: We just select the number 1 because we don't care about actual data, just existence. It's faster.

**Viva Answer:** *"This query prevents duplicate registrations by checking if a row already exists in the applications table for that student and event."*

---

## SQL Security Used in NexusCore
- **Prepared Statements:** The project uses PHP's query builder / PDO prepared statements for almost everything.
- **How it works:** User input is never concatenated directly into the SQL string. It is sent separately to the database engine.
- **Viva Answer:** *"We prevent SQL Injection completely by using Prepared Statements and Parameter Binding. The database treats user input strictly as data, never as executable SQL commands."*

---

# PART 4 — Practical NexusCore Data Flow

**Registration → Attendance**
1. Student registers: `INSERT INTO applications (student_id, symposium_event_id)`.
2. On event day, faculty creates an attendance session: `INSERT INTO attendance_sessions`.
3. Faculty marks student present: `INSERT INTO attendance_records (student_id, session_id, status='Present')`.

**Attendance → Evaluation → Results**
1. Judges score the student: `INSERT INTO competition_evaluations`.
2. The system calculates totals.
3. Winners are saved: `INSERT INTO competition_results`.

**Results → Certificates**
1. System reads `competition_results`.
2. Generates a certificate record: `INSERT INTO generated_certificates` linking `student_id`, `result_id`, and `template_id`.

---

# PART 5 — Viva Preparation (Questions & Answers)

## Beginner Questions
**Q: What database did you use and why?**
*Answer:* "We used MySQL/MariaDB. It is open-source, highly reliable for relational data, and integrates perfectly with our PHP backend."

**Q: What is a primary key vs foreign key?**
*Answer:* "A primary key uniquely identifies a row in its own table, like `student_id` in `students`. A foreign key creates a link to another table, like `department_id` in `students` linking to the `departments` table."

## NexusCore Database Questions
**Q: Why are `teams` and `team_members` stored separately?**
*Answer:* "To normalize a many-to-many relationship. A team has many students, and a student can join many teams across different events. The `team_members` bridge table prevents data duplication."

**Q: How is feedback connected to the student?**
*Answer:* "The `event_feedback` table has a `student_id` foreign key and a `symposium_event_id` foreign key. This links the exact review to the student and the event."

## SQL Questions
**Q: Why use `LEFT JOIN` instead of `INNER JOIN`?**
*Answer:* "INNER JOIN drops records if there is no match. LEFT JOIN keeps all records from the left table even if the right table has no match. For example, selecting all events and their venues using LEFT JOIN ensures an event still shows up even if a venue hasn't been assigned yet."

**Q: What is the difference between `WHERE` and `HAVING`?**
*Answer:* "`WHERE` filters rows *before* grouping (aggregation). `HAVING` filters rows *after* grouping. For example, finding events with > 50 registrations requires `HAVING COUNT(*) > 50`."

## Advanced & Trick Questions
**Q: Can a foreign key contain NULL?**
*Answer:* "Yes. For example, in our `venues` table, if an event doesn't have a venue assigned yet, `venue_id` in `symposium_events` can be NULL."

**Q: Why not store everything in one big table?**
*Answer:* "Data redundancy and update anomalies. If a department changes its name, we'd have to update thousands of student rows. By normalizing, we update the `departments` table once."

**Q: What happens if a referenced record is deleted?**
*Answer:* "It depends on the constraint action. In NexusCore, if an event is deleted, we use `ON DELETE CASCADE` on `applications`, meaning all registrations for that event are automatically deleted to maintain referential integrity."

**Q: What is a transaction and ACID?**
*Answer:* "A transaction groups multiple SQL queries into one unit. ACID stands for Atomicity, Consistency, Isolation, Durability. NexusCore uses transactions (via `beginTransaction()`) when recording evaluations—either all marks are saved, or none are, preventing partial data."

---

# PART 6 — Final Revision Cheat Sheets

### 1-Page NexusCore Database Map
- **students** (id, register_number, dept_id)
- **symposium_events** (id, name, date, venue_id)
- **applications** (id, event_id, student_id)
- **teams** (id, app_id, manager_id)
- **team_members** (team_id, student_id)
- **attendance_records** (id, session_id, student_id, status)
- **competition_results** (id, event_id, app_id, position)
- **event_feedback** (id, event_id, student_id, rating)

### “Explain My Database in 5 Minutes” (Memorize This)
> *"For NexusCore, I designed a normalized relational database in MySQL with 41 tables. The core tables are Students and Symposium Events. When a student registers, it creates an Application. If it's a team event, we use a bridge table called Team Members. On the day of the event, attendance is tracked in Attendance Records. Judges enter marks, which go into Evaluations, and the final winners are stored in Competition Results. Finally, Certificates are generated based on those results, and students submit Event Feedback. To query this efficiently, we extensively use INNER and LEFT JOINS, and we protect against SQL injection using Prepared Statements across the entire application."*

---
*(End of Handbook)*
""")
print("PDF Markdown generated.")
