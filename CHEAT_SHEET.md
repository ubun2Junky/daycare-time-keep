# Developer Cheat Sheet

Quick reference guide for common tasks as you develop and customize the application.

## File Organization

```
📁 Project Root
├── 📄 config.php              ← Main settings (hours, rates, timezone)
├── 📁 src/                    ← Backend PHP code (business logic)
│   ├── auth.php              ← Login/logout functions
│   └── timekeeping.php       ← Check-in/out functions
├── 📁 public/                 ← Frontend (what users see)
│   ├── index.php             ← Parent interface
│   ├── admin.php             ← Staff interface
│   ├── reports.php           ← Reports page
│   └── 📁 css/
│       └── styles.css        ← All styling
└── 📁 data/                   ← JSON data files
    ├── families.json         ← Family & children data
    ├── users.json            ← Staff accounts
    └── 📁 timekeeping/
        └── 2025-01.json      ← Monthly records
```

## Common PHP Patterns Used

### 1. Loading JSON Data
```php
// Load families
$data = loadJsonFile(FAMILIES_FILE);

// Access data
foreach ($data['families'] as $family) {
    echo $family['name'];
}
```

### 2. Saving JSON Data
```php
// Load existing data
$data = loadJsonFile(FAMILIES_FILE);

// Modify data
$data['families'][] = $newFamily;

// Save back to file
saveJsonFile(FAMILIES_FILE, $data);
```

### 3. Checking if User is Logged In
```php
if (isParentLoggedIn()) {
    // User is logged in, show interface
} else {
    // Not logged in, show login form
}
```

### 4. Getting Current User
```php
$family = getCurrentFamily();
echo $family['name']; // "Smith Family"
echo $family['children'][0]['first_name']; // "Emma"
```

### 5. Form Handling
```php
// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = $_POST['name'];
    $pin = $_POST['pin'];

    // Do something with data
    $result = loginParent($pin);

    // Show result to user
    if ($result['success']) {
        $message = $result['message'];
    }
}
```

## Common HTML/PHP Patterns

### 1. Display Data from PHP
```php
<h1><?php echo htmlspecialchars($family['name']); ?></h1>
```
**Note**: Always use `htmlspecialchars()` to prevent XSS attacks!

### 2. Loop Through Array
```php
<?php foreach ($family['children'] as $child): ?>
    <div class="child-card">
        <h3><?php echo htmlspecialchars($child['first_name']); ?></h3>
    </div>
<?php endforeach; ?>
```

### 3. Conditional Display
```php
<?php if ($isCheckedIn): ?>
    <span class="status-checked-in">Checked In</span>
<?php else: ?>
    <span class="status-not-here">Not Here</span>
<?php endif; ?>
```

### 4. Create a Form
```php
<form method="POST" action="/admin.php">
    <input type="hidden" name="action" value="add_family">

    <input type="text" name="family_name" required>
    <input type="password" name="family_pin" required>

    <button type="submit">Submit</button>
</form>
```

## CSS Tips

### Common Classes

```css
/* Buttons */
.btn                 /* Base button style */
.btn-primary         /* Purple gradient button */
.btn-success         /* Green button */
.btn-danger          /* Red button */
.btn-full            /* Full width button */

/* Cards */
.card                /* White container with shadow */
.child-card          /* Individual child card */

/* Alerts */
.alert               /* Base alert style */
.alert-success       /* Green success message */
.alert-error         /* Red error message */
.alert-warning       /* Orange warning */
```

### Customizing Colors

Main colors in `styles.css`:

```css
/* Line 12: Background gradient */
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);

/* Line 37: Primary purple */
color: #5a67d8;

/* Line 109: Success green */
background: #48bb78;

/* Line 117: Danger red */
background: #f56565;
```

## Database (JSON) Structure

### families.json
```json
{
  "families": [
    {
      "id": "fam_001",
      "name": "Smith Family",
      "pin_hash": "hashed_password_here",
      "contact_phone": "555-0123",
      "contact_email": "email@example.com",
      "children": [
        {
          "id": "child_001",
          "first_name": "Emma",
          "last_name": "Smith",
          "birth_date": "2020-05-15",
          "photo_base64": "",
          "notes": "Any special notes"
        }
      ]
    }
  ]
}
```

