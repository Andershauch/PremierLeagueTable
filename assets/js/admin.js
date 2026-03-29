(function () {
  if (typeof jQuery === "undefined") {
    return;
  }

  const previewConfig = window.pltAdminPreview || {};
  const fontFamilies = previewConfig.fontFamilies || {};
  const groups = Array.isArray(previewConfig.groups) ? previewConfig.groups : [];
  const notes = previewConfig.notes || {};
  const focusFallback = previewConfig.focusFallback || "Tottenham Hotspur";
  const storageKey = "pltAppearanceGroups";

  function normalizeTeamName(value) {
    return String(value || "")
      .toLowerCase()
      .replace(/\b(fc|afc|cf)\b/g, " ")
      .replace(/[^a-z0-9 ]+/g, " ")
      .replace(/\s+/g, " ")
      .trim();
  }

  jQuery(function ($) {
    if (typeof $.fn.wpColorPicker === "function") {
      $(".plt-color-field").wpColorPicker({
        change: function () {
          window.requestAnimationFrame(updatePreview);
        },
        clear: function () {
          window.requestAnimationFrame(updatePreview);
        },
      });
    }

    const $preset = $('select[name="plt_settings[visual_preset]"]');
    const $fontFamily = $('select[name="plt_settings[font_family]"]');
    const $teamFontFamily = $('select[name="plt_settings[team_font_family]"]');
    const $focusTeamFontFamily = $('select[name="plt_settings[focus_team_font_family]"]');
    const $headerFontFamily = $('select[name="plt_settings[header_font_family]"]');
    const $teamFontWeight = $('select[name="plt_settings[team_font_weight]"]');
    const $focusTeamFontWeight = $('select[name="plt_settings[focus_team_font_weight]"]');
    const $headerFontWeight = $('select[name="plt_settings[header_font_weight]"]');
    const $fontScale = $('select[name="plt_settings[font_scale]"]');
    const $density = $('select[name="plt_settings[density]"]');
    const $favoriteTeam = $('select[name="plt_settings[favorite_team]"]');
    const $zebraRows = $('input[name="plt_settings[zebra_rows]"]');
    const $customRows = $(".plt-appearance-row--custom");
    const $preview = $("#plt-live-preview");
    const $previewNote = $("#plt-preview-note");
    const $previewRows = $preview.find("tbody tr");
    const $appearanceTable = $('select[name="plt_settings[visual_preset]"]')
      .closest("table")
      .first();

    const colorFields = {
      textColor: $('input[name="plt_settings[text_color]"]'),
      gridColor: $('input[name="plt_settings[grid_color]"]'),
      zebraRowBg: $('input[name="plt_settings[zebra_row_bg]"]'),
      zebraRowText: $('input[name="plt_settings[zebra_row_text]"]'),
      headerBg: $('input[name="plt_settings[header_bg_color]"]'),
      headerText: $('input[name="plt_settings[header_text_color]"]'),
      favoriteBg: $('input[name="plt_settings[favorite_row_bg]"]'),
      favoriteText: $('input[name="plt_settings[favorite_row_text]"]'),
    };

    function getColorValue($field) {
      const fallback = String($field.data("default-color") || "");
      const value = String($field.val() || "").trim();
      return value || fallback;
    }

    function updateCustomRows() {
      const isCustom = $preset.val() === "custom";
      $customRows.toggleClass("plt-appearance-row--hidden", !isCustom);
      $(".plt-appearance-group-row").toggleClass("plt-appearance-row--hidden", !isCustom);
      $previewNote.text(isCustom ? notes.custom || "" : notes.legacy || "");
      toggleZebraRows();
    }

    function getStoredGroupState() {
      try {
        return JSON.parse(window.localStorage.getItem(storageKey) || "{}");
      } catch (error) {
        return {};
      }
    }

    function setStoredGroupState(id, isExpanded) {
      const state = getStoredGroupState();
      state[id] = isExpanded;
      try {
        window.localStorage.setItem(storageKey, JSON.stringify(state));
      } catch (error) {
        // Ignore storage failures and keep the UI functional.
      }
    }

    function getRowForField(fieldName) {
      return $appearanceTable
        .find(`[name="plt_settings[${fieldName}]"]`)
        .first()
        .closest("tr");
    }

    function setGroupExpanded(id, isExpanded) {
      const $heading = $appearanceTable.find(`.plt-group-toggle[data-group="${id}"]`);
      const $rows = $appearanceTable.find(`.plt-group-member[data-group="${id}"]`);
      $heading.attr("aria-expanded", isExpanded ? "true" : "false");
      $rows.toggleClass("plt-group-member--hidden", !isExpanded);
      setStoredGroupState(id, isExpanded);
      toggleZebraRows();
    }

    function buildAppearanceGroups() {
      if (!$appearanceTable.length || !groups.length || $appearanceTable.find(".plt-group-toggle").length) {
        return;
      }

      const storedState = getStoredGroupState();

      groups.forEach((group, index) => {
        const rows = (group.fields || [])
          .map((fieldName) => getRowForField(fieldName))
          .filter(($row) => $row.length);

        if (!rows.length) {
          return;
        }

        rows.forEach(($row) => {
          $row.addClass("plt-group-member").attr("data-group", group.id);
        });

        const $headingRow = $(`
          <tr class="plt-appearance-group-row ${group.customOnly ? "plt-appearance-row--custom" : ""}" data-group="${group.id}">
            <td colspan="2">
              <button type="button" class="plt-group-toggle" data-group="${group.id}" aria-expanded="true">
                <span class="plt-group-toggle__label"></span>
                <span class="plt-group-toggle__icon" aria-hidden="true"></span>
              </button>
              <p class="plt-group-toggle__description"></p>
            </td>
          </tr>
        `);

        $headingRow.find(".plt-group-toggle__label").text(group.label || "");
        $headingRow.find(".plt-group-toggle__description").text(group.description || "");
        rows[0].before($headingRow);

        const initialExpanded = Object.prototype.hasOwnProperty.call(storedState, group.id)
          ? !!storedState[group.id]
          : index === 0;
        setGroupExpanded(group.id, initialExpanded);
      });
    }

    function toggleZebraRows() {
      const zebraEnabled = $zebraRows.is(":checked");
      const $zebraBgRow = getRowForField("zebra_row_bg");
      const $zebraTextRow = getRowForField("zebra_row_text");

      [$zebraBgRow, $zebraTextRow].forEach(($row) => {
        if (!$row.length) {
          return;
        }

        $row.toggleClass("plt-conditional-row--hidden", !zebraEnabled);
      });
    }

    function updatePreviewClasses() {
      const isCustom = $preset.val() === "custom";
      $preview.removeClass(
        "plt-skin-legacy plt-skin-custom plt-font-small plt-font-medium plt-font-large plt-density-compact plt-density-comfortable plt-zebra-on"
      );

      if (isCustom) {
        $preview.addClass("plt-skin-custom");
        $preview.addClass(`plt-font-${$fontScale.val() || "medium"}`);
        $preview.addClass(`plt-density-${$density.val() || "comfortable"}`);
        if ($zebraRows.is(":checked")) {
          $preview.addClass("plt-zebra-on");
        }
      } else {
        $preview.addClass("plt-skin-legacy plt-font-medium plt-density-comfortable");
      }
    }

    function updatePreviewStyles() {
      const isCustom = $preset.val() === "custom";
      if (!isCustom) {
        $preview.removeAttr("style");
        return;
      }

      const fontKey = String($fontFamily.val() || "theme");
      const styleVars = {
        "--plt-font-family": fontFamilies[fontKey] || "inherit",
        "--plt-team-font-family":
          fontFamilies[String($teamFontFamily.val() || "theme")] || "inherit",
        "--plt-focus-team-font-family":
          fontFamilies[String($focusTeamFontFamily.val() || "theme")] || "inherit",
        "--plt-header-font-family":
          fontFamilies[String($headerFontFamily.val() || "theme")] || "inherit",
        "--plt-team-font-weight": String($teamFontWeight.val() || "400"),
        "--plt-focus-team-font-weight": String($focusTeamFontWeight.val() || "700"),
        "--plt-header-font-weight": String($headerFontWeight.val() || "600"),
        "--plt-body-text": getColorValue(colorFields.textColor),
        "--plt-meta-text": getColorValue(colorFields.textColor),
        "--plt-grid": getColorValue(colorFields.gridColor),
        "--plt-zebra-bg": getColorValue(colorFields.zebraRowBg),
        "--plt-zebra-text": getColorValue(colorFields.zebraRowText),
        "--plt-header-bg": getColorValue(colorFields.headerBg),
        "--plt-header-text": getColorValue(colorFields.headerText),
        "--plt-favorite-bg": getColorValue(colorFields.favoriteBg),
        "--plt-favorite-text": getColorValue(colorFields.favoriteText),
      };

      const inlineStyle = Object.entries(styleVars)
        .map(([key, value]) => `${key}: ${value}`)
        .join("; ");

      $preview.attr("style", inlineStyle);
    }

    function updatePreviewFocusRow() {
      const desiredTeam = normalizeTeamName($favoriteTeam.val() || focusFallback);
      let foundFocusRow = false;

      $previewRows.each(function () {
        const $row = $(this);
        const rowTeam = normalizeTeamName($row.data("team"));
        const isFavorite =
          desiredTeam !== "" &&
          (rowTeam === desiredTeam ||
            rowTeam.indexOf(desiredTeam) !== -1 ||
            desiredTeam.indexOf(rowTeam) !== -1);

        $row.toggleClass("is-favorite", isFavorite);
        if (isFavorite) {
          foundFocusRow = true;
        }
      });

      if (!foundFocusRow) {
        $previewRows
          .filter('[data-team="tottenham hotspur"]')
          .addClass("is-favorite");
      }
    }

    function updatePreview() {
      updateCustomRows();
      updatePreviewClasses();
      updatePreviewStyles();
      updatePreviewFocusRow();
    }

    $(document).on(
      "change input",
      'select[name^="plt_settings["], input[name^="plt_settings["]',
      updatePreview
    );

    $(document).on("click", ".plt-group-toggle", function () {
      const $button = $(this);
      const groupId = String($button.data("group") || "");
      const isExpanded = $button.attr("aria-expanded") === "true";
      if (groupId !== "") {
        setGroupExpanded(groupId, !isExpanded);
      }
    });

    buildAppearanceGroups();
    updatePreview();
  });
})();
