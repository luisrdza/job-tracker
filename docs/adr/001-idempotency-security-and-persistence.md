# ADR-001: Idempotency, Security, and Persistence

Status: Accepted  
Date: 2026-11-12  
General Context: Designing the baseline architecture of a REST API ensuring data consistency against network retries, secure access control, and decoupling the persistence layer to facilitate long-term maintainability.


### Contract-First Approach with OpenAPI 3.1 and Black-Box Suite
- Context: When developing endpoints without a single source of truth, discrepancies between documentation and implementation often arise, while coupling tests to the internal tools of the framework.
- Decision: The API contract will be defined first using OpenAPI 3.1 as the single source of truth, backed by a black-box test suite to validate HTTP inputs and outputs independently of the framework.
- Discarded Alternatives:
    - Code-First: Discarded because accidental code modifications could alter the public API without prior review.
- Consequences and Trade-offs:
    - Positive: Guarantees strict adherence to the contract, allowing future changes to the backend language while preserving the exact same validations.
    - Negative (trade-offs): Increases initial development time as writing and maintaining the OpenAPI specification before coding is required. Additionally, black-box tests are slower than unit tests because they require running the HTTP environment and database.

### Idempotency via UNIQUE Constraint and 409 Conflict Response
- Context: During network failures, a client may re-send the same creation request, causing duplicate records if the operation is not idempotent.
- Decision: Require the `Idempotency-Key` header and store it in an `idempotency` column with a `UNIQUE NOT NULL` constraint in the database. Thus, if the key already exists, the API rejects the retry by returning a `409 Conflict` error.
- Discarded Alternatives: 
    - Redis 201 Created: Discarded for this phase because it introduces an extra infrastructure dependency (Redis instance), requires managing expiration policies (TTL), and mandates serializing and storing the entire body of each successful response.
- Consequences and Trade-offs:
    - Positive: Zero additional external dependencies, lower operational complexity, and transactional guarantees backed by the database engine.
    - Negative (trade-offs): The client receives a `409 Conflict` error instead of the original payload, requiring a subsequent `GET` request if it needs to retrieve the saved data.
- Migration Criteria: A Redis-based migration will be planned when traffic volume increases or clients require receiving `201 Created` with the original cached payload instead of a `409`.

### Bearer Authentication with Constant-Time Comparison and Argon2id Readiness
- Context: Endpoint access control requires distinct visibility and permission levels, while leaving the foundation prepared for future user password authentication.
- Decision: Implemented Bearer token authentication validated via `hash_equals()` for constant-time string comparison. Similarly, configured password hashing using Argon2id via PHP's `password_hash()` for future user registration.
- Discarded Alternatives:
    - String comparison (`===`): Discarded because it stops evaluation on the first non-matching byte, leaking timing information.
    - Bcrypt for passwords: Bcrypt truncates passwords at a maximum of 72 bytes and provides lower resistance against attacks using specialized hardware.
- Consequences and Trade-offs:
    - Positive: `hash_equals()` mitigates content-based timing attacks by evaluating all bytes in constant time.
    - Negative (trade-offs): Argon2id consumes more RAM and CPU per validation compared to bcrypt, which could become a bottleneck under high concurrent login spikes.

### Decoupled Persistence (Repository Pattern) and Atomic Integrity via UNIQUE Constraint
- Context: Embedding SQL queries inside controllers complicates testing and maintenance. Additionally, verifying whether an `Idempotency-Key` exists via a `SELECT` prior to an `INSERT` introduces a race condition under concurrent requests.
- Decision: Implemented the Repository pattern over PDO/PostgreSQL to isolate data access, delegating concurrency control to the database's `UNIQUE` constraint.
- Discarded Alternatives:
    - Pessimistic Locking (`SELECT ... FOR UPDATE`): Discarded because it locks rows throughout the entire transaction, degrading throughput under high concurrency. Furthermore, `FOR UPDATE` cannot prevent the insertion of a key that does not yet exist in the table without table-level locking.
- Consequences and Trade-offs:
    - Positive: Cleaner, decoupled, and testable code. Eliminates the redundant roundtrip of a preliminary `SELECT` and avoids unnecessary database locks.
    - Negative (trade-offs): Boilerplate interfaces and concrete implementations due to repository decoupling. Furthermore, relying on database exceptions as control flow incurs PHP stack trace instantiation overhead and generates dead tuples in PostgreSQL.