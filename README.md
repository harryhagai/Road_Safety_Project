# Confidential Web Portal for Road Safety Reporting with Geospatial Mapping

This project is a secure web-based portal designed to help registered drivers report road safety incidents and traffic violations under an identified driver account. It also gives road officers and transport authorities a protected dashboard for managing reports, reviewing evidence, and analyzing road safety hotspots using location data.

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

- linking every driver report to a registered driver, vehicle, plate number, and organization
- capturing accurate incident locations through geospatial mapping
- supporting photo and video evidence submission
- helping officers review, verify, and manage reports efficiently
- improving prevention through hotspot analysis and report tracking

## Main Objectives

- Build a secure web portal for identified driver road safety reporting
- Provide a mobile-friendly reporting interface for citizens and commuters
- Integrate geospatial tools for location capture, visualization, and analysis
- Support transport authorities and road officers with an administrative dashboard
- Improve decision-making and preventive action through data-driven reporting

## Core Users

- Registered Drivers: identified drivers who submit road safety incidents
- Passengers: guests or registered passengers who submit bus-related evidence
- Road Officers: authorized personnel who log in to manage and analyze reports
- Administrators: privileged users who manage protected system settings

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
- Application Tier: Laravel business logic, single-guard authentication, role authorization, validation, notifications, and report processing
- Data Tier: MySQL database with one `users` table for passenger, driver, road officer, and admin accounts, plus reports, coordinates, evidence, logs, and analytics

## Expected Features

- Identified driver incident reporting
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
- Driver registration, authentication, and identified reporting workflow
- Google Maps integration for incident location capture
- Secure dashboard for road officers
- Reporting and analytics support

### Excluded

- Separate native mobile app
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
