# 📧 LumiNewsletter - Professional Email Marketing Solution

![LumiNewsletter Logo](https://github.com/yourusername/LumiNewsletter/raw/main/assets/img/logo.png)

**Current Version: 1.43**  
**Release Date: April 27, 2025**

## 📋 Table of Contents

- [Overview](#-overview)
- [Key Features](#-key-features)
- [System Requirements](#-system-requirements)
- [Installation Guide](#-installation-guide)
- [Configuration](#-configuration)
- [User Guide](#-user-guide)
  - [Dashboard](#dashboard)
  - [Creating Themes](#creating-themes)
  - [Managing Subscribers](#managing-subscribers)
  - [Creating & Sending Newsletters](#creating--sending-newsletters)
  - [Managing Users](#managing-users)
  - [SMTP Configuration](#smtp-configuration)
  - [Embedding Subscription Form](#embedding-subscription-form)
- [API Documentation](#-api-documentation)
- [Security Recommendations](#-security-recommendations)
- [Troubleshooting](#-troubleshooting)
- [Updates & Maintenance](#-updates--maintenance)
- [License](#-license)
- [Support](#-support)

## 🌟 Overview

LumiNewsletter is a comprehensive, self-hosted email marketing and newsletter management system designed for businesses and organizations looking for a professional solution to connect with their audience. With an intuitive interface and powerful features, LumiNewsletter makes it easy to create beautiful newsletters, manage subscriber lists, and track engagement—all from your own server.

Built with PHP and featuring a modern, responsive design, LumiNewsletter provides all the tools you need to run successful email marketing campaigns without recurring subscription costs or third-party dependencies.

## ✨ Key Features

### 📝 Newsletter Creation & Management
- **Visual Drag-and-Drop Editor** - Create professional newsletters with no coding knowledge required
- **Code Editor** - Advanced users can fine-tune HTML and CSS directly
- **Theme Library** - Start with pre-designed templates to speed up creation
- **Newsletter Archive** - Keep track of all sent newsletters

### 👥 Subscriber Management
- **Group-based Subscriptions** - Organize subscribers into different groups/lists
- **Subscription Management** - Add, remove, and manage subscribers easily
- **Self-service Subscription** - Public-facing subscription/unsubscription page
- **Customizable Welcome Emails** - Personalized messages for new subscribers

### 👤 User Management
- **Role-based Access Control** - Admin and user roles with different permissions
- **User Administration** - Add, edit, and remove system users
- **Secure Authentication** - Password hashing and security measures

### 🎨 Theme Builder
- **Advanced Theme Editor** - Build newsletter themes using GrapesJS
- **Template System** - Save and reuse your designs
- **Responsive Design Tools** - Ensure your newsletters look great on all devices

### 📊 Email Delivery
- **Custom SMTP Configuration** - Use your preferred email service provider
- **Testing Tools** - Verify your SMTP settings before sending
- **Bulk Sending Capabilities** - Send newsletters to multiple subscriber groups

### ⚙️ System Features
- **Easy Installation** - Web-based setup wizard
- **One-click Updates** - Stay up to date with the latest features
- **Customizable Settings** - Tailor the system to your needs
- **Mobile-Friendly Admin Interface** - Manage your newsletters from anywhere
- **Embeddable Subscription Forms** - Add subscription forms to any website

## 🖥️ System Requirements

Before installing LumiNewsletter, ensure your server meets these requirements:

- **PHP**: Version 7.4 or higher
- **MySQL/MariaDB**: Version 5.6 or higher
- **PHP Extensions**:
  - mysqli
  - json
  - mbstring
  - openssl
- **Web Server**: Apache, Nginx, or any PHP-compatible server
- **Disk Space**: Minimum 10MB for the application (more for storing newsletters)
- **Memory**: Minimum 128MB PHP memory limit recommended
- **Browser**: Modern browser (Chrome, Firefox, Safari, Edge) for admin panel

## 📦 Installation Guide

### 1️⃣ Preparation
1. **Download** the latest version of LumiNewsletter from [the official website](https://lumihost.net)
2. **Extract** the files to your local computer
3. **Upload** all files to your web server (via FTP, SSH, or your hosting control panel)

### 2️⃣ Server Setup
1. **Create a Database**:
   - Create a new MySQL database for LumiNewsletter
   - Note your database name, username, and password

### 3️⃣ Web-based Installation
1. **Launch the Installer**:
   - Navigate to `https://your-domain.com/path-to-lumi/install.php`
   - The installer will guide you through four simple steps

2. **System Requirements Check**:
   - The installer will verify that your server meets all requirements
   - Address any issues flagged by the installer before proceeding

3. **Database Configuration**:
   - Enter your database details:
     - Host (usually "localhost")
     - Database name
     - Username
     - Password

4. **Admin Account Creation**:
   - Set up your administrator account:
     - Username
     - Email
     - Password (use a strong, unique password)

5. **SMTP Configuration**:
   - Configure your email sending settings:
     - SMTP host (e.g., smtp.gmail.com)
     - SMTP username
     - SMTP password
     - SMTP port (typically 587 or 465)
     - SMTP security (TLS/SSL)

6. **Completion**:
   - Once installation is complete, you'll see a success message
   - Click the link to go to the login page

### 4️⃣ Post-Installation Security Steps

> ⚠️ **IMPORTANT**: After successful installation, IMMEDIATELY delete the `install.php` file from your server!

```bash
rm /path/to/your/website/install.php

This step is crucial to prevent unauthorized access to the installation wizard.

⚙️ Configuration
Admin Settings
Access Admin Settings:

Log in as administrator
Navigate to "Admin Settings" in the sidebar
General Settings:

Newsletter Title: Set the title that appears in the admin dashboard
Background URL: Customize the background image (optional)
Website URL: Set your website's URL for embedded widgets
SMTP Settings
Access SMTP Configuration:

Go to "SMTP Settings" in the sidebar
Configure Your Email Provider:

For Gmail:

Host: smtp.gmail.com
Port: 587
Security: TLS
Username: your Gmail address
Password: your app password (requires 2FA setup)
For Office 365:

Host: smtp.office365.com
Port: 587
Security: TLS
Username: your email address
Password: your password
Test Your Configuration:

Send a test email to verify settings
Troubleshoot if the test fails
Subscriber Groups
Create Subscriber Groups:
Go to "Subscribers" in the sidebar
Create relevant groups for segmenting your audience
Example groups: "Newsletter", "Product Updates", "Promotions"
🧩 User Guide
Dashboard
The dashboard provides an overview of your system with quick access to all features:

Recent Newsletters: Quick view of recently sent newsletters
Subscriber Stats: Overview of your subscriber count
Quick Actions: Buttons to create newsletters or manage subscribers
Creating Themes
Access Theme Creator:

Click "Create Theme" in the sidebar
Enter a name for your theme
Choose Design Method:

Visual Editor: Drag-and-drop interface for easy design
Code Editor: Direct HTML/CSS editing for advanced users
Start with a Template:

Select from pre-built templates
Customize with your content and branding
Design Your Theme:

Add headers, footers, content sections, images, buttons
Customize colors, fonts, spacing
Preview how it will look on different devices
Save Your Theme:

Click "Save Theme" when finished
Your theme will be available when creating newsletters
Managing Subscribers
Access Subscriber Management:

Click "Subscribers" in the sidebar
View and Filter:

Browse all subscribers
Filter by group
Add Subscribers Manually:

Click "Add Subscriber"
Enter email address
Select appropriate group(s)
Import Subscribers:

Click "Import Subscribers"
Upload CSV file with email addresses
Map fields and assign to groups
Export Subscribers:

Filter the subscribers you want to export
Click "Export" button
Choose format (CSV/Excel)
Creating & Sending Newsletters
Create a New Newsletter:

Click "Send Newsletter" in the sidebar
Enter a subject line
Select Content Options:

Choose a theme
Select recipient group(s)
Compose Your Content:

Use the rich text editor to create content
Add images, links, buttons, etc.
Preview your newsletter
Test Your Newsletter:

Send a test email to yourself
Check how it appears in various email clients
Schedule or Send:

Send immediately or schedule for later
Confirm delivery details
Managing Users
Access User Management:

Click "Users" in the sidebar
Add New User:

Click "Add New User"
Enter username, email, password
Assign appropriate role
Edit Existing Users:

Click "Edit" next to any user
Modify details or permissions
Delete Users:

Click "Delete" next to unwanted users
Confirm deletion
SMTP Configuration
Access SMTP Settings:

Click "SMTP Settings" in the sidebar
Configure Email Server:

Enter SMTP server details
Save settings
Test Email Delivery:

Enter a test email address
Click "Send Test Email"
Verify receipt
Embedding Subscription Form
Access Embed Instructions:

Click "Embed Widget" in the sidebar
Choose Embedding Method:

iFrame Embed: Simplest method, copy and paste HTML
JavaScript Embed: More flexible integration
Direct Include: Advanced customization options
Set Website URL:

Go to "Admin Settings"
Enter your website URL in the "Website URL" field
This ensures embed links are correct
Implementation Examples:

WordPress: Add to widget area or use shortcode
HTML website: Add to any div or container
Custom site: Integrate with your existing forms
Customization:

Adjust width, height, colors through data attributes
Customize success/failure messages
🔌 API Documentation
LumiNewsletter provides a basic API for integration with other systems:

Subscription API
Endpoint: subscribe.php
Method: POST
Parameters:

email (required): Subscriber's email address
group_id (required): Group ID to subscribe to
name (optional): Subscriber's name
Example:
{
  "email": "subscriber@example.com",
  "group_id": 1,
  "name": "John Doe"
}

Response:

{
  "success": true,
  "message": "Subscription successful"
}

🔒 Security Recommendations
Server Security:

Keep PHP and MySQL updated
Use HTTPS (SSL/TLS) for your domain
Implement server-level security measures
Application Security:

Delete install.php after installation
Use strong passwords for all accounts
Regularly backup your database
SMTP Security:

Use encrypted connections (TLS/SSL)
Consider using API keys instead of passwords when possible
Regularly rotate SMTP credentials
User Management:

Grant minimal necessary permissions to users
Regularly audit user accounts
Remove inactive users
Database Security:

Use a dedicated database user with limited permissions
Secure database credentials
Consider database encryption for sensitive data
🛠️ Troubleshooting
Common Issues
Installation Problems:

Verify server meets all requirements
Check database connection details
Ensure correct file permissions (typically 755 for folders, 644 for files)
Email Sending Issues:

Verify SMTP settings
Check for server-level email restrictions
Test with different email providers
Visual Editor Not Loading:

Clear browser cache
Ensure JavaScript is enabled
Try a different browser
Database Errors:

Check database connection
Verify table structure is intact
Ensure MySQL user has proper permissions
Embed Widget Issues:

Verify Website URL is correctly set in Admin Settings
Check for cross-origin issues
Try different embedding methods
Error Logs
If you encounter issues, check these logs:

PHP Error Logs:

Check your server's PHP error log
Common locations: /var/log/apache2/error.log or /var/log/nginx/error.log
LumiNewsletter Logs:

Check /logs directory in your LumiNewsletter installation
Getting Support
If you encounter issues not covered in this documentation:

Check Error Logs:

PHP error logs
Application logs
Contact Support:

Email: support@lumihost.net
Website: https://lumihost.net
🔄 Updates & Maintenance
Automatic Updates
Check for Updates:

Go to Admin Settings
Look for update notifications
One-Click Update:

Click "Download & Install Update"
The system will download and apply updates automatically
Manual Updates
Download Updates:

Get the latest version from https://lumihost.net/updates/
Extract the files locally
Backup Your System:

Export your database
Back up all files
Upload New Files:

Replace existing files with new versions
Preserve your configuration files:
config.php
Custom themes and templates
Run Update Scripts:

Visit https://your-domain.com/path-to-lumi/update.php
Follow on-screen instructions
Regular Maintenance
Database Optimization:

Regularly optimize tables
Remove unnecessary data
File Management:

Clean up old files
Ensure proper permissions
Security Audits:

Review user accounts
Check for suspicious activity
📄 License
LumiNewsletter is provided by Lumi Solutions. All rights reserved.

Version: 1.43
Release Date: April 27, 2025
Author: Lumi Solutions
For licensing questions, please contact info@lumihost.net.

🙋 Support
For technical support, feature requests, or bug reports:

Email: support@lumihost.net
Website: https://lumihost.net/support
Documentation: https://docs.lumihost.net
🙏 Thank you for choosing LumiNewsletter!

If you have any questions or need assistance, please don't hesitate to contact us.

© 2025 Lumi Solutions. All rights reserved.

