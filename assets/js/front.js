const lastOpenSectionKey = "mqd-last-open-section";

function animateSection(content, expanded) {
    if (!content) {
        return;
    }

    content.hidden = false;
    content.style.overflow = "hidden";

    const startHeight = expanded ? 0 : content.scrollHeight;
    const endHeight = expanded ? content.scrollHeight : 0;

    content.style.maxHeight = `${startHeight}px`;
    content.offsetHeight;
    content.style.maxHeight = `${endHeight}px`;

    window.setTimeout(() => {
        if (expanded) {
            content.style.maxHeight = "none";
        } else {
            content.hidden = true;
            content.style.maxHeight = "0px";
        }
    }, 260);
}

function setSectionState(toggle, expanded) {
    const section = toggle.closest(".mqd-section");
    const content = section ? section.querySelector("[data-mqd-section-content]") : null;
    const symbol = toggle.querySelector(".mqd-toggle-symbol");

    if (!content || !symbol) {
        return;
    }

    toggle.setAttribute("aria-expanded", expanded ? "true" : "false");
    symbol.textContent = expanded ? "-" : "+";
    section.classList.toggle("is-open", expanded);
    animateSection(content, expanded);
}

function closeOtherSections(activeToggle) {
    document.querySelectorAll(".mqd-section-toggle").forEach((toggle) => {
        if (toggle !== activeToggle) {
            setSectionState(toggle, false);
        }
    });
}

