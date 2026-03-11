# Deployment Guide (Free Hosting)

This guide will walk you through deploying your Personal Management System (PMS) to a free hosting provider like **InfinityFree** or **000webhost**.

## 1. Get Free Hosting
1. Go to [InfinityFree](https://infinityfree.com/) or [000webhost](https://www.000webhost.com/) and register for a free account.
2. Create a new hosting account and pick a free subdomain (e.g., `hashimpms.epizy.com`).
3. Wait a few minutes for the account to be created.
4. Log into your hosting **cPanel** (Control Panel).

## 2. Setup the Database
1. In your cPanel, find the **MySQL Databases** section.
2. Create a new database (e.g., `epiz_3456789_pms`).
3. Note down the:
   - MySQL Host Name (e.g., `sql101.epizy.com`)
   - MySQL Database Name
   - MySQL User Name
   - MySQL Password (usually your cPanel password)
4. Go to **phpMyAdmin** in your cPanel.
5. Select your new database on the left.
6. Click the **Import** tab at the top.
7. Upload the `database.sql` file from this project and click **Go**. This will create the `users`, `tasks`, and `transactions` tables.

## 3. Upload Files
1. In your PMS project folder, create a `.zip` file of all the files (excluding `node_modules` if any, but include the `vendor` folder!).
   - *Note: Since you ran Composer locally, the `vendor` folder is required. Make sure it is uploaded!*
2. Open the **Online File Manager** in your cPanel.
3. Open the `htdocs` (or `public_html`) folder.
4. Upload your `.zip` file and extract it.
5. Alternatively, you can use an FTP client like FileZilla to upload the files.

## 4. Configure Application (.env)
1. In the File Manager, open the `.env` file you extracted.
2. Update the Database credentials with the ones you noted down:
   ```env
   DB_HOST=sql101.epizy.com
   DB_NAME=epiz_3456789_pms
   DB_USER=epiz_3456789
   DB_PASS=your_cpanel_password

   # Change this to a random string!
   JWT_SECRET=my_production_super_secret_key

   # Update with your Gmail App Password for Reminders
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=your_email@gmail.com
   SMTP_PASS=your_gmail_app_password
   ```
3. Save the `.env` file.

## 5. Update Frontend API URL
1. Open `js/modules/api.js`.
2. Change the first line to point to your live domain:
   ```javascript
   const API_URL = 'http://your-free-subdomain.com/api';
   ```
3. Save the file.

## 6. Setup Cron Jobs for Reminders
You need a Cron Job to automatically check and send email reminders for tasks.
1. In your cPanel, search for **Cron Jobs**.
2. Set the interval. (e.g., Every hour: `0 * * * *`).
3. Set the command to execute your `reminders.php` script. The exact command depends on the hosting, but it generally looks like this:
   ```bash
   php -q /home/vol15_1/epizy.com/epiz_3456789/htdocs/cron/reminders.php
   ```
   *Note: If your free host does not support Cron Jobs, you can use a free external service like [Cron-job.org](https://cron-job.org/) and point it to a URL version of your reminder script (you would need to expose it via the router if you do this).*

## 7. Test!
Go to your live URL (e.g., `http://your-free-subdomain.com/`) in your browser. Register a new account, upload an income excel sheet, create a task, and verify everything works!
