# Pie Calendar Extended

Extends the [Pie Calendar](https://piecalendar.com) WordPress plugin with extra
event display layouts and Category/Tag filter controls on the native calendar.

It combines two former standalone add-ons — **Pie Calendar Layouts** and
**Pie Calendar Filter** — into a single plugin. It hooks into Pie Calendar
rather than modifying it, so Pie Calendar can be updated normally.

## Features

- **Extra display layouts** — render events in **List**, **Compact**, or
  **Column** layouts via the `[piecal_layouts]` shortcode or the *Pie Calendar
  Layouts* Gutenberg block. The editor preview and front end share one renderer,
  so they never drift apart.
- **Per-instance styling** — colors, buttons, and date badges are configurable
  in the block's Styles tab or as shortcode attributes, with site-wide defaults
  in the Customizer.
- **Category / Tag filtering** — adds Category and Tag dropdowns to Pie
  Calendar's native (FullCalendar) controls bar. Selections combine, and a
  *Reset Filters* button is always available. Only terms present on the
  calendar's events are listed.

## Requirements

- WordPress 6.0+
- PHP 7.4+
- The **Pie Calendar** plugin, installed and active (folder slug `pie-calendar`).

## Installation

1. Install and activate the Pie Calendar plugin.
2. Download the latest release ZIP (or clone this repo into
   `wp-content/plugins/pie-calendar-extended`).
3. Activate **Pie Calendar Extended** from **Plugins** in wp-admin.

If you previously ran *Pie Calendar Layouts* or *Pie Calendar Filter* as
standalone plugins, deactivate them first. The `[piecal_layouts]` shortcode and
block names are unchanged, so existing pages keep working.

## Usage

Layout shortcode examples:

```
[piecal_layouts layout="compact" post_type="event" limit="6" time="upcoming"]
[piecal_layouts post_id="425" time="upcoming"]
```

See **[SHORTCODE.md](SHORTCODE.md)** for the full list of `[piecal_layouts]`
attributes, accepted values, and more examples.

Filtering works automatically on any Pie Calendar rendered on the page. To
target custom taxonomies instead of the default `category` and `post_tag`, use
the `pcf_category_taxonomy` and `pcf_tag_taxonomy` filters.

> **Note:** the Category/Tag filter controls act on Pie Calendar's native
> calendar widget. They do **not** filter the `[piecal_layouts]` List/Compact/
> Column output, which is rendered separately.

## Structure

```
pie-calendar-extended.php   Bootstrap: constants, dependency notice, boots the classes
uninstall.php               Removes the Customizer's global default colors on delete
includes/
  class-pcl-query.php       Fetches Pie Calendar events (WP_Query + recurrence expansion)
  class-pcl-render.php      Renders the List/Compact/Column layout markup
  class-pcl-shortcode.php   [piecal_layouts] shortcode + attribute sanitizing
  class-pcl-block.php       Dynamic Gutenberg block (shares the renderer)
  class-pcl-customizer.php  Site-wide default colors panel
  class-pce-filter.php      Category/Tag filter controls for the native calendar
assets/                     Front-end CSS/JS and the block editor script
```

## Development

The plugin is plain PHP with no build step. To work on it locally, symlink or
copy this folder into a WordPress install's `wp-content/plugins/` directory
alongside an active Pie Calendar plugin.

Lint the PHP before committing:

```bash
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l
```

## License

[GPL-2.0-or-later](LICENSE) © Bryan Miller / [Creative Reload](https://creativereload.com)
