# Ride Booking System – Architecture & Technical Decisions

## 1. Why did you choose your technology stack and architecture?

I chose **Laravel 13, PHP 8.3, PostgreSQL 18, and Next.js with TypeScript** because they provide a practical foundation for building a maintainable and scalable ride-booking application.

- **Laravel 13:** Provides structured MVC architecture, API development, middleware, authentication through Sanctum, database transactions, and a clean service layer for business logic.
- **PostgreSQL:** Provides reliable relational data management, transactions, foreign-key constraints, row-level locking, and partial unique indexes. These are particularly important for preventing conflicting ride assignments.
- **Next.js and TypeScript:** Provide a component-based frontend, reusable UI components, type safety, and a clear separation between frontend and backend.
- **REST API architecture:** Keeps the frontend independent of backend implementation and allows mobile applications or other clients to use the same APIs in the future.

I used a modular monolithic backend with controllers, services, models, and middleware rather than introducing microservices prematurely.

## 2. How do you prevent two drivers from accepting the same ride?

I use multiple layers of protection:

1. Database transactions ensure that ride acceptance operations execute atomically.
2. `lockForUpdate()` locks the ride record during the acceptance transaction.
3. The service checks that the ride is still in `searching` status.
4. A partial unique index on `driver_assignments(ride_id)` ensures that only one active assignment can exist per ride.
5. Another partial unique index ensures that a driver cannot have multiple active assignments.

If two drivers attempt to accept the same ride, only one can successfully create the active assignment. The other receives a conflict response.

## 3. How do you handle retries and duplicate requests?

The system uses an idempotency mechanism for ride creation to prevent duplicate rides when clients retry requests.

The intended approach is:

- The client sends an idempotency key with a ride-creation request.
- Repeated requests using the same key should not create additional rides.
- Database transactions protect operations that modify ride and assignment states.
- Ride acceptance checks the current ride status before making changes.
- GET requests can generally be retried safely, while state-changing requests should only be retried with appropriate idempotency protection.

This prevents network retries from unintentionally creating duplicate business operations.

## 4. How does the frontend respond when an API request fails?

The frontend should handle API failures through a centralized API communication layer.

The expected behavior includes:

- Displaying meaningful error messages instead of exposing technical server errors.
- Showing validation errors near the relevant form fields.
- Displaying loading indicators while requests are processing.
- Handling expired or invalid authentication tokens by redirecting users to login.
- Allowing users to retry recoverable failures.
- Preventing repeated submissions while a request is already processing.

For example, if a driver attempts to accept a ride that another driver has already accepted, the frontend should display a message explaining that the ride is no longer available and refresh the available rides list.

## 5. How would you scale the system to multiple cities and thousands of concurrent users?

I would scale incrementally:

- Introduce city and service-area information into the data model.
- Filter rides and drivers by their operating city and service area.
- Add appropriate database indexes for frequently queried fields.
- Use PostgreSQL connection pooling and optimize expensive queries.
- Deploy multiple stateless Laravel application instances behind a load balancer.
- Use Redis for caching, shared application state where appropriate, and queue management.
- Move long-running tasks, such as notifications, into background workers.
- Introduce monitoring and load testing to identify bottlenecks before increasing infrastructure capacity.

For location-based driver matching at larger scale, I would also evaluate PostgreSQL PostGIS.

## 6. When would you introduce Redis, a message broker, or separate services?

**Redis:** I would introduce Redis when caching, distributed rate limiting, shared cache storage, or queue infrastructure becomes necessary.

**Message broker:** I would introduce a message broker when asynchronous communication becomes important, especially for ride notifications, SMS, emails, payment events, and other background processing.

**Separate services:** I would consider extracting services when independent scaling, deployment, ownership, or operational requirements justify the additional complexity.

For example, a dedicated notification service could be separated if notification volume grows significantly.

I would not introduce these technologies without a measurable requirement. A modular monolith is simpler to maintain at the current project scale.

## 7. How would you deploy, monitor, and roll back a faulty release?

I would use a CI/CD pipeline with separate development, staging, and production environments.

**Deployment:**
- Build and test the application automatically.
- Deploy the backend and frontend to their respective environments.
- Store environment-specific configuration and secrets securely.
- Run database migrations using a controlled deployment process.
- Use a reverse proxy and HTTPS.
- Keep application logs and monitoring enabled.

**Monitoring:**
- Monitor API response times, error rates, server resources, database performance, and queue failures.
- Use centralized logging and an error-tracking service.
- Configure alerts for critical failures.

**Rollback:**
- Keep previous application release artifacts available.
- Roll back the application to the last stable release if a deployment fails.
- Use backward-compatible database migrations so an application rollback does not break the database.
- Take regular database backups and test restoration procedures.

## 8. What are the three most important improvements before production?

**1. Real-time driver and rider communication**

Introduce WebSockets or an event-driven notification system so riders and drivers receive immediate updates when rides are accepted, cancelled, started, or completed.

**2. Security and reliability improvements**

Add comprehensive authorization and security testing, API rate limiting, stronger token/session management, improved audit logging, and production-grade error handling. Also conduct concurrency and load testing.

**3. Production infrastructure and observability**

Introduce automated CI/CD, Redis and background workers where needed, centralized monitoring, database backups, disaster recovery procedures, and scalable deployment infrastructure.

---

## Conclusion

The current application establishes the core ride-booking workflow with role-based authentication, fare estimation, driver matching, ride lifecycle management, transactional operations, and database-level assignment constraints.

The architecture intentionally prioritizes correctness, maintainability, and simplicity. Additional infrastructure and distributed components can be introduced as actual traffic and business requirements grow.