function openSection(toggle, shouldScroll = false) {
    const section = toggle.closest(".mqd-section");

    closeOtherSections(toggle);
    setSectionState(toggle, true);

    if (section && section.id) {
        try {
            window.localStorage.setItem(lastOpenSectionKey, section.id);
        } catch (error) {
            // Ignore storage failures quietly.
        }
    }

    if (shouldScroll && section) {
        const rect = section.getBoundingClientRect();
        if (rect.top < 12 || rect.top > window.innerHeight * 0.65) {
            section.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }
}

function restoreLastOpenSection() {
    let storedId = "";

    try {
        storedId = window.localStorage.getItem(lastOpenSectionKey) || "";
    } catch (error) {
        storedId = "";
    }

    const targetSection = storedId ? document.getElementById(storedId) : null;
    const targetToggle = targetSection ? targetSection.querySelector(".mqd-section-toggle") : null;

    if (targetToggle) {
        openSection(targetToggle, false);
        return;
    }

    const firstToggle = document.querySelector(".mqd-section-toggle");
    if (firstToggle) {
        openSection(firstToggle, false);
    }
}

function escapeRegExp(value) {
    return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function applyHighlight(element, query) {
    if (!element) {
        return;
    }

    const original = element.getAttribute("data-original-text") || element.textContent || "";
    if (!element.hasAttribute("data-original-text")) {
        element.setAttribute("data-original-text", original);
    }

    if (!query) {
        element.textContent = original;
        return;
    }

    const pattern = new RegExp(`(${escapeRegExp(query)})`, "ig");
    element.innerHTML = original.replace(pattern, "<mark>$1</mark>");
}

function updateResults(visibleItems) {
    const label = document.querySelector("[data-mqd-results]");
    if (label) {
        label.textContent = `${visibleItems} platos`;
    }
}

function updateClearButton(value) {
    const clearButton = document.querySelector("[data-mqd-clear-search]");
    if (clearButton) {
        clearButton.hidden = value.trim() === "";
    }
}

function updateShortcutState(activeId) {
    document.querySelectorAll(".mqd-shortcut").forEach((shortcut) => {
        shortcut.classList.toggle("is-active", shortcut.getAttribute("data-mqd-jump") === activeId);
    });
}

function filterMenu(query) {
    const normalized = query.trim().toLowerCase();
    const items = document.querySelectorAll("[data-mqd-item]");
    const sections = document.querySelectorAll("[data-mqd-section-shell]");
    let visibleItems = 0;
    let firstVisibleSection = null;

    items.forEach((item) => {
        const haystack = item.getAttribute("data-search-text") || "";
        const match = normalized === "" || haystack.includes(normalized);
        const title = item.querySelector("[data-mqd-item-name]");
        const english = item.querySelector("[data-mqd-item-english]");

        item.hidden = !match;
        applyHighlight(title, normalized);
        applyHighlight(english, normalized);

        if (match) {
            visibleItems += 1;
        }
    });

    sections.forEach((section) => {
        const toggle = section.querySelector(".mqd-section-toggle");
        const visibleSectionItems = section.querySelectorAll("[data-mqd-item]:not([hidden])");
        const hasVisibleItems = visibleSectionItems.length > 0;

        section.hidden = !hasVisibleItems;
        if (hasVisibleItems && !firstVisibleSection) {
            firstVisibleSection = section;
        }

        if (toggle && normalized !== "") {
            setSectionState(toggle, hasVisibleItems);
        }
    });

    const emptyState = document.querySelector("[data-mqd-empty-state]");
    if (emptyState) {
        emptyState.hidden = visibleItems !== 0;
    }

    updateResults(visibleItems);
    updateClearButton(normalized);

    if (firstVisibleSection) {
        updateShortcutState(firstVisibleSection.id);
    }
}

function updateBackTopVisibility() {
    const button = document.querySelector("[data-mqd-back-top]");
    if (!button) {
        return;
    }

    button.hidden = window.scrollY < 420;
}

function syncActiveSection() {
    const sections = Array.from(document.querySelectorAll(".mqd-section:not([hidden])"));
    if (!sections.length) {
        return;
    }

    let current = sections[0];
    sections.forEach((section) => {
        if (section.getBoundingClientRect().top <= 120) {
            current = section;
        }
    });

    updateShortcutState(current.id);
}

document.addEventListener("click", function (event) {
    const toggle = event.target.closest(".mqd-section-toggle");
    if (toggle) {
        const expanded = toggle.getAttribute("aria-expanded") === "true";
        if (expanded) {
            setSectionState(toggle, false);
            return;
        }

        openSection(toggle, true);
        syncActiveSection();
        return;
    }

    const shortcut = event.target.closest("[data-mqd-jump]");
    if (shortcut) {
        const targetId = shortcut.getAttribute("data-mqd-jump");
        const target = targetId ? document.getElementById(targetId) : null;
        const toggleTarget = target ? target.querySelector(".mqd-section-toggle") : null;

        if (target && toggleTarget) {
            openSection(toggleTarget, false);
            target.scrollIntoView({ behavior: "smooth", block: "start" });
            updateShortcutState(targetId);
        }
        return;
    }

    if (event.target.closest("[data-mqd-expand-all]")) {
        document.querySelectorAll(".mqd-section-toggle").forEach((sectionToggle) => {
            if (!sectionToggle.closest(".mqd-section").hidden) {
                setSectionState(sectionToggle, true);
            }
        });
        return;
    }

    if (event.target.closest("[data-mqd-collapse-all]")) {
        document.querySelectorAll(".mqd-section-toggle").forEach((sectionToggle) => {
            if (!sectionToggle.closest(".mqd-section").hidden) {
                setSectionState(sectionToggle, false);
            }
        });
        return;
    }

    if (event.target.closest("[data-mqd-clear-search]")) {
        const input = document.querySelector("[data-mqd-search]");
        if (input) {
            input.value = "";
            filterMenu("");
            input.focus();
        }
        return;
    }

    if (event.target.closest("[data-mqd-back-top]")) {
        window.scrollTo({ top: 0, behavior: "smooth" });
    }
});

document.addEventListener("input", function (event) {
    if (!event.target.matches("[data-mqd-search]")) {
        return;
    }

    filterMenu(event.target.value);
});

document.addEventListener("DOMContentLoaded", function () {
    restoreLastOpenSection();
    updateResults(document.querySelectorAll("[data-mqd-item]").length);
    syncActiveSection();
    updateBackTopVisibility();
});

window.addEventListener("scroll", function () {
    syncActiveSection();
    updateBackTopVisibility();
}, { passive: true });
