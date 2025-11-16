# Daycare Timekeeper

A simple, responsive web application for daycare centers to track children's check-in and check-out times, manage families, and generate attendance reports.

## Features

### For Parents
- ✅ Simple PIN-based login
- ✅ Check children in and out with one tap
- ✅ View check-in times and status
- ✅ Automatic overtime warnings (after 8.5 hours)
- ✅ Responsive design works on tablets and phones

### For Staff
- ✅ Secure admin dashboard
- ✅ View all currently checked-in children
- ✅ Add and manage families and children
- ✅ Generate detailed attendance reports
- ✅ Print reports for billing purposes
- ✅ Track overtime charges automatically

## Technology Stack

- **Backend**: PHP 8.2
- **Frontend**: HTML5, CSS3, Vanilla JavaScript
- **Data Storage**: JSON files (no database required)
- **Deployment**: Docker

## Installation & Setup

### Option 1: Using Docker (Recommended)

1. **Make sure Docker is installed** on your system
   - Download from [docker.com](https://www.docker.com/get-started)

2. **Build and start the application**:
   ```bash
   docker-compose up -d
   ```

3. **Access the application**:
   - Open your browser and go to: `http://localhost:8080`
   - Parent login: `http://localhost:8080/index.php`
   - Staff login: `http://localhost:8080/admin.php`

4. **Stop the application**:
   ```bash
   docker-compose down
   ```

### Option 2: Without Docker

1. **Requirements**:
   - PHP 8.0 or higher
   - Apache or Nginx web server
   - Write permissions for the `data/` directory

2. **Setup**:
   - Copy all files to your web server's document root
   - Point your web server to the `public/` directory
   - Ensure the `data/` directory is writable:
     ```bash
     chmod -R 775 data/
     ```

3. **Access the application**:
   - Navigate to your web server's URL

## Default Login Credentials

### Test Family Account
- **PIN**: `123456`
- **Family**: Smith Family (2 children)

### Test Staff Account
- **PIN**: `123456`
- **Role**: Admin

**⚠️ IMPORTANT**: Change these PINs before using in production!

## Project Structure

```
daycare_timekeeper/
├── public/                  # Web-accessible files
│   ├── index.php           # Parent login & check-in/out
│   ├── admin.php           # Staff dashboard
│   ├── reports.php         # Reporting tool
│   ├── css/
│   │   └── styles.css      # All styling
│   └── images/             # Image uploads
├── src/                    # Backend logic (not web-accessible)
│   ├── auth.php            # Authentication functions
│   ├── timekeeping.php     # Check-in/out logic
│   └── families.php        # Family management (future)
├── data/                   # Data storage
│   ├── families.json       # Family & children data
│   ├── users.json          # Staff PINs
│   └── timekeeping/
│       └── 2025-01.json    # Monthly attendance records
├── config.php              # Main configuration
├── Dockerfile              # Docker build instructions
└── docker-compose.yml      # Docker Compose configuration
```

## How It Works

### For Parents

1. **Login**: Enter your family PIN on the home page
2. **Check-In**: Tap your child's card and press "Check In"
3. **Check-Out**: When picking up, tap the child's card and press "Check Out"
4. **Overtime Warning**: If your child stays longer than 8.5 hours, you'll see a warning with the additional charges

### For Staff

1. **Login**: Go to `/admin.php` and enter your staff PIN
2. **Dashboard**: View all children currently checked in and their hours
3. **Manage Families**:
   - Click "Add New Family" to create a family account
   - Set their PIN (4-6 digits)
   - Add children to families with their information
4. **Reports**:
   - Click "Reports" tab
   - Select a child or "All Children"
   - Choose date range
   - Click "Generate Report"
   - Print for billing purposes

## Configuration

Edit `config.php` to customize:

```php
// Maximum hours before overtime
define('MAX_HOURS_PER_DAY', 8.5);

// Overtime rate per minute
define('OVERAGE_RATE_PER_MINUTE', 1.00); // $1 per minute

// Timezone
date_default_timezone_set('America/New_York');
```

## Data Storage

- All data is stored in JSON files in the `data/` directory
- Timekeeping records are organized by month (e.g., `2025-01.json`)
- Files are automatically created as needed
- **Backup**: Simply copy the `data/` directory to backup all your data

## Security Notes

1. **Change Default PINs**: The test accounts use `123456` - change these immediately!
2. **HTTPS**: In production, always use HTTPS to encrypt data in transit
3. **Backups**: Regularly backup the `data/` directory
4. **File Permissions**: Ensure `data/` is writable but not publicly accessible via web
5. **Updates**: Keep PHP and Docker updated for security patches

## Changing PINs

To change a PIN, you need to generate a new hash:

1. Create a file called `hash_pin.php` in the project root:
   ```php
   <?php
   $pin = '654321'; // Your new PIN
   echo password_hash($pin, PASSWORD_DEFAULT);
   ?>
   ```

2. Run it:
   ```bash
   php hash_pin.php
   ```

3. Copy the output hash and paste it into `data/families.json` or `data/users.json`

## Adding Child Photos

Photos are stored as base64 strings. To add a photo:

1. Convert your image to base64 (many free online tools available)
2. In the admin interface, you can paste the base64 string when adding/editing a child
3. Format: `data:image/jpeg;base64,/9j/4AAQSkZJRg...`

## Troubleshooting

### "Permission denied" errors
```bash
chmod -R 775 data/
chown -R www-data:www-data data/  # Linux/Mac
```

### Port 8080 already in use
Edit `docker-compose.yml` and change `"8080:80"` to `"8081:80"` (or any other available port)

### Changes not showing up
- Clear your browser cache
- If using Docker, rebuild: `docker-compose up -d --build`

### Can't log in
- Check that the PIN hash in the JSON file is correct
- Ensure you're using the right PIN (default is `123456`)

## Future Enhancements

Potential features to add:
- Photo upload interface (currently base64 paste)
- Email notifications for overtime
- SMS reminders
- Weekly/monthly email reports to parents
- Multi-location support
- Staff time tracking
- Billing integration

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the code comments - every file is heavily documented
3. Check file permissions and PHP error logs

## License

This is a custom application. Modify and use as needed for your daycare center.

## Development Notes

Built with ❤️ for daycares by a developer learning PHP and JavaScript.

- **PHP Version**: 8.2
- **No database required**: Everything uses simple JSON files
- **No external dependencies**: Pure PHP, HTML, CSS, and JavaScript
- **Responsive design**: Works on tablets, phones, and desktop
- **Print-friendly reports**: Optimized for printing attendance records
