/**
 * Calendar Component for Daycare Timekeeper
 *
 * This creates an interactive calendar that allows selecting dates
 * to view and edit attendance records.
 */

// Global variables to track current calendar state
let currentDate = new Date();
let selectedDate = null;

/**
 * Generate calendar HTML for a given month/year
 *
 * @param {number} year - The year to display
 * @param {number} month - The month to display (0-11)
 * @param {Object} recordsByDate - Object with dates as keys and record counts as values
 * @returns {string} HTML string for the calendar
 */
function generateCalendar(year, month, recordsByDate = {}) {
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay(); // 0 = Sunday, 6 = Saturday

    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    const today = new Date();
    const todayStr = formatDateYMD(today);

    let html = '<div class="calendar-container">';

    // Calendar Header with navigation
    html += '<div class="calendar-header">';
    html += '<button class="btn btn-secondary" onclick="previousMonth()" style="padding: 8px 15px;">&larr; Previous</button>';
    html += '<h2 style="margin: 0; color: #2d3748;">' + monthNames[month] + ' ' + year + '</h2>';
    html += '<button class="btn btn-secondary" onclick="nextMonth()" style="padding: 8px 15px;">Next &rarr;</button>';
    html += '</div>';

    // Day names header
    html += '<div class="calendar-grid">';
    html += '<div class="calendar-day-header">Sun</div>';
    html += '<div class="calendar-day-header">Mon</div>';
    html += '<div class="calendar-day-header">Tue</div>';
    html += '<div class="calendar-day-header">Wed</div>';
    html += '<div class="calendar-day-header">Thu</div>';
    html += '<div class="calendar-day-header">Fri</div>';
    html += '<div class="calendar-day-header">Sat</div>';

    // Empty cells before first day of month
    for (let i = 0; i < startingDayOfWeek; i++) {
        html += '<div class="calendar-day empty"></div>';
    }

    // Days of the month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = formatDateYMD(new Date(year, month, day));
        const hasRecords = recordsByDate[dateStr] > 0;
        const isToday = dateStr === todayStr;
        const isSelected = selectedDate === dateStr;

        let classes = 'calendar-day';
        if (isToday) classes += ' today';
        if (isSelected) classes += ' selected';
        if (hasRecords) classes += ' has-records';

        html += '<div class="' + classes + '" onclick="selectDate(\'' + dateStr + '\')">';
        html += '<div class="day-number">' + day + '</div>';
        if (hasRecords) {
            html += '<div class="record-count">' + recordsByDate[dateStr] + ' records</div>';
        }
        html += '</div>';
    }

    html += '</div>'; // Close calendar-grid
    html += '</div>'; // Close calendar-container

    return html;
}

/**
 * Format date as YYYY-MM-DD
 */
function formatDateYMD(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return year + '-' + month + '-' + day;
}

/**
 * Format date as Month DD, YYYY
 */
function formatDateReadable(dateStr) {
    const date = new Date(dateStr + 'T00:00:00');
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    return monthNames[date.getMonth()] + ' ' + date.getDate() + ', ' + date.getFullYear();
}

/**
 * Navigate to previous month
 */
function previousMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    updateCalendar();
}

/**
 * Navigate to next month
 */
function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    updateCalendar();
}

/**
 * Select a specific date
 */
function selectDate(dateStr) {
    selectedDate = dateStr;
    // Reload the page with the selected date
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('date', dateStr);
    window.location.search = urlParams.toString();
}

/**
 * Initialize calendar on page load
 */
function initCalendar() {
    // Check if there's a date parameter in URL
    const urlParams = new URLSearchParams(window.location.search);
    const dateParam = urlParams.get('date');

    if (dateParam) {
        selectedDate = dateParam;
        currentDate = new Date(dateParam + 'T00:00:00');
    }
}

// Call init when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCalendar);
} else {
    initCalendar();
}
