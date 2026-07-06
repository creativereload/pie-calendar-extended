/**
 * Pie Calendar Filter
 *
 * Adds Category / Tag dropdowns to the Pie Calendar controls bar and
 * shows/hides events based on the selection. Reads the pcfCategories and
 * pcfTags data attached to each event's extendedProps by the plugin's PHP side.
 */
(function () {
	"use strict";

	var data = window.PieCalendarFilterData || {};
	var L = data.strings || {};

	var strings = {
		filterByCategory: L.filterByCategory || "Filter by Category",
		allCategories: L.allCategories || "All Categories",
		filterByTag: L.filterByTag || "Filter by Tag",
		allTags: L.allTags || "All Tags",
		resetFilters: L.resetFilters || "Reset Filters"
	};

	var state = { category: "", tag: "" };
	var applying = false; // Re-entrancy guard (setProp triggers eventsSet).
	var built = false;
	var resetButton = null;

	function onReady(fn) {
		if (document.readyState !== "loading") {
			fn();
		} else {
			document.addEventListener("DOMContentLoaded", fn);
		}
	}

	// The calendar is created by Pie Calendar on DOMContentLoaded, so poll
	// briefly until window.calendar exists before building the UI.
	function waitForCalendar(attempts) {
		attempts = attempts || 0;

		if (window.calendar && typeof window.calendar.getEvents === "function") {
			init();
			return;
		}

		if (attempts > 100) {
			return; // Give up after ~10s.
		}

		setTimeout(function () {
			waitForCalendar(attempts + 1);
		}, 100);
	}

	function collectOptions() {
		var categories = {};
		var tags = {};

		window.calendar.getEvents().forEach(function (event) {
			var props = event.extendedProps || {};

			(props.pcfCategories || []).forEach(function (term) {
				if (term && term.slug) {
					categories[term.slug] = term.name;
				}
			});

			(props.pcfTags || []).forEach(function (term) {
				if (term && term.slug) {
					tags[term.slug] = term.name;
				}
			});
		});

		return { categories: categories, tags: tags };
	}

	function sortedEntries(map) {
		return Object.keys(map)
			.map(function (slug) {
				return { slug: slug, name: map[slug] };
			})
			.sort(function (a, b) {
				return String(a.name).localeCompare(String(b.name));
			});
	}

	function makeSelect(labelText, allText, entries, modifier, onChange) {
		var label = document.createElement("label");
		label.className = "pcf-filter pcf-filter--" + modifier;
		label.appendChild(document.createTextNode(labelText));

		var select = document.createElement("select");

		var allOption = document.createElement("option");
		allOption.value = "";
		allOption.textContent = allText;
		select.appendChild(allOption);

		entries.forEach(function (entry) {
			var option = document.createElement("option");
			option.value = entry.slug;
			option.textContent = entry.name;
			select.appendChild(option);
		});

		select.addEventListener("change", function () {
			onChange(select.value);
		});

		label.appendChild(select);
		return label;
	}

	function init() {
		if (built) {
			return;
		}

		var controls = document.querySelector(".piecal-controls");
		if (!controls) {
			return;
		}

		var options = collectOptions();
		var categoryEntries = sortedEntries(options.categories);
		var tagEntries = sortedEntries(options.tags);

		// Nothing to filter by yet. Events usually load via AJAX *after* init()
		// first runs, so treat this as "not ready" rather than "built" — leaving
		// `built` false lets a later eventsSet (via piecalFilterApply) retry once
		// events carrying terms exist. Otherwise the dropdowns would never appear.
		if (!categoryEntries.length && !tagEntries.length) {
			return;
		}

		var wrap = document.createElement("div");
		wrap.className = "pcf-filters";

		if (categoryEntries.length) {
			wrap.appendChild(
				makeSelect(strings.filterByCategory, strings.allCategories, categoryEntries, "category", function (value) {
					state.category = value;
					window.piecalFilterApply();
				})
			);
		}

		if (tagEntries.length) {
			wrap.appendChild(
				makeSelect(strings.filterByTag, strings.allTags, tagEntries, "tag", function (value) {
					state.tag = value;
					window.piecalFilterApply();
				})
			);
		}

		resetButton = document.createElement("button");
		resetButton.type = "button";
		resetButton.className = "fc-button fc-button-primary pcf-filter-reset";
		resetButton.textContent = strings.resetFilters;
		resetButton.addEventListener("click", function () {
			state.category = "";
			state.tag = "";
			wrap.querySelectorAll("select").forEach(function (select) {
				select.value = "";
			});
			window.piecalFilterApply();
		});
		wrap.appendChild(resetButton);

		// Place the filters right after the view chooser, else before the nav
		// buttons, else at the end of the controls bar.
		var viewChooser = controls.querySelector(".piecal-controls__view-chooser");
		var navGroup = controls.querySelector(".piecal-controls__navigation-button-group");

		if (viewChooser) {
			controls.insertBefore(wrap, viewChooser.nextSibling);
		} else if (navGroup) {
			controls.insertBefore(wrap, navGroup);
		} else {
			controls.appendChild(wrap);
		}

		built = true;
		window.piecalFilterApply();
	}

	// Exposed globally so the eventsSet callback injected via PHP can call it.
	window.piecalFilterApply = function () {
		// NOTE: `window.calendar` is auto-created by the browser as the <div id="calendar">
		// element and exists BEFORE Pie Calendar assigns the FullCalendar instance (which
		// happens after render()). The injected eventsSet handler fires during render(), so
		// a plain truthiness check passes on the DOM element and window.calendar.getEvents()
		// throws mid-render, aborting the whole calendar. Require the real instance.
		if (applying || !window.calendar || typeof window.calendar.getEvents !== "function") {
			return;
		}

		// The controls may not exist yet if events hadn't loaded when init() first
		// ran. Now that events have changed (this is called from the calendar's
		// eventsSet), try to build them. init() is a no-op once built, and on
		// success it calls back here to apply the filter, so we can return early.
		if (!built) {
			init();
			return;
		}

		applying = true;

		window.calendar.getEvents().forEach(function (event) {
			var props = event.extendedProps || {};

			var eventCategories = (props.pcfCategories || []).map(function (term) {
				return term.slug;
			});
			var eventTags = (props.pcfTags || []).map(function (term) {
				return term.slug;
			});

			var visible = true;

			if (state.category && eventCategories.indexOf(state.category) === -1) {
				visible = false;
			}
			if (state.tag && eventTags.indexOf(state.tag) === -1) {
				visible = false;
			}

			var desired = visible ? "auto" : "none";

			// Only write when the value actually changes. setProp triggers the
			// calendar's eventsSet callback (which calls this function again), so
			// writing unconditionally — even setting "auto" on an already-visible
			// event — causes an infinite render loop. Skipping no-op writes means
			// a redundant eventsSet finds nothing to change and stops.
			if (event.display !== desired) {
				event.setProp("display", desired);
			}
		});

		applying = false;
	};

	onReady(function () {
		waitForCalendar(0);
	});
})();
