# Echo HMS - Hospital Management System 🏥

**Echo HMS** is a comprehensive Hospital Management System designed to streamline healthcare operations, enhance patient care, and integrate advanced AI capabilities for clinical documentation and patient support.

---

## 🌟 Key Features

### 👨‍⚕️ For Doctors
- **AI Clinical Assistant**: Automatically transform raw medical notes into structured SOAP notes and professional documentation.
- **Patient History Summarization**: Get an AI-generated clinical timeline and overview of patient records.
- **Appointment Management**: View and manage daily schedules and patient visits.
- **Digital Prescriptions & Scans**: Issue digital medical records and track patient progress.

### 👤 For Patients
- **AI Health Chatbot (Echo Assistant)**: Instant support for appointment inquiries and general information (non-diagnostic).
- **Appointment Booking**: Dynamic scheduling system with real-time availability.
- **Medical Records**: Access personal medical history, prescriptions, and reports.
- **Secure Dashboard**: Manage profile and track upcoming visits.

### 🔑 For Administrators
- **Super Admin Dashboard**: Full control over system users (Doctors, Patients, Staff).
- **Financial Management**: Track payments and consultation fees.
- **Audit Logs**: Monitor system activities for security and transparency.
- **System Configuration**: Manage doctor specializations and department schedules.

---

## 🚀 Tech Stack

- **Backend**: PHP (Vanilla/Modular Architecture)
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript, Tailwind CSS / Bootstrap
- **AI Integration**: OpenAI GPT-4o-mini (via API)
- **Server**: XAMPP/Apache

---

## 🛠️ Installation

1. **Clone the Repository**:
   ```bash
   git clone https://github.com/zeyad4a/HMS_Graduation_project.git
   ```

2. **Database Setup**:
   - Import the `hms.sql` file (found in the `db/` folder) into your MySQL database.
   - Configure database credentials in `includes/config.php`.

3. **OpenAI API Key**:
   - Obtain an API key from [OpenAI](https://platform.openai.com/).
   - Update the placeholder `YOUR_OPENAI_API_KEY` in the following files:
     - `modules/doctor/doctor-ai-api.php`
     - `modules/patient/ai-proxy.php`
     - `modules/patient/chatbot-api.php`

4. **Run Local Server**:
   - Place the project folder in your `htdocs` directory.
   - Start Apache and MySQL via XAMPP.
   - Access the system via `http://localhost/hms`.

---

## 🛡️ Security Note

The project uses OpenAI's API for advanced features. Please ensure your API keys are kept secure. Do not commit your keys to public repositories.

---

## 📝 License

This project is developed as a Graduation Project. All rights reserved.

---

### 📞 Contact & Support

For inquiries or support, feel free to reach out to the project maintainers.