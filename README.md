# LearnDash Bulk Create

A WordPress plugin that extends LearnDash functionality to allow bulk creation of Courses, Lessons, or Topics using a CSV file.

## Description

LearnDash Bulk Create adds a new menu item under LearnDash in the WordPress admin panel, allowing administrators to bulk create LearnDash content (Courses, Lessons, or Topics) by uploading a CSV file. This plugin streamlines the process of creating multiple LearnDash content items at once, saving time and effort for course creators.

## Features

- Bulk create Courses, Lessons, or Topics
- Upload CSV file with content details
- Specify parent relationships (e.g., Course ID for Lessons, Lesson ID for Topics)
- Downloadable CSV template for easy formatting
- Seamless integration with LearnDash LMS

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- LearnDash LMS plugin (version 3.0 or higher)

## Installation

This plugin is designed to be installed using Composer. To install, follow these steps:

1. Ensure you have Composer installed on your system.

2. Add the plugin repository to your project's `composer.json` file:

   ```json
   {
     "repositories": [
       {
         "type": "vcs",
         "url": "https://github.com/serenichron/learndash-bulk-create"
       }
     ]
   }
   ```

3. Require the plugin in your project:

   ```
   composer require serenichron/learndash-bulk-create
   ```

4. If you're using a custom installer location for WordPress plugins, make sure it's configured correctly in your `composer.json`:

   ```json
   {
     "extra": {
       "installer-paths": {
         "wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
       }
     }
   }
   ```

5. Update your autoloader:

   ```
   composer dump-autoload
   ```

6. Activate the plugin through the WordPress admin panel or WP-CLI.

## Usage

1. Navigate to LearnDash > Bulk Create in the WordPress admin menu.
2. Choose the content type you want to create (Course, Lesson, or Topic).
3. If creating Lessons or Topics, enter the parent ID (Course ID for Lessons, Lesson ID for Topics).
4. Download the CSV template and fill it with your content details.
5. Upload your prepared CSV file.
6. Click "Upload and Create" to process the file and create the content.

## CSV Format

The CSV file should have the following columns:

- `post_title`: The title of the course, lesson, or topic
- `post_content`: The main content
- Additional columns for custom fields (if needed)

You can download a template CSV file from the Bulk Create page in the admin panel.

## Support

For bug reports or feature requests, please use the [GitHub issue tracker](https://github.com/serenichron/learndash-bulk-create/issues).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
```

## Changelog

### 1.0.0
- Initial release
