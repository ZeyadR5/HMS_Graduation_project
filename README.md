<div align="center">
  <img src="assets/images/echol.png" alt="Echo HMS Logo" width="120" />
  <h1>Echo HMS - Advanced Hospital Management System</h1>
  <p><strong>A Next-Generation Healthcare Platform with AI, Mobile Integration, and High Security</strong></p>
  
  [![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)
  [![MySQL](https://img.shields.io/badge/MySQL-Database-4479A1?style=for-the-badge&logo=mysql&logoColor=white)](https://mysql.com)
  [![Flutter](https://img.shields.io/badge/Flutter-Mobile_App-02569B?style=for-the-badge&logo=flutter&logoColor=white)](https://flutter.dev)
  [![OpenAI](https://img.shields.io/badge/AI-OpenAI_GPT_4o-412991?style=for-the-badge&logo=openai&logoColor=white)](https://openai.com)
</div>

<br/>

**Echo HMS** is a comprehensive, graduation-level Hospital Management System designed to bridge the gap between clinical efficiency and patient experience. It uniquely combines a robust web portal for administration with a seamless Flutter mobile application for patients and doctors.

---

## ✨ Key Features & Architecture

### 🛡️ Enterprise-Grade Security
- **Strict Patient Activation Flow**: New patients linking their National IDs must receive an Admin-generated Activation Code to prevent unauthorized data access.
- **Secure Password Recovery**: Integrated `PHPMailer` for robust OTP (One-Time Password) email verifications and password resets.
- **Directory Protection**: Hardened `.htaccess` rules prevent unauthorized directory browsing and protect core backend modules.
- **Audit Logging**: Comprehensive internal tracking of sensitive actions (e.g., generating codes, updating profiles).

### 🤖 AI-Powered Clinical Assistance
- **Smart Medical Records (SOAP)**: Doctors can generate structured clinical summaries and action plans from raw consultation notes instantly using OpenAI integration.
- **Echo Assistant Chatbot**: A contextual 24/7 AI chatbot tailored to assist patients with system navigation and general (non-diagnostic) inquiries.

### 📱 Flutter Mobile Integration
- **Hybrid WebView App**: A dedicated mobile application wrapper built with Flutter.
- **Real-Time Push Notifications**: A custom polling architecture (`api-poll-notifications.php`) seamlessly triggers local push notifications on Android/iOS devices when patients or doctors receive updates.

### 🏥 Clinic Operations
- **Live Queue Management**: Real-time waiting room displays (`queue-screen.php`) for patients to track their turns dynamically.
- **Doctors Timetable**: Interactive scheduling system for patients to view doctor availability and book appointments instantly.
- **Financial Dashboard**: Detailed revenue tracking, deposit management, and printable receipts.

---

## 🚀 Technology Stack

| Component | Technology |
|---|---|
| **Backend Core** | PHP (Vanilla / Modular) |
| **Database** | MySQL |
| **Frontend Web** | HTML5, CSS3, JavaScript, Tailwind CSS, Bootstrap |
| **Mobile App** | Flutter (Dart), `flutter_local_notifications` |
| **AI Engine** | OpenAI GPT-4o-mini API |
| **Email Service** | PHPMailer (SMTP via Gmail) |

---

## ⚙️ Installation & Setup

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/ZeyadR5/HMS_Graduation_project.git
   ```

2. **Database Configuration**:
   - Run a web server (IIS or Apache) and a MySQL-compatible database server.
   - This PHP application connects to MySQL/MariaDB with the `mysqli` extension; IIS is the web server, not the database engine.
   - Copy `.env.example` to `.env` and set your database credentials there.
   *(Note: The database schema is assumed to be pre-configured on your SQL server).*

3. **Configure Environment Variables (AI & Mailer)**:
   - Set `FAWATERAK_API_KEY` to enable Fawaterak payments.
   - Set `HMS_SMTP_USER` and `HMS_SMTP_PASS` to enable email notifications. Never commit `.env`; it is ignored by Git.

4. **Launch Web Platform**:
   - Place the project in your `htdocs/hms` directory.
   - Access the system via `http://localhost/hms`.

5. **Launch Mobile App (Optional)**:
   - Navigate to the `echo_system` directory.
   - Run `flutter pub get` followed by `flutter run` on your connected emulator or physical device.

---

## 🎓 About This Project
This project was developed as a comprehensive **Graduation Project**, showcasing modern web development, API integration, AI utilization, and mobile-first hybrid application strategies in the healthcare sector.

---
<div align="center">
  <i>Developed with ❤️ for the future of healthcare technology.</i>
</div>