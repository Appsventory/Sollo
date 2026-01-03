<p align="center"><a href="https://github.com/Appsventory/Sollo" target="_blank"><img src="./public/assets/sollo-logo.png" width="300" alt="Sollo Logo"></a></p>

<p align="center">
  <strong>A simple and flexible PHP framework that helps you build websites and web applications with ease</strong>
</p>

<br>

<div align="center">
  <a href="https://github.com/Appsventory/Sollo/releases/latest">
    <img src="https://img.shields.io/github/v/release/Appsventory/Sollo?style=flat&logo=github&color=2bbc8a" alt="Latest Release">
  </a>
  <a href="https://github.com/Appsventory/Sollo/stargazers">
    <img src="https://img.shields.io/github/stars/Appsventory/Sollo?style=flat&logo=github&color=ffd700" alt="Stars">
  </a>
  <a href="https://github.com/Appsventory/Sollo/network/members">
    <img src="https://img.shields.io/github/forks/Appsventory/Sollo?style=flat&logo=github&color=blueviolet" alt="Forks">
  </a>
  <br>
  <a href="https://github.com/Appsventory/Sollo/releases">
    <img src="https://img.shields.io/github/downloads/Appsventory/Sollo/total?style=flat&color=orange" alt="Downloads">
  </a>
  <a href="https://github.com/Appsventory/Sollo/commits/main">
    <img src="https://img.shields.io/github/last-commit/Appsventory/Sollo?style=flat&logo=github&color=4c1" alt="Last Commit">
  </a>
  <img src="https://img.shields.io/badge/license-MIT-blue" alt="License">
</div>


# Quick Start

### 1. Clone Repository

```bash
git clone https://github.com/Appsventory/Sollo.git
cd Sollo
```

### 2. Install Dependencies (Optional)
```bash
composer install
```

### 3. Configure Environment
```bash
php fany make:env --name="My App" --with-example
# Edit .env file with your configuration
```

### 4. Start Development Server
```bash
php fany server
```

### 5. Access Your Application

Open your browser and navigate to: `http://localhost:8000`

---


## Project Structure

```

Sollo/
├── app/                    # Application layer (business-facing logic)
│   ├── Controllers/        # HTTP request controllers
│   ├── Database/           # Migrations, seeders, repositories
│   ├── Helpers/            # Stateless helper functions
│   ├── Middleware/         # HTTP middleware for request filtering
│   ├── Models/             # Data models and business logic
│   ├── Plugins/            # Optional feature modules / extensions
│   └── Routes/             # Application route definitions
│       ├── api.php         # Api routes configuration
│       └── web.php         # Web routes configuration
├── core/                   # Framework kernel (framework-agnostic)
│   ├── Console/            # Command line tools and scripts
│   ├── Foundation/         # App bootstrap
│   ├── Framework/          # Core framework implementation
│   │   ├── Exceptions/     # Global & system-level exception handling
│   │   └── Velo/           # Internal system services
│   ├── Providers/          # Service providers & dependency bindings
│   └── Support/            # Low-level utilities (collections, helpers, traits)
├── public/                 # Web accessible files (Document Root)
│   ├── assets/             # Static assets (images, fonts, files)
│   ├── css/                # Stylesheet files
│   ├── js/                 # JavaScript files
│   ├── storage/            # Public storage for uploaded files
│   ├── index.php           # Application entry point
│   ├── robots.txt          # Search engine crawling rules
│   └── .htaccess           # Apache URL rewriting rules
├── resources/              # Shared / system-level templates
│   ├── style/              # Source styles (SCSS, Tailwind, etc.)
│   └── Views/              # Template files and layouts
├── storage/                # Private application storage
│   ├── logs/               # Application logs
│   ├── cache/              # Application cache
│   └── framework/          # Application maintain
├── vendor/                 # Third-party packages and dependencies
├── .env                    # Environment variables and configuration
├── .env.example            # Environment configuration template
├── composer.json           # Composer dependencies
└── fany                    # Custom CLI tool for development tasks

```

## License

This project is licensed under the MIT License

## Acknowledgments

- Thanks to the PHP community for inspiration and best practices
- Special thanks to all contributors and early adopters
- Built with ♥︎ by the ICK Network Team

---

<p align="center">
  <strong>Made with ♥︎ by ICK Network Team</strong>
</p>