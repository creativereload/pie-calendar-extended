# `[piecal_layouts]` Shortcode Reference

Renders Pie Calendar events in a List, Compact, or Column layout.

```
[piecal_layouts layout="compact" post_type="event" limit="6" time="upcoming"]
[piecal_layouts post_id="425" time="upcoming"]
```

All attributes are optional. Colors left blank fall back to the site-wide
defaults in **Appearance → Customize → Pie Calendar Layouts**, and if none is
set there, to the plugin's stylesheet. The same attributes back the *Pie
Calendar Layouts* Gutenberg block.

---

## Content & query

| Attribute | Default | Accepts | Description |
|---|---|---|---|
| `layout` | `list` | `list`, `compact`, `column` | Which layout to render. An unrecognized value falls back to `list`. |
| `post_type` | *(empty)* | any post-type slug, or empty | Restrict to one post type. Empty = any type that has events. |
| `post_id` | `0` | a post ID | Show only this event's occurrences. **Overrides `post_type`.** |
| `limit` | `10` | integer | Maximum number of events/occurrences to show. |
| `time` | `upcoming` | `upcoming`, `past`, `all` | Filter occurrences relative to now. |
| `order` | `ASC` | `ASC`, `DESC` | Sort order by date. Anything other than `DESC` is treated as `ASC`. |

## Element toggles

| Attribute | Default | Accepts | Description |
|---|---|---|---|
| `show_image` | `yes` | `yes`/`no`, `true`/`false`, `1`/`0` | Show the event's featured image. |
| `show_excerpt` | `yes` | `yes`/`no`, `true`/`false`, `1`/`0` | Show the event excerpt. |
| `show_button` | `yes` | `yes`/`no`, `true`/`false`, `1`/`0` | Show the "View Event" button/link. |
| `excerpt_length` | `20` | integer | Excerpt length in words (when `show_excerpt` is on). |
| `link_text` | `View Event` | text | Label for the event button/link. |
| `date_format` | *(empty)* | [PHP date format](https://www.php.net/manual/en/datetime.format.php) | Override the date display. Empty = site's default format. |
| `columns` | `3` | `1`–`3` | Number of columns. **`column` layout only.** Clamped to 1–3. |

## Colors

Each accepts a hex (`#0a7`), a CSS function (`rgb()`, `rgba()`, `hsl()`,
`hsla()`), a CSS variable (`var(--accent)` — useful with theme palettes like
GeneratePress), or a named keyword (`tomato`). Blank = use the Customizer
default, then the stylesheet default.

| Attribute | Applies to |
|---|---|
| `card_bg_color` | Card background |
| `text_color` | Body text |
| `link_color` | Links |
| `link_hover_color` | Links (hover) |
| `border_color` | Card borders |
| `button_bg_color` | Button background |
| `button_text_color` | Button text |
| `button_bg_hover_color` | Button background (hover) |
| `button_text_hover_color` | Button text (hover) |
| `button_border_color` | Button border |
| `button_border_hover_color` | Button border (hover) |
| `badge_bg_color` | Date badge background |
| `badge_text_color` | Date badge text |
| `badge_border_color` | Date badge border |

## Border widths

Accept a pixel value (`2` or `2px`); blank uses the stylesheet default.

| Attribute | Applies to |
|---|---|
| `card_border_width` | Card border |
| `badge_border_width` | Date badge border |
| `button_border_width` | Button border |

## Corner radii

Per-corner border radius in pixels, clamped to **0–60**. `2` and `2px` both
work; blank uses the stylesheet default. Corners are top-left (`tl`),
top-right (`tr`), bottom-right (`br`), bottom-left (`bl`).

| Element | Attributes |
|---|---|
| Card | `card_radius_tl`, `card_radius_tr`, `card_radius_br`, `card_radius_bl` |
| Image | `image_radius_tl`, `image_radius_tr`, `image_radius_br`, `image_radius_bl` |
| Date badge | `badge_radius_tl`, `badge_radius_tr`, `badge_radius_br`, `badge_radius_bl` |
| Button | `button_radius_tl`, `button_radius_tr`, `button_radius_br`, `button_radius_bl` |

---

## Examples

Upcoming events in a 2-column grid, no excerpts:

```
[piecal_layouts layout="column" columns="2" show_excerpt="no" limit="6"]
```

A single event's upcoming occurrences, compact:

```
[piecal_layouts post_id="425" layout="compact" time="upcoming"]
```

Past events, newest first, custom button text and date format:

```
[piecal_layouts time="past" order="DESC" link_text="Recap" date_format="M j, Y"]
```

Branded card with rounded top corners:

```
[piecal_layouts card_bg_color="#0a7d55" text_color="#ffffff" card_radius_tl="12" card_radius_tr="12"]
```

> **Note:** these layouts are rendered independently of Pie Calendar's native
> calendar. The Category/Tag filter controls added by this plugin apply to the
> native calendar widget, **not** to `[piecal_layouts]` output.
