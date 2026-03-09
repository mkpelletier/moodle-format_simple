# Simple format (format_simple)

A clean, minimalist course format for Moodle 5.0+ that presents content one unit at a time with smooth transitions and a focused learning experience.

## Features

- **Single-section display** with crossfade transitions between units
- **Three-zone layout** automatically categorises activities into Learning Content, Related Resources, and Related Activities
- **Inline content rendering** for pages, books, H5P, SCORM, and video URLs (YouTube/Vimeo)
- **Learning outcomes** configurable per section, displayed in a popout panel
- **Custom navigation panel** with completion progress indicators (replaces Moodle's course index)
- **Cog navigation** replaces the secondary nav with a tile-grid popover available on all course pages
- **Persistent home button** for navigating back to the course from activity pages
- **Course Info overlay** (optional) displays section 0 in a modal accessible from every page
- **Course banner** (optional) hero image with course name on section 0

## Requirements

- Moodle 5.0 or later

## Installation

1. Download or clone this repository into `course/format/simple` in your Moodle installation.
2. Visit **Site administration > Notifications** to complete the installation.
3. When creating or editing a course, select **Simple format** from the course format dropdown.

## Configuration

Course-level settings (under **Course settings > Course format**):

| Setting | Description | Default |
|---------|-------------|---------|
| Hidden sections | Whether hidden sections are collapsed or invisible | Invisible |
| Show course banner | Display a hero banner on the Course Info section | Yes |
| Show Course Info as overlay | Display section 0 in a floating overlay instead of inline | No |

Section-level settings (under **Edit section**):

| Setting | Description |
|---------|-------------|
| Learning outcomes | One outcome per line, displayed in a popout panel |

## How it works

Activities are automatically sorted into zones based on their module type:

- **Learning Content**: page, H5P, SCORM, LTI, lesson, label, video URLs
- **Related Resources**: URL, resource (file), book, folder
- **Related Activities**: assignment, quiz, forum, and all other activity types

The first learning content item in each section is displayed inline (embedded) rather than as a link.

## Testing

### PHPUnit

```bash
php vendor/bin/phpunit --testsuite format_simple_testsuite
```

### Behat

```bash
php admin/tool/behat/cli/run.php --tags=@format_simple
```

## License

This plugin is licensed under the [GNU GPL v3 (or later)](https://www.gnu.org/copyleft/gpl.html).

## Credits

Developed by the [South African Theological Seminary](https://www.sats.ac.za/) (ICT Department).
