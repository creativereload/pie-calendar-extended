=== Pie Calendar Extended ===
Contributors: creativereload
Author: Bryan Miller
Author URI: https://creativereload.com
Tags: pie calendar, events, calendar, event layouts, event filter
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Requires Plugins: pie-calendar
Stable tag: 0.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extends Pie Calendar with extra event display layouts and Category/Tag filter controls on the native calendar.

== Description ==

Pie Calendar Extended is an add-on for the Pie Calendar plugin that bundles two
features into one package:

**Extra display layouts**
Render your Pie Calendar events in List, Compact, or Column layouts using the
`[piecal_layouts]` shortcode or the "Pie Calendar Layouts" Gutenberg block. Both
the editor preview and the front end share the same renderer, so layouts never
drift out of sync. Colors, buttons, and date badges can be styled per-instance
in the block's Styles tab or the shortcode attributes, with site-wide defaults
set in the Customizer.

**Category / Tag filtering**
Adds Category and Tag dropdowns to Pie Calendar's native (FullCalendar) calendar
controls bar so visitors can filter which events are shown. Selections combine
(an event must match all active filters to remain visible), and a "Reset Filters"
button is always available. The dropdowns only list terms that actually appear on
the calendar's events.

The plugin hooks into Pie Calendar rather than modifying it, so Pie Calendar can
be updated normally. It requires the Pie Calendar plugin to be installed and
active.

== Installation ==

1. Install and activate the Pie Calendar plugin.
2. Upload the `pie-calendar-extended` folder to `/wp-content/plugins/`, or install
   the ZIP via Plugins > Add New > Upload Plugin.
3. Activate Pie Calendar Extended through the Plugins menu in WordPress.
4. Add the `[piecal_layouts]` shortcode (or the "Pie Calendar Layouts" block) to a
   page, and/or place a Pie Calendar to see the Category/Tag filter controls.

== Usage ==

Layout shortcode examples:

`[piecal_layouts layout="compact" post_type="event" limit="6" time="upcoming"]`
`[piecal_layouts post_id="425" time="upcoming"]`

Filtering works automatically on any Pie Calendar rendered on the page. To target
custom taxonomies instead of the standard `category` and `post_tag`, use the
`pcf_category_taxonomy` and `pcf_tag_taxonomy` filters.

== Frequently Asked Questions ==

= Does the Category/Tag filter apply to the [piecal_layouts] output? =

No. The filter controls act on Pie Calendar's native calendar widget. The
`[piecal_layouts]` List/Compact/Column output is rendered separately and is not
filtered by those dropdowns.

= Does this replace the separate Pie Calendar Layouts and Pie Calendar Filter plugins? =

Yes. Pie Calendar Extended combines both. If you previously ran either as a
standalone plugin, deactivate them before activating this one. The
`[piecal_layouts]` shortcode and block names are unchanged, so existing pages
keep working.

== Changelog ==

= 0.0.3 =
* Recurrence occurrences now link with pass-through occurrence data (eventstart/eventend/timezone), matching Pie Calendar's native calendar links, so `[piecal_info]` on the target page shows the clicked occurrence's date instead of the base event's. The original occurrence keeps its clean permalink.

= 0.0.2 =
* Added a configurable heading level (H2–H6, default H3) for event titles, via a new `heading_level` shortcode attribute and a "Title heading level" control in the block's Layout panel.
* Accessibility: date badges now render as semantic `<time datetime>` elements with a visually hidden full-date label for screen readers, and the weekday/time subtitle is wrapped in `<time datetime>`.

= 0.0.1 =
* Initial release of Pie Calendar Extended, combining "Pie Calendar Layouts" and "Pie Calendar Filter" into a single plugin.
* Unified the dependency admin notice, text domain, and asset handling.
* Added an uninstall handler that removes the Customizer's global default colors on deletion.

The version histories of the two source plugins are preserved below for reference.

= Layouts history (formerly Pie Calendar Layouts) =
= 1.5.0 =
* List, Compact, and Column layouts via the `[piecal_layouts]` shortcode and Gutenberg block, with per-instance and Customizer-level styling.

= Filter history (formerly Pie Calendar Filter) =
= 0.0.3 =
* Fixed: Calendar could get stuck on "Loading" and never render when the filter ran during calendar init (the `window.calendar` guard now requires the real FullCalendar instance, not the `<div id="calendar">` element).
* Fixed: Filter dropdowns now populate reliably when events load via AJAX after init, instead of staying empty.
* Changed: Removed the "Filter by Type" (post type) dropdown; the controls are now Category and Tag only.
* Changed: Filter controls are right-aligned in the calendar bar, and the "Reset Filters" button is always visible.
* Changed: Filter label font size set to 1em to match the view chooser.

= 0.0.2 =
* Fixed: Infinite render loop that froze the calendar on load (the filter now only updates an event's visibility when it actually changes).

= 0.0.1 =
* Initial release.

== Upgrade Notice ==

= 0.0.1 =
Initial release combining Pie Calendar Layouts and Pie Calendar Filter into one plugin. Deactivate those standalone plugins before activating Pie Calendar Extended.
