# <img src="public/favicon.svg" alt="CORE" width="36px" height="36px" /> CORE: **C**ommunity-driven **O**pen **R**esearch **E**cosystem

OpenArxiv (CORE) as a live instantiation of CORE, is a novel platform for the ePrint service backed by rigorous, community-based evaluation of scientific research leading to a self-sustaining research ecosystem — built with vanilla PHP 8+, MySQL/MariaDB, and modern MVC techniques.

## 🌟 Why CORE?

A deep structural crisis in academia requires a complete reform for continued scientific innovation. CORE is designed to ensure fairness, transparency, and efficiency in knowledge dissemination, resource allocation, and career advancement, creating the conditions for a new era of innovation in basic research.
- **The overarching principle is the Diversity Principle** —  advocating for a merit-based system that constantly cultivates diverse ideas and directions to foster innovation.
- **Community-driven** —  engaging the entire community so that participation is all-inclusive, voices are democratic, and diverse ideas and directions in research are
preserved and actively promoted; also implying that it mirrors the structure of a
healthy society with built-in monopoly-breaking measures against the top and a ro-
bust safety net for the bottom.
- **Open** —  indicating an open-ended, open-minded, open-to-risk, and dynamically calibrated system that maintains transparency of the procedure and process while protecting each individual’s will and privacy, constantly evolving to meet new challenges and resist gaming.
- **Research** —  requiring scientific, rigorous, and merit-based ethos and approaches.
- **Ecosystem** —  implementing healthy, self-correcting feedback loops and proper incentive mechanisms in a living, self-regulating system.
  
CORE introduces a **Rigorous Dual Evaluation System** for STEM and other research fields to implement proper incentives and complete the feedback cycle:
- **Three research activities** — original research, indirect contributions (e.g., peer review), and funding/resource requests — are evaluated using quantitative, continually refined metrics.
- **Quality over quantity** — the community-based evaluation system rewards the quality, not the quantity, of accomplishments (**AL**).
- **Credit-based incentives** — members earn credits (**ECP**) and advance in roles as they accumulate experience and contributions.
- **High-risk, high-reward research** — gets a better chance of being funded through sophisticated incentive mechanisms.

