# 📧 LumiNewsletter - Professional Email Marketing Solution

![LumiNewsletter Logo](https://github.com/BeefSnot/LumiNewsletterPHP/raw/main/assets/images/lumihost.png)

## Website (currently work in progress, but works!) (https://lumisolutions.tech/newsletterv1/index.php

## Test site - https://newsletter.lumisolutions.tech

Test login (only editor, no admin. Working on an actual test site as we speak)

Test
Test1234


**I will be organizing project very soon!**

**Current Version: 1.559** | **Release Date: April 27, 2025**

## 📋 Table of Contents

- 🌟 Overview
- ✨ Key Features
- 🖥️ System Requirements
- 📦 Installation Guide
- ⚙️ Configuration
- 🧩 User Guide
- 🔌 API Documentation
- 🔒 Security Recommendations
- 🛠️ Troubleshooting
- 📄 License

## 🌟 Overview

LumiNewsletter is a comprehensive, self-hosted email marketing system designed for businesses looking to connect with their audience. Create newsletters, manage subscribers, and track engagement—all from your own server.

## ✨ Key Features

- **Newsletter Creation** - Visual editor and code editor options
- **Subscriber Management** - Group-based organization and easy import/export
- **User Management** - Role-based access control
- **Theme Builder** - Create and save custom designs
- **Email Delivery** - Custom SMTP configuration and testing tools
- **System Features** - Easy installation, one-click updates, mobile-friendly
- **📊 Advanced Analytics** - Track opens, clicks, and subscriber engagement
- **🧪 A/B Testing** - Compare different email subjects and content
- **🤖 Automation** - Create multi-step workflows with conditions and delays
- **🔑 API Integration** - Programmatic access with secure API keys
- **📱 Mobile Responsive** - Fully adaptive dashboard with sidebar navigation
- **👥 Subscriber Groups** - Organize contacts with customizable groups
- **🔒 Privacy Controls** - GDPR-compliant subscriber consent management
- **🔄 Segment Management** - Dynamic subscriber segmentation based on behavior
- **✉️ Email Personalization** - Custom tags and dynamic content blocks

## 🖥️ System Requirements

- **PHP**: 7.4+ with mysqli, json, mbstring, openssl extensions
- **MySQL/MariaDB**: 5.6+
- **Disk Space**: 10MB minimum (50MB+ recommended for growth)
- **Memory**: 128MB PHP memory limit recommended
- **Web Server**: Apache or Nginx with mod_rewrite enabled
- **Browser**: Modern browsers (Chrome, Firefox, Safari, Edge)

## 📦 Installation Guide

### 1️⃣ Preparation
1. **Download** from [The Official Download Link](https://lumisolutions.tech/newsletterupdates/luminewsletterlatest.zip), or you can also use github releases to download the zip!
2. **Upload** all files to your web server
3. **Extract** all the files, and move them to root directory!
4. **Ensure** file permissions are set correctly (755 for directories, 644 for files)

### 2️⃣ Server Setup
1. Create a MySQL database and note credentials
2. Make sure your PHP version meets requirements (7.4+)
3. Enable required PHP extensions (mysqli, json, mbstring, openssl)

### 3️⃣ Web Installation
1. Navigate to `https://your-domain.com/path-to-lumi/install.php`
2. Follow the installation wizard (requirements check, database setup, admin account, SMTP)
3. Complete all steps in the installation process

### 4️⃣ Post-Installation Security
> ⚠️ **IMPORTANT**: Immediately delete install.php after installation!

```bash
rm /path/to/your/website/install.php
```

## 🔐 User Roles & Permissions

- **👑 Admin** - Full system access including user management and system settings
- **📝 Editor** - Can create and send newsletters, manage subscribers
- **👁️ Viewer** - Read-only access to reports and subscriber lists

## ⚙️ Advanced Configuration

- **🎨 Theme Customization** - Create and customize email templates
- **📨 SMTP Settings** - Configure multiple email delivery providers
- **📱 API Management** - Generate and manage API keys for external integrations
- **🔒 Privacy Settings** - Configure data retention and user consent options
- **📊 Analytics Integration** - Connect with external analytics platforms

## Yes, the installation script (install.php) contains all the necessary database tables for the current features, including:

- User authentication and role management
- Newsletter creation and delivery
- Subscriber management with groups and segmentation
- Email analytics tracking
- A/B testing capabilities
- Automation workflows
- API integration
- Content personalization
- Privacy and consent management
- Social sharing functionality

The database structure will be automatically created during installation, ensuring all features work correctly from the start. If not, please let us know!
