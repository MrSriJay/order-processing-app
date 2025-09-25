# Order Processing App

A Laravel-based application for importing, processing, and managing orders from CSV files. It supports stock management, notifications, refunds, KPIs, and leaderboards. Redis is used for caching and queues, and Laravel Horizon for queue monitoring.

---

## Task

### Task 1: Order Import and Processing
- Import orders from a CSV using `php artisan orders:import orders.csv`.
- Asynchronous order processing:
  - Reserve stock
  - Simulate payment (50% failure rate for testing)
  - Finalize or rollback
  - Update KPIs: revenue, order count, average order value
  - Maintain customer leaderboard in Redis
- Laravel Horizon monitors queues; Supervisor ensures persistent queue workers.

### Task 2: Notifications
- Sends queued notifications (logged to `storage/logs/laravel.log`) for successful or failed orders.
- Stores notification history in the `notifications` table.

### Task 3: Refunds
- Handles partial or full refunds using `php artisan orders:refund {order_id} {amount?}`.
- Processes refunds asynchronously, updates KPIs/leaderboard, and ensures idempotency using `refund_id`.

---

## Flow Diagram

The diagram below illustrates the end-to-end workflow of the Order Processing Application.
It shows how orders are imported, validated, and processed through stock reservation and payment simulation.
It also highlights how notifications and KPIs/leaderboards are updated on success, how the system handles failures (payment or stock issues), and how the refund process is managed separately to ensure accurate financial tracking.

![Alt text](public\order_processing_flow.png)


---
## Prerequisites

- **OS:** Windows 11 with WSL (Ubuntu recommended for Redis and Supervisor)
- **PHP:** 8.2+ (thread-safe, with SQLite and Redis extensions)
- **Composer:** Latest version
- **Redis:** Installed via WSL (`sudo apt install redis-server`) or Windows MSI
- **SQLite:** Built into PHP
- **DB Browser for SQLite:** Optional
- **Git:** For cloning/managing repository


---

## Installation

### 1. Clone the Laravel Project
```bash
git clone https://github.com/MrSriJay/order-processing-app.git
cd order-processing-app
```


### 2. Install Redis, Supervisor and Horizon

#### WSL (Windows Subsystem for Linux):
WSL allows you to run a Linux environment directly on Windows without using a virtual machine. It provides a Linux terminal and supports Linux tools, making it ideal for running services like Redis, Supervisor, and Laravel Horizon on Windows.


```bash
# Update packages
sudo apt update

# Install Redis
sudo apt install redis-server
sudo service redis-server start
redis-cli ping  # Should return PONG

# Install Supervisor
sudo apt install supervisor

# Install Laravel Horizon
composer require laravel/horizon predis/predis
php artisan horizon:install


# Additional
php artisan config:clear
php artisan horizon &
php artisan horizon:status
redis-cli flushdb

```

### 3. Install PHP dependencies & setup

Run these commands from the project root. On Windows it's recommended to use WSL (Ubuntu) for Redis/Supervisor-related steps; the commands below are POSIX/bash-friendly.

##### 3.1 Edit .env file:
```bash
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite #Change the path to your sqlite file

QUEUE_CONNECTION=redis  # We'll set this later; start with 'sync' for testing
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MAIL_MAILER=log  # For email notifications (logs to storage/logs/laravel.log); change to 'smtp' for real emails

```
##### 3.2 Install PHP and Database Migrations

```bash
composer install
php artisan key:generate
php artisan migrate --seed
```

### 4. Run the Project

1. **Start Redis**  
   Ensure Redis is running on your machine. You can start it with:

   ```bash
      redis-server
   ```
   
2. **Run Server**  
   To start the Laravel development server, run:
    ```bash
       php artisan serve
    ```  
3. **Run Queue Worker**  
   Start the Laravel queue worker to process queued jobs:
    ```bash
       php artisan queue:work redis
    ```  

4. **Import CSV**  
   TTo import orders from a CSV file, run (CSV file stored in project root):
    ```bash
       php artisan orders:import orders.csv
    ```  

5. **View KPIs**  
   To view the KPIs (Key Performance Indicators), run:
    ```bash
       php artisan orders:kpis
    ```  

6. **Refund Orders**  
   To run the refund process, run:
    ```bash
       php artisan orders:refund {order_id} {amount}
    ```  
---

##  API Endpoints

  ### 1. KPI API Endpoint
    - **Route**: `GET /api/kpis`
    - **Purpose**: Returns KPI metrics (total revenue, order count, average order value) and customer leaderboard from Redis (`kpis:YYYY-MM-DD`, `leaderboard:customers`) or database fallback.
    - **Response**:
      ```json
      {
        "kpis": {
          "total_revenue": "19792.00",
          "order_count": 9,
          "average_order_value": "2199.11"
        },
        "leaderboard": [
          {"name": "Jane Doe", "email": "jane@example.com", "total": "7500.00"},
          ...
        ]
      }
      ```

  ### 2. KPI Dashboard
    - **Route**: `GET /kpi-dashboard`
    - **Purpose**: Renders the KPI dashboard view (`kpi-dashboard.blade.php`), fetching data from `/api/kpis` to display KPIs and leaderboard.
    - **Implementation**: Web route in `routes/web.php` returning `kpi-dashboard` view.
    
![Alt text](public\kpi-dashboard.png)

  ### 3. Horizon Dashboard
    - **Route**: `GET /horizon`
    - **Purpose**: Monitors Horizon queues, showing job status (`ProcessRefund`, `ProcessOrder`), metrics, and monitoring.
    - **Implementation**: Provided by Laravel Horizon, configured in `config/horizon.php`.

![Alt text](public\horizon-dashboard.png)

---

## Notifications

The application uses the `SendOrderNotification` job to notify users of order status changes (e.g., completed, refunded, failed).

- **Process**:
  - Dispatched by `ProcessOrder` and `ProcessRefund` jobs via Laravel Horizon.
  - Logs notification details to `storage/logs/ordersNotificationMessages.log`.
  - Example log entry:
    ```
    [2025-09-25 06:49:45] local.INFO: Sending notification for Order ID: 1, status: refunded
    ```

- **Configuration**:
  - Custom log channel in `config/logging.php`:
    ```php
    'channels' => [
        'orders' => [
            'driver' => 'single',
            'path' => storage_path('logs/ordersNotificationMessages.log'),
            'level' => 'info',
        ],
    ]
    ```
  - Job logs via:
    ```php
    \Log::channel('orders')->info("Sending notification for Order ID: {$order->id}, status: {$status}");
    ```

- **Verify**:
  - Check logs:
    ```bash
    cat storage/logs/ordersNotificationMessages.log
    ```
  - Test:
    ```bash
    php artisan orders:refund 1
    ```