## 🚀 Features (Phase 1 — MVP)
| Feature | Description |
|---------|-------------|
| **Authentication** | Email/password registration and login with "Remember Me" support |
| **ORCID Integration** | Login and register via [ORCID](https://orcid.org/) |
| **ePrint Repository** | Upload, view, revise, and stream PDF documents |
| **Draft Workflow** | Draft submission → approval → finalization pipeline |
| **Math Support** | MathJax for Tex/Latex to SVG ($...$, \$ to Escape)|
| **Member Profiles** | View and edit profiles with extended metadata |
| **Research Branches** | 4-level hierarchy: Discipline → Field → Area → Direction |
| **Research Topics** | Multi-parent emerging cross-disciplinary topics |
| **Admin Dashboard** | Moderation tools for administrators (role ≥ 600) |
| **Display Style** | Clean, academic, minimalist design |

### 💡 Upcoming Major Features
- **Evaluation system** — rating, review, and comment workflows
- **Achievement Levels (AL)** — quantified researcher accomplishments
- **Earned Credit Points (ECP)** — credit-based incentive system
- **Committees** — governance and moderation bodies
- **Document types** — 19+ document categories (o-doc, r-doc, a-doc, f-doc, p-doc, etc.)
- **Collaboration** — value-added features for collaborations / teams
- **Member Organizations** — sponsoring member institutions, foundations, etc.
- **Funding system** — allocation of research resources
- **Job/Hiring system** — using the new evaluation system
- **Voting system** — election and award workflows
- **Tagging system** — fraud / user-defined tags
- **API & theming** — plugin architecture and custum themes

## 🛠️ Tech Stack

| Component | Technology |
|-----------|------------|
| Backend | PHP 8+ (strict types, OOP) |
| Database | MySQL / MariaDB via PDO |
| Frontend | HTML5, Vanilla CSS, Vanilla JS |
| Authentication | Password hashing, ORCID OAuth 2.0 |
> **No frameworks. No Composer. No dependencies. Pure vanilla PHP.**
> **Custom MVC Architecture. Front Controller. Responsive UI.**

## 🔒 Security

- **CSRF protection** — tokens validated on all POST requests
- **XSS prevention** — `htmlspecialchars()` on all user-generated output
- **SQL injection** — PDO prepared statements exclusively
- **Password storage** — `password_hash()` / `password_verify()`
- **Visibility & access** — Guest (1) → Member (10) → Rater (20) → Reviewer (30) → Moderator (40) → Leader (50)
- **Admin roles** — Viewer (100) → Updater (200) → Editor (300) → Senior Editor (400) → Chief Editor (500) → Administrator (600)

## 📂 Project Structure (MVC Architecture)

```text
core/
├── public/                  # Web root (only public entry point)
│   ├── index.php            # Front controller
│   ├── js/                  # Javascripts
│   └── css/                 # Stylesheets
├── app/
│   ├── controllers/         # Business logic (skinny controllers)
│   │   ├── admin/           # Admin controllers
│   │   ├── api/             # Ajax lookups
│   │   └── XxxController.php
│   ├── models/              # Database interactions (fat models)
│   │   ├── Xxx.php
│   │   ├── evaluations/     # Evaluation models
│   │   ├── lookups/         # Lookup/Dictionary tables
│   │   ├── parameters/      # Model parameters
│   │   └── system/          # System utilities
│   ├── views/               # HTML templates
│   │   ├── partials/        # Shared partials (header, footer)
│   │   ├── errors/          # Error pages (400, 403, 404, 500)
│   │   ├── auth/            # Login, register, ORCID
│   │   ├── member/          # Member profiles
│   │   ├── repository/      # Document views
│   │   ├── admin/           # Admin dashboard
│   │   └── home/            # Home page
│   ├── engine/              # System mechanics (ErrorHandler)
│   └── config/
│       └── routes.php       # Route definitions
├── config/
│   ├── config.php           # Global settings
│   └── Database.php         # PDO singleton connection
├── database/
│   └── mysql.txt            # Full database schema with seed data
├── storage/
│   ├── uploads/             # Secure PDF/Zip storage
│   └── logs/                # Error logs
├── plugins/                 # Extensibility (MathJax support)
├── themes/                  # UI themes (high-contrast)
└── references/              # Reference articles (read-only)
```

## 📦 Requirements
- PHP 8.0+
- MySQL 8.0+ / MariaDB 10.4+
- Web server (Apache/Nginx) or PHP built-in server for development

## ⚙️ Installation & Setup
- **Local installation and development**

```bash
# Clone the repository
git clone https://github.com/wtannd/core.git
cd core

# Create the database
mysql -u root -p < database/mysql.txt

# Copy and edit configuration
cp config/config.example.php config/config.php
# Edit config.php with your database credentials and settings

# Start the development server
php -S localhost:8000 -t public/
```

Visit `http://localhost:8000` in your browser.

- **Deployment on web hosting service**
	- Upload your entire repository (excluding any local config.php) to the server.
	- Create DB on Host using the host's control panel (like cPanel)
	- Run Intall Script by navigating to https://yourdomain.com/install.php.
	- Enter the database credentials you just created and your desired admin login.
	- Delete the install script once it succeeds.

## 📄 License

This project is licensed under the GNU GPLv3 License - see the [LICENSE](LICENSE) file for details.

## 📌 Links & References

- Website: [openarxiv.org](https://openarxiv.org)
- ORCID: [orcid.org](https://orcid.org)
- MirrorUniverse: [mirroruniverse.org ](https://mirroruniverse.org)
- References: [Original_Motivation](references/science_full7.pdf), [Example_OePRESS](references/oepress3.pdf), [CORE](references/core.pdf)
