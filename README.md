# Staff Shift Log

A WordPress plugin for logging and managing staff shift requests with admin approval.

## Features

- Staff can submit shift requests from the frontend
- Administrators can approve or reject shift requests
- Calendar view of all shifts
- Dashboard widget for quick access to shift submission
- Admin bar menu for quick access to shift management
- Download shift calendar in CSV, Excel, or PDF format

## Installation

1. Upload the `staff-shift-log` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Create a page and add the shortcode `[staff_shift_form]` to display the shift submission form
4. Create a page and add the shortcode `[staff_shift_calendar]` to display the calendar view

## Calendar Download Feature

The plugin includes a feature to download the shift calendar in various formats (CSV, Excel, PDF). To use this feature, you need to install the required dependencies:

### Option 1: Using Composer (Recommended)

1. Make sure you have Composer installed on your server
2. Navigate to the plugin directory: `cd /path/to/wp-content/plugins/staff-shift-log`
3. Run: `composer install`

### Option 2: Manual Installation

If you can't use Composer, you can manually download and install the required libraries:

1. Download [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet/releases) and [TCPDF](https://github.com/tecnickcom/TCPDF/releases)
2. Create a `vendor` directory in the plugin folder
3. Extract the libraries into the `vendor` directory

## Usage

### For Staff

1. Navigate to the page with the `[staff_shift_form]` shortcode
2. Fill out the shift request form and submit
3. View your shift requests on the calendar page

### For Administrators

1. Go to the WordPress admin area
2. Navigate to "Staff Shifts" in the admin menu
3. Approve or reject shift requests
4. Use the "Download Calendar" feature to export the shift calendar

## Shortcodes

- `[staff_shift_form]` - Displays the shift submission form
- `[staff_shift_calendar]` - Displays the calendar view of all shifts

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- MySQL 5.7 or higher

## License

This plugin is licensed under the GPL v2 or later.

## Author

Augustin Phiri - [Website](https://augustinphiri.co.za/)