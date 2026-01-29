(function (Drupal, once) {

  Drupal.behaviors.roomLayoutCapacity = {
    attach(context) {

      once('room-layout-capacity', '.room-layout-picker', context)
        .forEach(picker => {

          /* ------------------------------------------------------------
           * ELEMENTEN
           * ------------------------------------------------------------ */

          const valueInput = picker.querySelector('input[type="hidden"]');
          const summary    = picker.querySelector('.room-layout-summary');
          const toggle     = picker.querySelector('.room-layout-toggle');
          const rows       = picker.querySelectorAll('.room-layout-row');
          const chips      = picker.querySelectorAll('.chip');

          if (!valueInput || !summary) {
            return;
          }

          /* ------------------------------------------------------------
           * STATE
           * ------------------------------------------------------------ */

          let layouts = [];
          let capacityCache = {};
          let activeFilter = 'all';

          try {
            layouts = JSON.parse(valueInput.value || '[]');
          }
          catch (e) {
            layouts = [];
          }

          /* ------------------------------------------------------------
           * HELPERS
           * ------------------------------------------------------------ */

          function sync() {
            valueInput.value = JSON.stringify(layouts);
          }

          function isSelected(tid) {
            return layouts.some(item => item.layout === tid);
          }

          function addLayout(tid) {
            if (isSelected(tid)) return;

            layouts.push({
              layout: tid,
              capacity: capacityCache[tid] ?? 0
            });

            sync();
            render();
          }

          function removeLayout(tid) {
            layouts = layouts.filter(item => item.layout !== tid);
            sync();
            render();
          }
          
          function updateCapacity(tid, value) {
            capacityCache[tid] = value;

            layouts.forEach(item => {
              if (item.layout === tid) {
                item.capacity = value;
              }
            });
            sync();
          }


          /* ------------------------------------------------------------
           * RENDER SUMMARY
           * ------------------------------------------------------------ */

          function renderSummary() {
            summary.innerHTML = '';

            layouts.forEach(item => {
              const row = picker.querySelector(
                `.room-layout-row[data-tid="${item.layout}"]`
              );

              if (!row) return;

              const label = row.dataset.label;
              const icon  = row.dataset.icon;

              summary.insertAdjacentHTML('beforeend', `
                <div class="summary-item" data-tid="${item.layout}">
                  <button type="button" class="summary-remove">✕</button>
                  <span class="summary-icon">${icon}</span>
                  <span class="summary-label">${label}</span>
                  <input
                    type="number"
                    class="summary-capacity"
                    min="0"
                    value="${item.capacity || ''}">
                </div>
              `);
            });
          }

          /* ------------------------------------------------------------
           * RENDER LIST
           * ------------------------------------------------------------ */

          function renderList() {
            rows.forEach(row => {
              const tid = parseInt(row.dataset.tid, 10);

              if (isSelected(tid)) {
                row.style.display = 'none';
                return;
              }

              if (activeFilter === 'all' || row.dataset.group === activeFilter) {
                row.style.display = '';
              }
              else {
                row.style.display = 'none';
              }
            });
          }

          function render() {
            renderSummary();
            renderList();
          }

          /* ------------------------------------------------------------
           * EVENTS
           * ------------------------------------------------------------ */

          // Toggle open/close
          toggle?.addEventListener('click', e => {
            e.preventDefault();
            picker.classList.toggle('open');
            toggle.textContent = picker.classList.contains('open')
              ? Drupal.t('– Hide layouts')
              : Drupal.t('+ Add layout');
          });

          // Add layout (klik op rij)
          rows.forEach(row => {
            
            row.setAttribute('tabindex', '0');

            row.addEventListener('click', () => {
              const tid = parseInt(row.dataset.tid, 10);
              addLayout(tid);
            });
          });

          // Remove + capacity
          summary.addEventListener('click', e => {
            if (e.target.classList.contains('summary-remove')) {
              const tid = parseInt(
                e.target.closest('.summary-item').dataset.tid,
                10
              );
              removeLayout(tid);
            }
          });

          summary.addEventListener('input', e => {
            if (e.target.classList.contains('summary-capacity')) {
              const tid = parseInt(
                e.target.closest('.summary-item').dataset.tid,
                10
              );
              updateCapacity(tid, parseInt(e.target.value || 0, 10));
            }
          });

              // Filtering
              chips.forEach(chip => {
                chip.addEventListener('click', () => {
                  chips.forEach(c => c.classList.remove('active'));
                  chip.classList.add('active');

                  activeFilter = chip.dataset.filter;
                  render();
                                   
              rows.forEach(row => {
                const tid = parseInt(row.dataset.tid, 10);
                if (isSelected(tid)) {
                  row.style.display = 'none';
                  return;
                }

                row.style.display =
                  filter === 'all' || row.dataset.group === filter
                    ? ''
                    : 'none';
              });
            });
          });

          /* ------------------------------------------------------------
           * INIT
           * ------------------------------------------------------------ */

          render();

        });
    }
  };

})(Drupal, once);


