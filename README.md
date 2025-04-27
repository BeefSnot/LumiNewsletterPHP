# 📧 LumiNewsletter - Professional Email Marketing Solution

![LumiNewsletter Logo](https://github.com/yourusername/LumiNewsletter/raw/main/assets/img/logo.png)

**Current Version: 1.43** | **Release Date: April 27, 2025**

## 📋 Table of Contents

- [🌟 Overview](#-overview)
- [✨ Key Features](#-key-features)
- [🖥️ System Requirements](#️-system-requirements)
- [📦 Installation Guide](#-installation-guide)
- [⚙️ Configuration](#️-configuration)
- [🧩 User Guide](#-user-guide)
- [🔌 API Documentation](#-api-documentation)
- [🔒 Security Recommendations](#-security-recommendations)
- [🛠️ Troubleshooting](#️-troubleshooting)
- [📄 License](#-license)

## 🌟 Overview

LumiNewsletter is a comprehensive, self-hosted email marketing system designed for businesses looking to connect with their audience. Create newsletters, manage subscribers, and track engagement—all from your own server.

## ✨ Key Features

- **Newsletter Creation** - Visual editor and code editor options
- **Subscriber Management** - Group-based organization and easy import/export
- **User Management** - Role-based access control
- **Theme Builder** - Create and save custom designs
- **Email Delivery** - Custom SMTP configuration and testing tools
- **System Features** - Easy installation, one-click updates, mobile-friendly

## 🖥️ System Requirements

- **PHP**: 7.4+ with mysqli, json, mbstring, openssl extensions
- **MySQL/MariaDB**: 5.6+
- **Disk Space**: 10MB minimum
- **Memory**: 128MB PHP memory limit recommended

## 📦 Installation Guide

### 1️⃣ Preparation
1. **Download** from [the official website](https://lumihost.net)
2. **Upload** all files to your web server

### 2️⃣ Server Setup
1. Create a MySQL database and note credentials

### 3️⃣ Web Installation
1. Navigate to `https://your-domain.com/path-to-lumi/install.php`
2. Follow the installation wizard

### 4️⃣ Post-Installation Security
> ⚠️ **IMPORTANT**: Immediately delete `install.php` after installation!

```bash
rm /path/to/your/website/install.php