### timekeeping/2025-01.json
```json
{
  "month": "2025-01",
  "records": [
    {
      "id": "rec_001",
      "child_id": "child_001",
      "date": "2025-01-15",
      "check_in_time": "08:00:00",
      "check_out_time": "16:45:00",
      "duration_hours": 8.75,
      "overage_minutes": 15,
      "overage_charge": 15.00,
      "checked_in_by": "parent",
      "checked_out_by": "parent",
      "notes": ""
    }
  ]
}
```

## Useful Functions Reference

### From config.php
```php
loadJsonFile($filepath)                    // Read JSON file
saveJsonFile($filepath, $data)             // Write JSON file
generateId($prefix)                        // Create unique ID
formatTime($time)                          // Format time nicely
calculateHours($startTime, $endTime)       // Calculate duration
```

### From auth.php
```php
isParentLoggedIn()                         // Check if parent logged in
isStaffLoggedIn()                          // Check if staff logged in
getCurrentFamily()                         // Get logged in family data
getCurrentStaff()                          // Get logged in staff data
loginParent($pin)                          // Login parent
loginStaff($pin)                           // Login staff
logout()                                   // Log out current user
hashPin($pin)                              // Hash a PIN for storage
```

### From timekeeping.php
```php
checkInChild($childId, $childName)         // Check in a child
checkOutChild($childId, $childName)        // Check out a child
getActiveCheckIn($childId)                 // Check if child is checked in
getCurrentlyCheckedIn()                    // Get all checked in children
getChildRecords($childId, $start, $end)    // Get child's records
```

## Quick Modifications

### Change Maximum Hours
`config.php` line 20:
```php
define('MAX_HOURS_PER_DAY', 9.0); // Change from 8.5
```

### Change Overtime Rate
`config.php` line 21:
```php
define('OVERAGE_RATE_PER_MINUTE', 1.50); // Change from 1.00
```

### Change Timezone
`config.php` line 24:
```php
date_default_timezone_set('America/Los_Angeles');
```

### Add a New Page
1. Create `public/newpage.php`
2. Add at top:
```php
<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../src/auth.php';
requireStaffLogin(); // or requireParentLogin()
?>
```
3. Access at: `http://localhost:8080/newpage.php`

## Debugging Tips

### View PHP Errors
Check Docker logs:
```bash
docker logs daycare_timekeeper
```

### View in Browser
Add this temporarily to see variable contents:
```php
echo '<pre>';
print_r($variable);
echo '</pre>';
```

### Check Session Data
```php
echo '<pre>';
print_r($_SESSION);
echo '</pre>';
```

### Check POST Data
```php
echo '<pre>';
print_r($_POST);
echo '</pre>';
```

## Security Reminders

1. **Always use `htmlspecialchars()`** when displaying user data:
   ```php
   echo htmlspecialchars($userInput);
   ```

2. **Never store PINs in plain text** - use `hashPin()`:
   ```php
   $hash = hashPin('123456');
   ```

3. **Check user is logged in** before showing sensitive data:
   ```php
   requireStaffLogin(); // at top of file
   ```

## Testing Workflow

1. **Make changes** to PHP/HTML/CSS files
2. **Refresh browser** (Ctrl+Shift+R for hard refresh)
3. If changes don't appear:
   - Clear browser cache
   - Rebuild Docker: `docker-compose up -d --build`
4. **Check for errors**:
   - Browser console (F12)
   - Docker logs

## Git Commands (If Using Version Control)

```bash
# Initial setup
git init
git add .
git commit -m "Initial commit"

# Daily workflow
git status                    # See what changed
git add .                     # Stage all changes
git commit -m "Description"   # Save changes
```

## Before Going to Production

- [ ] Change all default PINs
- [ ] Enable HTTPS
- [ ] Set up backups for `data/` folder
- [ ] Test on mobile devices
- [ ] Review file permissions
- [ ] Update timezone setting
- [ ] Adjust maximum hours and rates
- [ ] Add real child photos (optional)

---

**Remember**: Every file in this project has detailed comments. When in doubt, read the comments in the relevant file!
