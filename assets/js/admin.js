(function ($) {
    const token = "999999";

    function refreshIndexes() {
        const $sections = $("[data-mqd-section]");

        $sections.each(function (sectionIndex) {
            const $section = $(this);
            const $titleField = $section.find("[data-mqd-section-title]");
            const titlePreview = $titleField.val() || "Nueva seccion";

            $section.find(".mqd-section-title-preview").text(titlePreview);
            replaceIndex($section, /sections\[\d+\]/g, `sections[${sectionIndex}]`);

            $section.find("[data-mqd-item]").each(function (itemIndex) {
                replaceIndex($(this), /items\]\[\d+\]/g, `items][${itemIndex}]`);
            });
        });

        $("[data-mqd-button]").each(function (buttonIndex) {
            replaceIndex($(this), /pdf_buttons\[\d+\]/g, `pdf_buttons[${buttonIndex}]`);
        });
    }

    function replaceIndex($root, pattern, replacement) {
        $root.find("[name]").each(function () {
            const current = $(this).attr("name");
            $(this).attr("name", current.replace(pattern, replacement));
        });
    }

    function buildTemplate(template, sectionIndex, itemIndex) {
        return template
            .replaceAll(`sections[${token}]`, `sections[${sectionIndex}]`)
            .replaceAll(`items][${token}]`, `items][${itemIndex}]`)
            .replaceAll(`pdf_buttons[${token}]`, `pdf_buttons[${sectionIndex}]`);
    }

    function toggleSection($button) {
        const $body = $button.closest("[data-mqd-section]").find("[data-mqd-collapse-body]").first();
        const $symbol = $button.find(".mqd-collapse-symbol");
        const isHidden = $body.prop("hidden");

        $body.prop("hidden", !isHidden);
        $symbol.text(isHidden ? "-" : "+");
    }

    $(document).on("click", "[data-mqd-collapse-toggle]", function () {
        toggleSection($(this));
    });

    $(document).on("input", "[data-mqd-section-title]", function () {
        const value = $(this).val() || "Nueva seccion";
        $(this).closest("[data-mqd-section]").find(".mqd-section-title-preview").text(value);
    });

    $(document).on("click", "[data-mqd-add-section]", function () {
        const sectionIndex = $("[data-mqd-section]").length;
        const html = buildTemplate(mqdAdmin.sectionTemplate, sectionIndex, 0);
        $("[data-mqd-sections]").append(html);
        refreshIndexes();
    });

    $(document).on("click", "[data-mqd-remove-section]", function () {
        $(this).closest("[data-mqd-section]").remove();
        refreshIndexes();
    });

    $(document).on("click", "[data-mqd-add-item]", function () {
        const $section = $(this).closest("[data-mqd-section]");
        const sectionIndex = $section.index();
        const itemIndex = $section.find("[data-mqd-item]").length;
        const html = buildTemplate(mqdAdmin.itemTemplate, sectionIndex, itemIndex);

        $section.find("[data-mqd-items]").append(html);
        refreshIndexes();
    });

    $(document).on("click", "[data-mqd-remove-item]", function () {
        $(this).closest("[data-mqd-item]").remove();
        refreshIndexes();
    });

    $(document).on("click", "[data-mqd-add-button]", function () {
        const buttonIndex = $("[data-mqd-button]").length;
        const html = buildTemplate(mqdAdmin.buttonTemplate, buttonIndex, 0);

        $("[data-mqd-buttons]").append(html);
        refreshIndexes();
    });

    $(document).on("click", "[data-mqd-remove-button]", function () {
        $(this).closest("[data-mqd-button]").remove();
        refreshIndexes();
    });

    $(document).on("click", "[data-mqd-pick-pdf]", function (event) {
        event.preventDefault();

        const $target = $(this).closest("[data-mqd-button]").find('input[type="url"]');
        const frame = wp.media({
            title: mqdAdmin.mediaTitle,
            button: { text: mqdAdmin.mediaButton },
            library: { type: "application/pdf" },
            multiple: false
        });

        frame.on("select", function () {
            const attachment = frame.state().get("selection").first().toJSON();
            $target.val(attachment.url).trigger("change");
        });

        frame.open();
    });

    $(document).on("click", "[data-mqd-pick-image]", function (event) {
        event.preventDefault();

        const $target = $(this).closest(".mqd-pdf-row").find('input[type="url"]').first();
        const frame = wp.media({
            title: mqdAdmin.imageTitle,
            button: { text: mqdAdmin.imageButton },
            library: { type: "image" },
            multiple: false
        });

        frame.on("select", function () {
            const attachment = frame.state().get("selection").first().toJSON();
            $target.val(attachment.url).trigger("change");
        });

        frame.open();
    });

    refreshIndexes();
})(jQuery);
