(function (Drupal, once) {
  Drupal.behaviors.awturFacilitiesTabs = {
    attach(context) {
      once('awtur-facilities-tabs', '#section-faciliteiten .form-tabs', context).forEach((tabsEl) => {
        const tabs = Array.from(tabsEl.querySelectorAll('[role="tab"]'));
        const panels = Array.from(tabsEl.querySelectorAll('[role="tabpanel"]'));
        const defaultTabId = tabsEl.dataset.defaultTab;

        if (!tabs.length || !panels.length) {
          return;
        }

        const activateTab = (nextTab, moveFocus = false) => {
          tabs.forEach((tab) => {
            const isActive = tab === nextTab;
            const panelId = tab.getAttribute('aria-controls');
            const panel = panelId ? tabsEl.querySelector(`#${panelId}`) : null;

            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.tabIndex = isActive ? 0 : -1;
            tab.classList.toggle('is-active', isActive);

            if (panel) {
              panel.hidden = !isActive;
              panel.classList.toggle('is-active', isActive);

              // These form fields are still rendered as Drupal details elements.
              // When the tab is active we force its content open; inactive tabs close.
              panel.querySelectorAll('details').forEach((detailsEl) => {
                detailsEl.open = isActive;
              });
            }
          });

          if (moveFocus) {
            nextTab.focus();
          }
        };

        tabs.forEach((tab, index) => {
          tab.addEventListener('click', () => activateTab(tab));

          tab.addEventListener('keydown', (event) => {
            let targetIndex = null;

            if (event.key === 'ArrowRight') {
              targetIndex = (index + 1) % tabs.length;
            }
            else if (event.key === 'ArrowLeft') {
              targetIndex = (index - 1 + tabs.length) % tabs.length;
            }
            else if (event.key === 'Home') {
              targetIndex = 0;
            }
            else if (event.key === 'End') {
              targetIndex = tabs.length - 1;
            }

            if (targetIndex === null) {
              return;
            }

            event.preventDefault();
            activateTab(tabs[targetIndex], true);
          });
        });

        const initialTab = tabs.find((tab) => tab.id === defaultTabId) || tabs[0];
        activateTab(initialTab);
      });
    }
  };
})(Drupal, once);
