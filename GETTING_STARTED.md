# Getting Started with Daycare Timekeeper

## Your Application is Ready!

The application is currently running at: **http://localhost:8080**

## Quick Access Links

- **Parent Login**: http://localhost:8080/index.php
- **Staff Admin**: http://localhost:8080/admin.php

## Test Login Credentials

### For Parents (Test Account)
- **PIN**: `123456`
- **Family**: Smith Family
- **Children**: Emma Smith, Noah Smith

### For Staff (Admin)
- **PIN**: `123456`
- **Name**: Admin User

## First Steps

### 1. Test the Parent Flow
1. Open http://localhost:8080/index.php in your browser
2. Enter PIN: `123456`
3. You'll see the Smith family's 2 children
4. Try checking in Emma
5. Try checking out Emma

### 2. Test the Admin Flow
1. Open http://localhost:8080/admin.php
2. Enter PIN: `123456`
3. View the dashboard showing currently checked-in children
4. Try adding a new family:
   - Click "Add New Family"
   - Fill in family details
   - Set a new PIN (4-6 digits)
5. Add children to the new family
6. Go to Reports tab to see attendance records

## Understanding the Code

Since you're learning PHP and JavaScript, here's what each file does:

### Backend PHP Files (in `/src/`)

1. **auth.php** - Handles all login/logout
   - `loginParent()` - Checks parent PIN
   - `loginStaff()` - Checks staff PIN
   - `isParentLoggedIn()` - Checks if someone is logged in
   - Uses PHP sessions to remember who's logged in

2. **timekeeping.php** - Handles check-in/check-out
   - `checkInChild()` - Records when a child arrives
   - `checkOutChild()` - Records when a child leaves
   - Calculates overtime automatically
   - Saves data to JSON files by month

3. **config.php** - Settings and helper functions
   - `loadJsonFile()` - Reads JSON data
   - `saveJsonFile()` - Writes JSON data
   - `calculateHours()` - Math for time calculations

### Frontend Files (in `/public/`)

1. **index.php** - Parent interface
   - Shows login screen if not logged in
   - Shows children cards if logged in
   - Handles check-in/out button clicks

2. **admin.php** - Staff interface
   - Dashboard with statistics
   - Forms to add families and children
   - Tab navigation for different sections

3. **reports.php** - Reporting tool
   - Generates attendance reports
   - Filter by child and date range
   - Print-friendly layout

4. **css/styles.css** - All the styling
   - Responsive design (works on phones/tablets)
   - Bright, friendly colors for daycare theme
   - Touch-friendly buttons

### Data Storage (in `/data/`)

All data is stored in simple JSON files:

1. **families.json** - Family and children information
2. **users.json** - Staff accounts and PINs
3. **timekeeping/YYYY-MM.json** - Check-in/out records by month

## Key PHP Concepts Used

### 1. Sessions
```php
session_start(); // Starts session
$_SESSION['family_id'] = 'fam_001'; // Store data
isset($_SESSION['family_id']); // Check if exists
```

### 2. POST Form Handling
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pin = $_POST['pin']; // Get form data
}
```

### 3. Password Hashing (Security)
```php
password_hash($pin, PASSWORD_DEFAULT); // Create hash
password_verify($pin, $hash); // Check if PIN matches
```

### 4. File Operations
```php
file_get_contents($file); // Read file
file_put_contents($file, $data); // Write file
json_decode($json, true); // Convert JSON to array
json_encode($array); // Convert array to JSON
```

## Docker Commands

### Start the application
```bash
docker-compose up -d
```

### Stop the application
```bash
docker-compose down
```

### View logs
```bash
docker logs daycare_timekeeper
```

### Restart after changes
```bash
docker-compose up -d --build
```

### Access container shell (for debugging)
```bash
docker exec -it daycare_timekeeper bash
```

## Customizing the Application

### Change Business Rules
Edit `config.php`:
```php
define('MAX_HOURS_PER_DAY', 9.0); // Change from 8.5 to 9 hours
define('OVERAGE_RATE_PER_MINUTE', 1.50); // Change from $1 to $1.50
```

### Change Colors
Edit `public/css/styles.css`:
```css
/* Line 12: Change background gradient */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Line 37: Change primary color */
color: #5a67d8; /* This is the main purple color */
```

### Change Timezone
Edit `config.php`:
```php
date_default_timezone_set('America/Los_Angeles'); // Change to your timezone
```

## Common Modifications

### Add a New Field to Children

1. Edit `data/families.json` and add the field:
```json
{
  "id": "child_001",
  "first_name": "Emma",
  "emergency_contact": "555-9999"  // NEW FIELD
}
```

2. Edit `public/admin.php` to add input field in the form
3. Edit `public/index.php` to display the new field

### Add Email Notifications

You would need to:
1. Install a PHP mail library
2. Add email sending function in `src/timekeeping.php`
3. Call it when overtime occurs

## Troubleshooting

### Application not loading
```bash
# Check if container is running
docker ps

# Check logs for errors
docker logs daycare_timekeeper

# Restart container
docker-compose restart
```

### Permission errors
```bash
chmod -R 775 data/
```

### Changes not appearing
- Clear browser cache
- Hard refresh: Ctrl+Shift+R (Windows) or Cmd+Shift+R (Mac)

## Next Steps for Learning

1. **Experiment**: Try changing colors, text, button labels
2. **Add Features**:
   - Add a notes field to check-ins
   - Add emergency contact display
   - Add child age calculation
3. **Study the Code**: Read the comments in each file
4. **Modify Forms**: Add new fields to the family/child forms

## Resources for Learning

- PHP Basics: https://www.php.net/manual/en/langref.php
- PHP Sessions: https://www.php.net/manual/en/book.session.php
- JSON in PHP: https://www.php.net/manual/en/book.json.php
- CSS Flexbox: https://css-tricks.com/snippets/css/a-guide-to-flexbox/
- CSS Grid: https://css-tricks.com/snippets/css/complete-guide-grid/

## Support

If something doesn't work:
1. Check the browser console for JavaScript errors (F12)
2. Check Docker logs for PHP errors
3. Read the code comments - every function is explained
4. Review the README.md for more details

---

**Congratulations!** You now have a fully functional daycare timekeeper application. Take your time exploring the code and making it your own!
