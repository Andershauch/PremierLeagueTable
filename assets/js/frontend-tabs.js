document.addEventListener('DOMContentLoaded', () => {
  const tabGroups = document.querySelectorAll('[data-plt-tabs]');

  tabGroups.forEach((group) => {
    const tabs = Array.from(group.querySelectorAll('[data-plt-tab]'));
    const panels = Array.from(group.querySelectorAll('.plt-tabs__panel'));

    const activateTab = (tab) => {
      const targetId = tab.getAttribute('aria-controls');

      tabs.forEach((item) => {
        const active = item === tab;
        item.classList.toggle('is-active', active);
        item.setAttribute('aria-selected', active ? 'true' : 'false');
      });

      panels.forEach((panel) => {
        const active = panel.id === targetId;
        panel.classList.toggle('is-active', active);
        panel.hidden = !active;
      });
    };

    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => activateTab(tab));
      tab.addEventListener('keydown', (event) => {
        if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
          return;
        }

        event.preventDefault();
        const direction = event.key === 'ArrowRight' ? 1 : -1;
        const nextIndex = (index + direction + tabs.length) % tabs.length;
        tabs[nextIndex].focus();
        activateTab(tabs[nextIndex]);
      });
    });
  });
});
