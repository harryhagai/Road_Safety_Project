# Confidential Web Portal for Road Safety Reporting with Geospatial Mapping

This project is a secure web-based portal designed to help citizens and commuters report road safety incidents and traffic violations anonymously. It also gives road officers and transport authorities a protected dashboard for managing reports, reviewing evidence, and analyzing road safety hotspots using location data.

## Project Details

- Project Title: Development of Confidential Web Portal for Road Safety Reporting with Geospatial Mapping
- Project Type: Software Project
- Student Name: HAGAI HAROLD NGOBEY
- Registration Number: NIT/BIT/2023/2185
- Program: BIT
- Level: 8
- Institution: National Institute of Transport
- Faculty: Faculty of Information Technology and Education
- Department: Department of Computing and Communication Technology
- Supervisor: Mr. RODRICK MERO

## Purpose of the System

The system is intended to solve common road safety reporting challenges in Tanzania by:

- allowing anonymous reporting without account registration
- capturing accurate incident locations through geospatial mapping
- supporting photo and video evidence submission
- helping officers review, verify, and manage reports efficiently
- improving prevention through hotspot analysis and report tracking

## Main Objectives

- Build a secure and confidential web portal for anonymous road safety reporting
- Provide a mobile-friendly reporting interface for citizens and commuters
- Integrate geospatial tools for location capture, visualization, and analysis
- Support transport authorities and road officers with an administrative dashboard
- Improve decision-making and preventive action through data-driven reporting

## Core Users

- Anonymous Reporters: citizens and commuters who submit road safety incidents
- Road Officers: authorized personnel who log in to manage and analyze reports

## Technologies Used

This project is mainly built with the following technologies:

- PHP 8.3+
- Laravel 13
- MySQL
- Bootstrap 5
- JavaScript
- HTML5
- CSS3
- Google Maps API

## System Architecture

The project follows a three-tier architecture:

- Presentation Tier: reporter interface, map interaction, officer dashboard, forms, and status pages
- Application Tier: Laravel business logic, authentication, validation, notifications, and report processing
- Data Tier: MySQL database for reports, coordinates, files, officer accounts, logs, and analytics

## Expected Features

- Anonymous incident reporting
- Location selection on map
- Evidence upload support
- Reference number generation for report tracking
- Officer authentication and dashboard access
- Report review and status management
- Geospatial visualization and hotspot analysis
- Report filtering and export support

## Project Scope

### Included

- Web-based reporting platform for desktop and mobile devices
- Anonymous reporting workflow
- Google Maps integration for incident location capture
- Secure dashboard for road officers
- Reporting and analytics support

### Excluded

- Native Android or iOS mobile app
- Payment or fine processing
- Integration with social media platforms
- Integration with traffic lights, CCTV, or emergency vehicle tracking
- Reporter account registration

## Local Setup

1. Install PHP and Composer dependencies.

```bash
composer install
```

2. Create the environment file if needed, then generate the app key.

```bash
copy .env.example .env
php artisan key:generate
```

3. Configure your database in `.env`.

Example current database values:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=rsp_db
DB_USERNAME=root
DB_PASSWORD=
```

4. Run database migrations.

```bash
php artisan migrate
```

5. Start the application.

```bash
composer run dev
```

## Frontend Notes

Bootstrap assets are served locally from the project and combined with custom styling for the road safety interface.

## License

This project is protected under the terms provided in the `LICENSE` file. All rights and ownership information for the roadofficer work are stated there.
